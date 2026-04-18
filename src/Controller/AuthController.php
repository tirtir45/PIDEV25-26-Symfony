<?php

namespace App\Controller;

use App\Repository\UtilisateursRepository;
use App\Repository\RolesRepository;
use App\Service\CaptchaService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class AuthController extends AbstractController
{
    #[Route('/', name: 'app_login', methods: ['GET', 'POST'])]
    public function login(Request $request, UtilisateursRepository $repo, EntityManagerInterface $em, CaptchaService $captcha): Response
    {
        if ($request->getSession()->get('user_id')) {
            return $this->redirectByRole($request->getSession()->get('user_role'));
        }

        $error = null;

        if ($request->isMethod('POST')) {
            // Vérification CAPTCHA
            $captchaToken = $request->request->get('h-captcha-response', '');
            if (!$captcha->verify($captchaToken)) {
                $error = 'Veuillez valider le CAPTCHA.';
                return $this->render('auth/login.html.twig', ['error' => $error, 'captchaSiteKey' => $captcha->getSiteKey()]);
            }

            $email    = trim((string) $request->request->get('email', ''));
            $password = (string) $request->request->get('password', '');

            if (empty($email) || empty($password)) {
                $error = 'Veuillez remplir tous les champs.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'Adresse email invalide.';
            } else {
                $user = $repo->findOneBy(['email' => $email]);

                if (!$user) {
                    $error = 'Email ou mot de passe incorrect.';
                } else {
                    $storedPassword = $user->getMotDePasse();
                    $passwordValid  = password_verify($password, $storedPassword) || $storedPassword === $password;

                    if ($passwordValid) {
                        if ($storedPassword === $password) {
                            $user->setMotDePasse(password_hash($password, PASSWORD_BCRYPT));
                            $em->flush();
                        }
                        $request->getSession()->set('user_id',    $user->getId());
                        $request->getSession()->set('user_nom',   $user->getNom());
                        $request->getSession()->set('user_email', $user->getEmail());
                        $request->getSession()->set('user_role',  $user->getRole() ? $user->getRole()->getNomRole() : 'Utilisateur');
                        $request->getSession()->set('user_photo', $user->getPhoto());
                        return $this->redirectByRole($request->getSession()->get('user_role'));
                    }

                    $error = 'Email ou mot de passe incorrect.';
                }
            }
        }

        return $this->render('auth/login.html.twig', ['error' => $error, 'captchaSiteKey' => $captcha->getSiteKey()]);
    }

    #[Route('/inscription', name: 'app_register', methods: ['GET', 'POST'])]
    public function register(Request $request, UtilisateursRepository $repo, EntityManagerInterface $em, RolesRepository $roleRepo, CaptchaService $captcha): Response
    {
        $roles = $roleRepo->findAll();
        $error = null;

        if ($request->isMethod('POST')) {
            // Vérification CAPTCHA
            $captchaToken = $request->request->get('h-captcha-response', '');
            if (!$captcha->verify($captchaToken)) {
                $error = 'Veuillez valider le CAPTCHA.';
                return $this->render('auth/register.html.twig', ['error' => $error, 'roles' => $roles, 'captchaSiteKey' => $captcha->getSiteKey()]);
            }
            $nom       = trim((string) $request->request->get('nom', ''));
            $email     = trim((string) $request->request->get('email', ''));
            $telephone = trim((string) $request->request->get('telephone', ''));
            $password  = (string) $request->request->get('password', '');
            $confirm   = (string) $request->request->get('confirm', '');
            $roleId    = $request->request->get('role_id');

            if (empty($nom)) {
                $error = 'Le nom est obligatoire.';
            } elseif (strlen($nom) < 2 || strlen($nom) > 100) {
                $error = 'Le nom doit contenir entre 2 et 100 caractères.';
            } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $nom)) {
                $error = 'Le nom ne peut contenir que des lettres et des espaces.';
            } elseif (empty($email)) {
                $error = 'L\'email est obligatoire.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $error = 'L\'adresse email n\'est pas valide.';
            } elseif (empty($password)) {
                $error = 'Le mot de passe est obligatoire.';
            } elseif (strlen($password) < 6) {
                $error = 'Le mot de passe doit contenir au moins 6 caractères.';
            } elseif (!preg_match('/[A-Z]/', $password)) {
                $error = 'Le mot de passe doit contenir au moins une lettre majuscule.';
            } elseif (!preg_match('/[a-z]/', $password)) {
                $error = 'Le mot de passe doit contenir au moins une lettre minuscule.';
            } elseif (!preg_match('/[0-9]/', $password)) {
                $error = 'Le mot de passe doit contenir au moins un chiffre.';
            } elseif (empty($roleId)) {
                $error = 'Veuillez choisir un rôle.';
            } elseif ($password !== $confirm) {
                $error = 'Les mots de passe ne correspondent pas.';
            } elseif (!empty($telephone) && !preg_match('/^[0-9\+\-\s\(\)]{8,20}$/', $telephone)) {
                $error = 'Le numéro de téléphone n\'est pas valide (8 à 20 chiffres).';
            } elseif ($repo->findOneBy(['email' => $email])) {
                $error = 'Cet email est déjà utilisé.';
            } else {
                try {
                    $user = new \App\Entity\Utilisateurs();
                    $user->setNom($nom);
                    $user->setEmail($email);
                    $user->setMotDePasse(password_hash($password, PASSWORD_BCRYPT));
                    $user->setTelephone($telephone ?: null);
                    $user->setDateInscription(new \DateTime());

                    $role = $roleRepo->find($roleId);
                    if ($role) {
                        $user->setRole($role);
                    }

                    $em->persist($user);
                    $em->flush();

                    $request->getSession()->set('user_id',    $user->getId());
                    $request->getSession()->set('user_nom',   $user->getNom());
                    $request->getSession()->set('user_email', $user->getEmail());
                    $request->getSession()->set('user_role',  $user->getRole() ? $user->getRole()->getNomRole() : 'Utilisateur');

                    $this->addFlash('success', 'Compte créé avec succès. Bienvenue !');
                    return $this->redirectByRole($request->getSession()->get('user_role'));
                } catch (\Exception $e) {
                    $error = 'Erreur lors de la création : ' . $e->getMessage();
                }
            }
        }

        return $this->render('auth/register.html.twig', ['error' => $error, 'roles' => $roles, 'captchaSiteKey' => $captcha->getSiteKey()]);
    }

    #[Route('/dashboard', name: 'app_dashboard')]
    public function dashboard(Request $request, UtilisateursRepository $repo, \App\Repository\ReclamationsRepository $reclamRepo): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }
        
        // Always redirect to specific dashboard if it's a known role
        return $this->redirectByRole($request->getSession()->get('user_role'));
    }

    private function redirectByRole(?string $role): Response
    {
        return match ($role) {
            'Administrateur' => $this->redirectToRoute('admin_dashboard'),
            'Entrepreneur'   => $this->redirectToRoute('entrepreneur_dashboard'),
            'Fournisseur'    => $this->redirectToRoute('app_fournisseur_ressources'),
            default          => $this->render('dashboard/index.html.twig', [
                'isAdmin' => false,
                'stats'   => [ 'users' => 0, 'reclamations' => 0, 'pending' => 0, 'resolved' => 0 ], // Dummy stats if role is unknown
            ]),
        };
    }



    #[Route('/deconnexion', name: 'app_logout')]
    public function logout(Request $request): Response
    {
        $request->getSession()->clear();
        return $this->redirectToRoute('app_login');
    }
}