<?php

namespace App\Controller;

use App\Entity\Demande_emplois;
use App\Form\Demande_emploiType;
use App\Repository\Demande_emploisRepository;
use App\Repository\PublicationsRepository;
use App\Repository\UtilisateursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/demande-emploi')]
class Demande_emploiController extends AbstractController
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

    private function isAdmin(Request $request): bool
    {
        return $request->getSession()->get('user_role') === 'Administrateur';
    }

    #[Route('/', name: 'app_demande_emploi_index', methods: ['GET'])]
    public function index(Request $request, Demande_emploisRepository $repo, PublicationsRepository $pubRepo): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $publicationId = $request->query->get('publication_id');
        if ($publicationId) {
            $publication = $pubRepo->find($publicationId);
            $demandes    = $publication ? $repo->findByPublication($publication->getPublication_id()) : [];
        } else {
            $demandes = $repo->findAll();
        }

        return $this->render('demande_emploi/index.html.twig', ['demandes' => $demandes]);
    }

    #[Route('/new', name: 'app_demande_emploi_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, PublicationsRepository $pubRepo, UtilisateursRepository $userRepo): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $isAdmin = $this->isAdmin($request);
        $userId  = $request->getSession()->get('user_id');
        $candidat = $userRepo->find($userId);

        $demande = new Demande_emplois();

        $publicationId = $request->get('publication_id') ?? ($request->isMethod('POST') ? $request->request->all('demande_emploi')['publication'] ?? null : null);
        if ($publicationId) {
            $publication = $pubRepo->find($publicationId);
            if ($publication) {
                $demande->setPublication($publication);

                // Check if this user already applied (non-admin only)
                if (!$isAdmin && $request->isMethod('GET')) {
                    $existing = $em->getRepository(Demande_emplois::class)->findOneBy([
                        'publication_id' => $publication,
                        'candidat_id'    => $candidat,
                    ]);
                    if ($existing) {
                        $this->addFlash('error', 'Vous avez déjà postulé à cette offre.');
                        return $this->redirectToRoute('app_publication_show', ['publication_id' => $publication->getPublication_id()]);
                    }
                }
            }
        }

        // Auto-set candidat for non-admin users
        if (!$isAdmin) {
            $demande->setCandidat($candidat);
        }

        $form = $this->createForm(Demande_emploiType::class, $demande, [
            'is_admin'   => $isAdmin,
            'require_cv' => true,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            if ($form->isValid()) {
                try {
                    $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/cvs';
                    $newFilename = $this->handleFileUpload($form, $uploadDir);

                    if ($newFilename) {
                        $demande->setCvUrl($newFilename);
                    }

                    // Fallback to ensure publication is set (if disabled field omitted it)
                    if (!$demande->getPublication() && $publicationId) {
                        $pub = $pubRepo->find($publicationId);
                        if ($pub) $demande->setPublication($pub);
                    }

                    // Ensure candidat is set for non-admins
                    if (!$isAdmin) {
                        $demande->setCandidat($candidat);
                        $demande->setStatutDemande('En attente');
                        $demande->setDateDemande(new \DateTime());
                    }

                    // CRITICAL: Final check before flush
                    if (!$demande->getPublication()) {
                        throw new \Exception("L'offre d'emploi est manquante. Recommencez depuis l'annonce.");
                    }

                    $em->persist($demande);
                    $em->flush();

                    $this->addFlash('success', 'Votre candidature a été envoyée avec succès !');

                    $pub = $demande->getPublication();
                    if ($pub && !$isAdmin) {
                        return $this->redirectToRoute('app_candidat_mes_candidatures');
                    }
                    if ($pub && $isAdmin) {
                        return $this->redirectToRoute('app_publication_show', ['publication_id' => $pub->getPublication_id()]);
                    }
                    return $this->redirectToRoute('app_demande_emploi_index');
                } catch (\Exception $e) {
                    $this->addFlash('error', "Erreur lors de l'enregistrement : " . $e->getMessage());
                    $this->logger->error($e->getMessage());
                }
            } else {
                foreach ($form->getErrors(true) as $error) {
                    $this->addFlash('error', $error->getMessage());
                }
            }
        }

        return $this->render('demande_emploi/new.html.twig', [
            'demande'  => $demande,
            'form'     => $form->createView(),
            'is_admin' => $isAdmin,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_demande_emploi_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, Demande_emploisRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $isAdmin = $this->isAdmin($request);
        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        // Non-admin users can only edit their own applications
        if (!$isAdmin) {
            $userId = $request->getSession()->get('user_id');
            if ($demande->getCandidat() && $demande->getCandidat()->getId() !== $userId) {
                throw $this->createAccessDeniedException();
            }

            $statut = $demande->getStatutDemande();
            if ($statut && $statut !== 'En attente') {
                $this->addFlash('error', 'Cette candidature n\'est plus modifiable (statut : ' . $statut . ').');
                return $this->redirectToRoute('app_candidat_mes_candidatures');
            }
        }

        $form = $this->createForm(Demande_emploiType::class, $demande, [
            'is_admin'   => $isAdmin,
            'require_cv' => false,  // CV is optional on edit (keep existing one)
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadDir = $this->getParameter('kernel.project_dir') . '/public/uploads/cvs';
            $newFilename = $this->handleFileUpload($form, $uploadDir);

            if ($newFilename) {
                $demande->setCvUrl($newFilename);
            }

            $em->flush();
            $this->addFlash('success', 'La candidature a été modifiée !');

            if (!$isAdmin) {
                return $this->redirectToRoute('app_candidat_mes_candidatures');
            }

            $pub = $demande->getPublication();
            if ($pub) {
                return $this->redirectToRoute('app_publication_show', ['publication_id' => $pub->getPublication_id()]);
            }
            return $this->redirectToRoute('app_demande_emploi_index');
        }

        return $this->render('demande_emploi/edit.html.twig', [
            'demande'  => $demande,
            'form'     => $form->createView(),
            'is_admin' => $isAdmin,
        ]);
    }

    private function handleFileUpload(FormInterface $form, string $uploadDir): ?string
    {
        /** @var UploadedFile|null $file */
        $file = $form->get('cvFile')->getData();

        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $newFilename = uniqid() . '.' . $file->guessExtension();

        try {
            $file->move($uploadDir, $newFilename);
        } catch (\Exception $e) {
            return null;
        }

        return $newFilename;
    }

    #[Route('/{id}', name: 'app_demande_emploi_show', methods: ['GET'])]
    public function show(int $id, Demande_emploisRepository $repo, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        return $this->render('demande_emploi/show.html.twig', ['demande_emploi' => $demande]);
    }

    #[Route('/{id}/cancel', name: 'app_demande_emploi_cancel', methods: ['POST'])]
    public function cancel(int $id, Request $request, Demande_emploisRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        // Only the owner can cancel, and only if status is "En attente"
        $userId = $request->getSession()->get('user_id');
        if ($demande->getCandidat() && $demande->getCandidat()->getId() !== $userId) {
            throw $this->createAccessDeniedException();
        }

        if ($demande->getStatutDemande() !== 'En attente') {
            $this->addFlash('error', 'Vous ne pouvez annuler qu\'une candidature en attente.');
            return $this->redirectToRoute('app_candidat_mes_candidatures');
        }

        if ($this->isCsrfTokenValid('cancel' . $demande->getDemande_id(), $request->request->get('_token'))) {
            $em->remove($demande);
            $em->flush();
            $this->addFlash('success', 'Votre candidature a été annulée.');
        }

        return $this->redirectToRoute('app_candidat_mes_candidatures');
    }

    #[Route('/{id}/delete', name: 'app_demande_emploi_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, Demande_emploisRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        if ($this->isCsrfTokenValid('delete' . $demande->getDemande_id(), $request->request->get('_token'))) {
            $em->remove($demande);
            $em->flush();
            $this->addFlash('success', 'Candidature supprimée !');
        }
        return $this->redirectToRoute('app_demande_emploi_index');
    }
}
