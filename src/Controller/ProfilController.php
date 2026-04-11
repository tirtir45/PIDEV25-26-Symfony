<?php

namespace App\Controller;

use App\Repository\UtilisateursRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ProfilController extends AbstractController
{
    #[Route('/profil', name: 'app_profile', methods: ['GET', 'POST'])]
    public function index(Request $request, UtilisateursRepository $repo, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $user   = $repo->find($userId);
        $errors = [];

        if ($request->isMethod('POST')) {
            $action = $request->request->get('action');

            if ($action === 'delete') {
                $reclamations = $em->getRepository(\App\Entity\Reclamations::class)->findBy(['utilisateur' => $user]);
                foreach ($reclamations as $r) {
                    $em->remove($r);
                }
                $em->flush();
                $em->remove($user);
                $em->flush();
                $request->getSession()->clear();
                return $this->redirectToRoute('app_login');
            }

            $nom         = trim((string) $request->request->get('nom', ''));
            $email       = trim((string) $request->request->get('email', ''));
            $telephone   = trim((string) $request->request->get('telephone', ''));
            $bio         = trim((string) $request->request->get('bio', ''));
            $competences = trim((string) $request->request->get('competences', ''));

            // Validation nom
            if (empty($nom)) {
                $errors['nom'] = 'Le nom est obligatoire.';
            } elseif (strlen($nom) < 2) {
                $errors['nom'] = 'Le nom doit contenir au moins 2 caractères.';
            } elseif (strlen($nom) > 100) {
                $errors['nom'] = 'Le nom ne peut pas dépasser 100 caractères.';
            } elseif (!preg_match('/^[a-zA-ZÀ-ÿ\s\-]+$/', $nom)) {
                $errors['nom'] = 'Le nom ne peut contenir que des lettres et des espaces.';
            }

            // Validation email
            if (empty($email)) {
                $errors['email'] = 'L\'email est obligatoire.';
            } elseif (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
                $errors['email'] = 'L\'adresse email n\'est pas valide.';
            } elseif (strlen($email) > 150) {
                $errors['email'] = 'L\'email ne peut pas dépasser 150 caractères.';
            } else {
                $existing = $repo->findOneBy(['email' => $email]);
                if ($existing && $existing->getId() !== $user->getId()) {
                    $errors['email'] = 'Cet email est déjà utilisé.';
                }
            }

            // Validation téléphone
            if (!empty($telephone) && !preg_match('/^[0-9\+\-\s\(\)]{8,20}$/', $telephone)) {
                $errors['telephone'] = 'Le numéro de téléphone n\'est pas valide (8 à 20 chiffres).';
            }

            // Validation bio
            if (!empty($bio) && strlen($bio) < 10) {
                $errors['bio'] = 'La bio doit contenir au moins 10 caractères.';
            } elseif (!empty($bio) && strlen($bio) > 1000) {
                $errors['bio'] = 'La bio ne peut pas dépasser 1000 caractères.';
            }

            // Validation compétences
            if (!empty($competences) && strlen($competences) < 3) {
                $errors['competences'] = 'Les compétences doivent contenir au moins 3 caractères.';
            } elseif (!empty($competences) && strlen($competences) > 1000) {
                $errors['competences'] = 'Les compétences ne peuvent pas dépasser 1000 caractères.';
            }

            if (empty($errors)) {
                $user->setNom($nom);
                $user->setEmail($email);
                $user->setTelephone($telephone ?: null);
                $user->setBio($bio ?: null);
                $user->setCompetences($competences ?: null);

                $photoFile = $request->files->get('photo');
                if ($photoFile) {
                    $uploadDir = __DIR__ . '/../../public/uploads/photos';
                    if (!is_dir($uploadDir)) {
                        mkdir($uploadDir, 0777, true);
                    }
                    $filename = uniqid() . '.' . $photoFile->getClientOriginalExtension();
                    $photoFile->move($uploadDir, $filename);
                    $user->setPhoto('uploads/photos/' . $filename);
                }

                $em->flush();
                $request->getSession()->set('user_nom', $nom);
                $request->getSession()->set('user_email', $email);
                $this->addFlash('success', 'Profil mis à jour avec succès.');
                return $this->redirectToRoute('app_profile');
            }
        }

        return $this->render('profil/index.html.twig', [
            'user'   => $user,
            'errors' => $errors,
        ]);
    }
}