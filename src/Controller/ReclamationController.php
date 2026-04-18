<?php

namespace App\Controller;

use App\Entity\Reclamations;
use App\Entity\Reclamation_commentaires;
use App\Repository\ReclamationsRepository;
use App\Repository\UtilisateursRepository;
use App\Service\ReclamationAnalyzerService;
use App\Service\TranslationService;
use App\Service\ChatbotService;
use App\Service\NotificationService;
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
        TranslationService $translator,
        ChatbotService $chatbot,
        NotificationService $notifier
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

            if (empty($sujet) || empty($description)) {
                $error    = 'Veuillez remplir tous les champs.';
                $showForm = true;
            } else {
                $user = $userRepo->find($userId);
                $r = new Reclamations();
                $r->setUtilisateur($user);

                // Traduction automatique du sujet et de la description en français
                $sujetResult = $translator->translate($sujet, 'fr');
                $descResult  = $translator->translate($description, 'fr');

                $r->setSujet($sujetResult['translated']);

                // Stocker l'original si la langue source n'est pas le français
                if ($descResult['sourceLang'] !== 'fr' && $descResult['translated'] !== $description) {
                    $r->setDescriptionOriginale($description);
                    $r->setLangueOriginale($descResult['sourceLang']);
                }
                $r->setDescription($descResult['translated']);

                // Analyse IA automatique (sur le texte traduit)
                $analysis = $analyzer->analyze($sujet . ' ' . $r->getDescription());
                $r->setCategorie($analysis['categorie']);
                $r->setSentiment($analysis['sentiment']);
                $r->setPriorite($analysis['priorite']);

                // Réponse automatique chatbot
                $autoReply = $chatbot->generateResponse($r->getDescription());
                $r->setAutoResponse($autoReply);

                $em->persist($r);
                $em->flush();

                // Notifications
                $notifier->onNewReclamation($r);

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

    #[Route('/reclamations/{id}', name: 'reclamation_show', methods: ['GET', 'POST'])]
    public function show(
        int $id,
        Request $request,
        ReclamationsRepository $repo,
        UtilisateursRepository $userRepo,
        EntityManagerInterface $em
    ): Response {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $reclamation = $repo->find($id);
        if (!$reclamation || $reclamation->getUtilisateur()->getId() !== $userId) {
            throw $this->createNotFoundException();
        }

        if ($request->isMethod('POST')) {
            $contenu = trim((string) $request->request->get('message', ''));

            if ($reclamation->getStatut() === 'RESOLU' || $reclamation->getStatut() === 'REJETE') {
                $this->addFlash('warning', 'Cette réclamation est fermée, la communication est désactivée.');
                return $this->redirectToRoute('reclamation_show', ['id' => $id]);
            }

            if (!empty($contenu)) {
                $user = $userRepo->find($userId);
                $msg = new Reclamation_commentaires();
                $msg->setId_reclamation($reclamation);
                $msg->setId_auteur($user);
                $msg->setCommentaire($contenu);
                $msg->setDate_commentaire(new \DateTime());
                $em->persist($msg);
                $em->flush();
            }

            return $this->redirectToRoute('reclamation_show', ['id' => $id]);
        }

        return $this->render('reclamation/show.html.twig', [
            'reclamation' => $reclamation,
            'messages'    => $reclamation->getReclamationCommentairess(),
            'userId'      => $userId,
        ]);
    }
}