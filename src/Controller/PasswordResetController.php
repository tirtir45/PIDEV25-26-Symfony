<?php

namespace App\Controller;

use App\Repository\UtilisateursRepository;
use App\Service\PasswordResetService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class PasswordResetController extends AbstractController
{
    #[Route('/mot-de-passe-oublie', name: 'password_forgot', methods: ['GET', 'POST'])]
    public function forgot(
        Request $request,
        UtilisateursRepository $repo,
        PasswordResetService $service
    ): Response {
        $error = null;

        if ($request->isMethod('POST')) {
            $email = trim($request->request->get('email', ''));

            if (empty($email) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Veuillez saisir une adresse email valide.';
            } else {
                $user = $repo->findOneBy(['email' => $email]);

                if (!$user || !$user->isActif()) {
                    $error = 'Aucun compte actif trouvé pour cet email.';
                } else {
                    $success = $service->generateOtp($user);

                    if (!$success) {
                        $error = 'Erreur : Limite de demandes atteinte ou numéro de téléphone manquant.';
                    } else {
                        $request->getSession()->set('reset_email', $email);
                        $this->addFlash('success', 'Un code de vérification a été envoyé sur votre téléphone.');
                        return $this->redirectToRoute('password_verify');
                    }
                }
            }
        }

        return $this->render('password_reset/forgot.html.twig', [
            'error' => $error,
        ]);
    }

    /* ── Étape 2 : saisir le code OTP ── */
    #[Route('/verification-code', name: 'password_verify', methods: ['GET', 'POST'])]
    public function verify(
        Request $request,
        UtilisateursRepository $repo,
        PasswordResetService $service
    ): Response {
        $session = $request->getSession();
        $email   = $session->get('reset_email');
        $error   = null;
        $ttl     = $service->getTtl();

        if (!$email) {
            return $this->redirectToRoute('password_forgot');
        }

        $user = $repo->findOneBy(['email' => $email]);
        if ($user) {
            $activeToken = $service->getActiveToken($user);
            if ($activeToken) {
                $ttl = max(0, $activeToken->getDate_expiration()->getTimestamp() - time());
            }
        }

        if ($request->isMethod('POST')) {
            $code = trim($request->request->get('code', ''));

            if (!$user) {
                $error = 'Session expiree. Recommencez.';
            } else {
                $result = $service->verifyOtp($user, $code);

                match ($result) {
                    'valid'   => $session->set('otp_verified_email', $email),
                    'expired' => $error = 'Le code a expire. Recommencez.',
                    default   => $error = 'Code incorrect.',
                };

                if ($result === 'valid') {
                    return $this->redirectToRoute('password_reset');
                }
            }
        }

        return $this->render('password_reset/verify.html.twig', [
            'error' => $error,
            'ttl'   => $ttl,
        ]);
    }

    /* ── Étape 3 : nouveau mot de passe ── */
    #[Route('/nouveau-mot-de-passe', name: 'password_reset', methods: ['GET', 'POST'])]
    public function reset(
        Request $request,
        UtilisateursRepository $repo,
        EntityManagerInterface $em
    ): Response {
        $session = $request->getSession();
        $email   = $session->get('otp_verified_email');

        if (!$email) {
            return $this->redirectToRoute('password_forgot');
        }

        $error = null;

        if ($request->isMethod('POST')) {
            $password = $request->request->get('password', '');
            $confirm  = $request->request->get('confirm', '');

            if (strlen($password) < 6) {
                $error = 'Minimum 6 caracteres.';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $error = 'Au moins une majuscule requise.';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $error = 'Au moins un chiffre requis.';
            } elseif ($password !== $confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } else {
                $user = $repo->findOneBy(['email' => $email]);
                if ($user) {
                    $user->setMotDePasse(password_hash($password, PASSWORD_BCRYPT));
                    $em->flush();
                    $session->remove('otp_verified_email');
                    $session->remove('reset_email');
                    $this->addFlash('success', 'Mot de passe modifie avec succes.');
                    return $this->redirectToRoute('app_login');
                }
            }
        }

        return $this->render('password_reset/reset.html.twig', ['error' => $error]);
    }
}
