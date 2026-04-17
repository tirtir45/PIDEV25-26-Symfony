<?php

namespace App\Controller;

use App\Entity\Reclamations;
use App\Repository\ReclamationsRepository;
use App\Repository\UtilisateursRepository;
use App\Service\ReclamationAnalyzerService;
use App\Service\TranslationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ReclamationController extends AbstractController
{
    #[Route('/reclamations', name: 'reclamation_index', methods: ['GET', 'POST'])]
    public function index(
        Request $request,
        ReclamationsRepository $repo,
        UtilisateursRepository $userRepo,
        EntityManagerInterface $em,
        ReclamationAnalyzerService $analyzer,
        TranslationService $translator
    ): Response {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $showForm = $request->query->get('new') === '1';
        $error = null;

        if ($request->isMethod('POST')) {
            $sujet       = trim((string) $request->request->get('sujet', ''));
            $description = trim((string) $request->request->get('description', ''));
            $targetLang  = 'fr';

            if (empty($sujet) || empty($description)) {
                $error    = 'Veuillez remplir tous les champs.';
                $showForm = true;
            } else {
                $user = $userRepo->find($userId);
                $r = new Reclamations();
                $r->setUtilisateur($user);
                $r->setSujet($sujet);

                // Traduction automatique
                $result = $translator->translate($description, $targetLang);
                if ($result['sourceLang'] !== $targetLang && $result['translated'] !== $description) {
                    $r->setDescriptionOriginale($description);
                    $r->setLangueOriginale($result['sourceLang']);
                    $r->setDescription($result['translated']);
                } else {
                    $r->setDescription($description);
                }

                // Analyse IA automatique (sur le texte traduit)
                $analysis = $analyzer->analyze($sujet . ' ' . $r->getDescription());
                $r->setCategorie($analysis['categorie']);
                $r->setSentiment($analysis['sentiment']);
                $r->setPriorite($analysis['priorite']);

                $em->persist($r);
                $em->flush();

                $this->addFlash('success', 'Réclamation soumise avec succès.');
                return $this->redirectToRoute('reclamation_index');
            }
        }

        $reclamations = $repo->findBy(
            ['utilisateur' => $userId],
            ['dateCreation' => 'DESC']
        );

        return $this->render('reclamation/index.html.twig', [
            'reclamations' => $reclamations,
            'showForm'     => $showForm,
            'error'        => $error,
        ]);
    }
}