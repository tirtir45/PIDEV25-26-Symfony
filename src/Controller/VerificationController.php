<?php

namespace App\Controller;

use App\Entity\Demandes_verification;
use App\Repository\Demandes_verificationRepository;
use App\Repository\UtilisateursRepository;
use App\Service\BadgeVerificationAIService;
use App\Service\NotificationService;
use App\Service\UserVerificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class VerificationController extends AbstractController
{
    /* ── Utilisateur : soumettre une demande ── */
    #[Route('/verification/demander', name: 'verification_request', methods: ['GET', 'POST'])]
    public function request(
        Request $request,
        UtilisateursRepository $userRepo,
        Demandes_verificationRepository $demandeRepo,
        EntityManagerInterface $em,
        NotificationService $notifier,
        BadgeVerificationAIService $aiService,
        UserVerificationService $verifier
    ): Response {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) return $this->redirectToRoute('app_login');

        $user = $userRepo->find($userId);

        // Vérifier si une demande EN_ATTENTE existe déjà
        $existing = $demandeRepo->findOneBy(['id_utilisateur' => $user, 'statut' => 'EN_ATTENTE']);

        if ($request->isMethod('POST') && !$existing) {
            $raison = trim($request->request->get('raison', ''));
            if (empty($raison)) {
                $this->addFlash('error', 'Veuillez expliquer votre demande.');
                return $this->redirectToRoute('verification_request');
            }

            // Analyse IA
            $aiResult = $aiService->analyze($user, $raison);

            $d = new Demandes_verification();
            $d->setId_utilisateur($user);
            $d->setType_role($user->getRole() ? $user->getRole()->getNomRole() : 'Utilisateur');
            $d->setRaison_demande($raison);
            $d->setDocument_justificatif('');
            $d->setDate_demande(new \DateTime());
            $d->setDate_traitement(new \DateTime());
            $d->setId_admin_traitant($user);

            // Décision automatique selon le score IA
            if ($aiResult['decision'] === 'ACCEPT') {
                $d->setStatut('ACCEPTEE');
                $d->setCommentaire_admin("Score IA : {$aiResult['score']}/100 — {$aiResult['explication']}");
                $verifier->checkAndUpdate($user);
                // Badge accordé directement
                $user->setBadgeVerifie(true);
                $user->setDateVerification(new \DateTime());
                $notifier->save($user, 'success', '✔️ Badge Vérifié accordé automatiquement',
                    "Score IA : {$aiResult['score']}/100. {$aiResult['explication']}");
                $this->addFlash('success', "✔️ Félicitations ! Votre badge a été accordé automatiquement (score : {$aiResult['score']}/100).");
            } elseif ($aiResult['decision'] === 'REJECT') {
                $d->setStatut('REJETEE');
                $d->setCommentaire_admin("Score IA : {$aiResult['score']}/100 — {$aiResult['explication']}");
                $notifier->save($user, 'error', 'Demande de badge refusée',
                    "Score IA : {$aiResult['score']}/100. {$aiResult['explication']}");
                $this->addFlash('error', "❌ Demande refusée (score : {$aiResult['score']}/100). {$aiResult['explication']}");
            } else {
                // REVIEW → en attente admin
                $d->setStatut('EN_ATTENTE');
                $d->setCommentaire_admin("Score IA : {$aiResult['score']}/100 — En attente de validation admin.");
                $this->addFlash('success', "⏳ Demande soumise (score : {$aiResult['score']}/100). Validation de l'administrateur requise.");
            }

            $em->persist($d);
            $em->flush();

            // Notifier tous les admins
            foreach ($userRepo->createQueryBuilder('u')
                ->join('u.role', 'r')
                ->where('r.nomRole = :role')
                ->setParameter('role', 'Administrateur')
                ->getQuery()->getResult() as $admin) {
                $notifier->save($admin, 'warning',
                    '🔔 Nouvelle demande de badge',
                    "{$user->getNom()} a soumis une demande de badge vérifié.",
                    '/admin/verifications'
                );
            }

            $this->addFlash('success', 'Votre demande de vérification a été soumise. L\'administrateur vous répondra bientôt.');
            return $this->redirectToRoute('app_profile');
        }

        return $this->render('verification/request.html.twig', [
            'user'     => $user,
            'existing' => $existing,
        ]);
    }

    /* ── Admin : liste des demandes ── */
    #[Route('/admin/verifications', name: 'admin_verification_index', methods: ['GET'])]
    public function adminIndex(Request $request, Demandes_verificationRepository $repo): Response
    {
        if ($request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $demandes = $repo->findBy([], ['date_demande' => 'DESC']);

        return $this->render('verification/admin_index.html.twig', [
            'demandes' => $demandes,
        ]);
    }

    /* ── Admin : accepter ── */
    #[Route('/admin/verifications/{id}/accepter', name: 'admin_verification_accept', methods: ['POST'])]
    public function accept(
        int $id,
        Request $request,
        Demandes_verificationRepository $repo,
        UtilisateursRepository $userRepo,
        EntityManagerInterface $em,
        UserVerificationService $verifier,
        NotificationService $notifier
    ): Response {
        if ($request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $demande = $repo->find($id);
        if ($demande) {
            $adminId = $request->getSession()->get('user_id');
            $admin   = $userRepo->find($adminId);

            $demande->setStatut('ACCEPTEE');
            $demande->setDate_traitement(new \DateTime());
            $demande->setId_admin_traitant($admin);
            $demande->setCommentaire_admin($request->request->get('commentaire', 'Demande acceptée.'));

            // Attribuer le badge
            $user = $demande->getId_utilisateur();
            $user->setBadgeVerifie(true);
            $user->setDateVerification(new \DateTime());

            $em->flush();

            // Notification
            $notifier->save($user, 'success',
                '✔️ Badge Vérifié obtenu',
                'Félicitations ! Votre demande de vérification a été acceptée. Le badge ✔️ est maintenant visible sur votre profil.'
            );

            $this->addFlash('success', "Badge accordé à {$user->getNom()}.");
        }

        return $this->redirectToRoute('admin_verification_index');
    }

    /* ── Admin : rejeter ── */
    #[Route('/admin/verifications/{id}/rejeter', name: 'admin_verification_reject', methods: ['POST'])]
    public function reject(
        int $id,
        Request $request,
        Demandes_verificationRepository $repo,
        UtilisateursRepository $userRepo,
        EntityManagerInterface $em,
        NotificationService $notifier
    ): Response {
        if ($request->getSession()->get('user_role') !== 'Administrateur') {
            return $this->redirectToRoute('app_login');
        }

        $demande = $repo->find($id);
        if ($demande) {
            $adminId = $request->getSession()->get('user_id');
            $admin   = $userRepo->find($adminId);

            $demande->setStatut('REJETEE');
            $demande->setDate_traitement(new \DateTime());
            $demande->setId_admin_traitant($admin);
            $demande->setCommentaire_admin($request->request->get('commentaire', 'Demande rejetée.'));

            $em->flush();

            $user = $demande->getId_utilisateur();
            $notifier->save($user, 'error',
                'Demande de vérification rejetée',
                'Votre demande de badge vérifié a été rejetée. Contactez l\'administrateur pour plus d\'informations.'
            );

            $this->addFlash('success', "Demande rejetée.");
        }

        return $this->redirectToRoute('admin_verification_index');
    }
}
