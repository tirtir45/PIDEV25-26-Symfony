<?php

namespace App\Controller;

use App\Entity\Demande_emplois;
use App\Entity\Publications;
use App\Form\PublicationType;
use App\Repository\PublicationsRepository;
use App\Repository\UtilisateursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use App\Service\HuggingFaceService; // en haut du fichier
use App\Service\CvParserService;

#[Route('/publication')]
class PublicationController extends AbstractController
{
    private function requireAuth(Request $request): ?Response
    {
        if (!$request->getSession()->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    private function requireAdmin(Request $request): ?Response
    {
        if ($r = $this->requireAuth($request)) return $r;
        if ($request->getSession()->get('user_role') !== 'Administrateur') {
            throw $this->createAccessDeniedException();
        }
        return null;
    }

    #[Route('/offres', name: 'app_user_publication_index', methods: ['GET'])]
    public function publicIndex(Request $request, PublicationsRepository $publicationRepository): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $recherche    = $request->query->get('recherche');
        $contrat      = $request->query->get('contrat');
        $localisation = $request->query->get('localisation');

        $publications  = $publicationRepository->findByFilters($recherche, $contrat, $localisation);
        $localisations = $publicationRepository->findUniqueLocalisations();

        return $this->render('publication/user_offres.html.twig', [
            'publications'  => $publications,
            'localisations' => $localisations,
        ]);
    }

   #[Route('/{publication_id}', name: 'app_publication_show', methods: ['GET'], requirements: ['publication_id' => '\d+'])]
public function show(
    int $publication_id,
    PublicationsRepository $repo,
    Request $request,
    HuggingFaceService $hfService,
    CvParserService $cvParser,
    EntityManagerInterface $em
): Response {
    if ($r = $this->requireAuth($request)) return $r;

    $publication = $repo->find($publication_id);
    if (!$publication) throw $this->createNotFoundException();

    $matchingScores = [];

    foreach ($publication->getDemande_emplois() as $demande) {
        $demandeId = $demande->getDemande_id();

        // 1. Correction logique : Utiliser !== null (car un score de 0 est valide)
        if ($demande->getMatchingScore() !== null) {
            $matchingScores[$demandeId] = $demande->getMatchingScore();
            continue;
        }

        try {
            $cvFile = $demande->getCvUrl();
            if (!$cvFile) {
                $matchingScores[$demandeId] = null;
                continue;
            }

            $cvPath = $this->getParameter('kernel.project_dir') . '/public/uploads/cvs/' . $cvFile;

            // Vérification si le fichier existe vraiment sur le disque
            if (!file_exists($cvPath)) {
                $matchingScores[$demandeId] = null;
                continue;
            }

            // Extraction du texte
            $cvText = $cvParser->extractText($cvPath);
            $jobText = $publication->getDescription();
            //ajout ici 
            // NETTOYAGE : L'IA n'aime pas les textes trop longs ou pleins de caractères spéciaux
$cvText = mb_substr(strip_tags($cvText), 0, 1000); // Limiter à 1000 caractères
$jobText = mb_substr(strip_tags($jobText), 0, 1000);

            if (empty(trim($cvText)) || empty(trim($jobText))) {
                $matchingScores[$demandeId] = 0;
                continue;
            }

            // Calcul IA
            $score = $hfService->getSimilarityScore($cvText, $jobText);
            
            // !!! Si l'API HuggingFace renvoie une erreur, il faut savoir laquelle
            if ($score === null) {
                // Pour débugger, décommente la ligne suivante :
                // dd("L'API Hugging Face n'a pas renvoyé de score pour la demande " . $demandeId);
                $matchingScores[$demandeId] = null;
                continue;
            }

            $score = round($score * 100, 2);

            // Stockage
            $demande->setMatchingScore($score);
            $em->persist($demande);
            $matchingScores[$demandeId] = $score;

        } catch (\Exception $e) {
            // !!! C'est ici que l'erreur est cachée. 
            // Pour voir la vraie erreur, décommente la ligne suivante :
            // dd($e->getMessage()); 
            
            $matchingScores[$demandeId] = null;
        }
    }

    $em->flush();

    return $this->render('publication/show.html.twig', [
        'publication' => $publication,
        'matchingScores' => $matchingScores
    ]);
}

    #[Route('/{publication_id}/postuler', name: 'app_publication_postuler', methods: ['GET'], requirements: ['publication_id' => '\d+'])]
    public function postuler(int $publication_id, PublicationsRepository $pubRepo, UtilisateursRepository $userRepo, EntityManagerInterface $em, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $publication = $pubRepo->find($publication_id);
        if (!$publication) throw $this->createNotFoundException();

        $userId = $request->getSession()->get('user_id');
        $candidat = $userRepo->find($userId);

        $demandeExistante = $em->getRepository(Demande_emplois::class)->findOneBy([
            'publication_id' => $publication,
            'candidat_id'    => $candidat,
        ]);

        if ($demandeExistante) {
            $this->addFlash('error', 'Vous avez déjà postulé à cette offre.');
            return $this->redirectToRoute('app_publication_show', ['publication_id' => $publication->getPublication_id()]);
        }

        // Redirect to the application form with the publication ID
        return $this->redirectToRoute('app_demande_emploi_new', ['publication_id' => $publication->getPublication_id()]);
    }

    #[Route('/admin', name: 'app_admin_publication_index', methods: ['GET'])]
    public function adminIndex(PublicationsRepository $publicationRepository, Request $request): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        return $this->render('publication/index.html.twig', [
            'publications' => $publicationRepository->findAll(),
        ]);
    }

    #[Route('/admin/new', name: 'app_admin_publication_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $publication = new Publications();
        $form = $this->createForm(PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($publication);
            $em->flush();
            $this->addFlash('success', "L'offre '" . $publication->getTitre() . "' a été ajoutée avec succès !");
            return $this->redirectToRoute('app_admin_publication_index');
        }

        return $this->render('publication/new.html.twig', ['publication' => $publication, 'form' => $form->createView()]);
    }

    #[Route('/admin/{publication_id}/edit', name: 'app_admin_publication_edit', methods: ['GET', 'POST'], requirements: ['publication_id' => '\d+'])]
    public function edit(int $publication_id, Request $request, PublicationsRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $publication = $repo->find($publication_id);
        if (!$publication) throw $this->createNotFoundException();

        $form = $this->createForm(PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', "L'offre a été modifiée avec succès !");
            return $this->redirectToRoute('app_admin_publication_index');
        }

        return $this->render('publication/edit.html.twig', ['publication' => $publication, 'form' => $form->createView()]);
    }

    #[Route('/admin/{publication_id}', name: 'app_admin_publication_delete', methods: ['POST'], requirements: ['publication_id' => '\d+'])]
    public function delete(int $publication_id, Request $request, PublicationsRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $publication = $repo->find($publication_id);
        if (!$publication) throw $this->createNotFoundException();

        if ($this->isCsrfTokenValid('delete' . $publication->getPublication_id(), $request->request->get('_token'))) {
            $em->remove($publication);
            $em->flush();
            $this->addFlash('success', 'La publication a été supprimée avec succès !');
        }

        return $this->redirectToRoute('app_admin_publication_index');
    }
}
