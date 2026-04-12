<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\RegisterType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Authentication\AuthenticationUtils;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityController extends AbstractController
{
    #[Route('/', name: 'app_home')]
    public function home(): Response
    {
        if (!$this->getUser()) {
            return $this->redirectToRoute('app_login');
        }
        if ($this->isGranted('ROLE_ADMIN')) {
            return $this->redirectToRoute('app_admin_publication_index');
        }
        return $this->redirectToRoute('app_user_publication_index');
    }

    #[Route('/login', name: 'app_login')]
    public function login(AuthenticationUtils $authenticationUtils): Response
    {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        return $this->render('security/login.html.twig', [
            'last_username' => $authenticationUtils->getLastUsername(),
            'error'         => $authenticationUtils->getLastAuthenticationError(),
        ]);
    }

    #[Route('/logout', name: 'app_logout')]
    public function logout(): void
    {
        // Handled by Symfony security firewall — this method is never actually called
        throw new \LogicException('This method should not be called directly.');
    }

    #[Route('/register', name: 'app_register')]
    public function register(
        Request $request,
        EntityManagerInterface $em,
        UtilisateurRepository $repo,
        UserPasswordHasherInterface $hasher
    ): Response {
        if ($this->getUser()) {
            return $this->redirectToRoute('app_home');
        }

        $utilisateur = new Utilisateur();
        $form = $this->createForm(RegisterType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Check email uniqueness
            if ($repo->findByEmail($utilisateur->getEmail())) {
                $this->addFlash('error', 'Cet email est déjà utilisé.');
                return $this->render('security/register.html.twig', ['form' => $form->createView()]);
            }

            // Hash the password
            $hashedPassword = $hasher->hashPassword($utilisateur, $utilisateur->getMotDePasse());
            $utilisateur->setMotDePasse($hashedPassword);

            // Role défini par le formulaire (Entrepreneur ou Candidat)
            if (!$utilisateur->getIdRole()) {
                $utilisateur->setIdRole(Utilisateur::ROLE_ENTREPRENEUR_ID);
            }
            $utilisateur->setActif(true);
            $utilisateur->setDateInscription(new \DateTime());

            $em->persist($utilisateur);
            $em->flush();

            $this->addFlash('success', 'Compte créé ! Vous pouvez maintenant vous connecter.');
            return $this->redirectToRoute('app_login');
        }

        return $this->render('security/register.html.twig', [
            'form' => $form->createView(),
        ]);
    }
}