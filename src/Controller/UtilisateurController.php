<?php

namespace App\Controller;

use App\Entity\Utilisateur;
use App\Form\UtilisateurType;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('')]
class UtilisateurController extends AbstractController
{
    // ─── LIST avec recherche + tri ────────────────────────────────────────────
    #[Route('/utilisateurs', name: 'utilisateur_index', methods: ['GET'])]
    public function index(Request $request, UtilisateurRepository $repo): Response
    {
        $search     = $request->query->get('search', '');
        $roleFilter = $request->query->get('role_filter', '');

        // Parser sort_combined (ex: "nom_ASC")
        $sortCombined = $request->query->get('sort_combined', 'dateInscription_DESC');
        $parts  = explode('_', $sortCombined, 2);
        $sortBy = $parts[0] ?? 'dateInscription';
        $order  = $parts[1] ?? 'DESC';

        $utilisateurs = $repo->findWithSearchAndSort($search, $sortBy, $order, $roleFilter);

        $nextOrder = $order === 'ASC' ? 'DESC' : 'ASC';

        return $this->render('utilisateur/index.html.twig', [
            'utilisateurs' => $utilisateurs,
            'search'       => $search,
            'sortBy'       => $sortBy,
            'order'        => $order,
            'nextOrder'    => $nextOrder,
        ]);
    }

    // ─── CREATE ───────────────────────────────────────────────────────────────
    #[Route('/utilisateurs/nouveau', name: 'utilisateur_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em): Response
    {
        $utilisateur = new Utilisateur();
        $form = $this->createForm(UtilisateurType::class, $utilisateur);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash le mot de passe (champ non mappé)
            $plainPassword = $form->get('motDePasse')->getData();
            if ($plainPassword) {
                $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
                $utilisateur->setMotDePasse($hashedPassword);
            }
            
            $em->persist($utilisateur);
            $em->flush();

            $this->addFlash('success', 'Utilisateur créé avec succès.');
            return $this->redirectToRoute('utilisateur_index');
        }

        return $this->render('utilisateur/new.html.twig', [
            'form' => $form,
        ]);
    }

    // ─── SHOW ─────────────────────────────────────────────────────────────────
    #[Route('/utilisateurs/{id}', name: 'utilisateur_show', methods: ['GET'], requirements: ['id' => '\d+'])]
    public function show(Utilisateur $utilisateur): Response
    {
        return $this->render('utilisateur/show.html.twig', [
            'utilisateur' => $utilisateur,
        ]);
    }

    // ─── EDIT ─────────────────────────────────────────────────────────────────
    #[Route('/utilisateurs/{id}/modifier', name: 'utilisateur_edit', methods: ['GET', 'POST'], requirements: ['id' => '\d+'])]
    public function edit(Request $request, Utilisateur $utilisateur, EntityManagerInterface $em): Response
    {
        // Bloquer la modification des comptes administrateurs
        $roleNom = $utilisateur->getRole()?->getNomRole();
        $rolesAdmin = ['Administrateur', 'Admin', 'Moderateur'];
        if (in_array($roleNom, $rolesAdmin)) {
            $this->addFlash('danger', 'Vous ne pouvez pas modifier un compte administrateur.');
            return $this->redirectToRoute('utilisateur_index');
        }

        $form = $this->createForm(UtilisateurType::class, $utilisateur, ['is_edit' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Hash le mot de passe si modifié (champ non mappé)
            $plainPassword = $form->get('motDePasse')->getData();
            if ($plainPassword) {
                $hashedPassword = password_hash($plainPassword, PASSWORD_BCRYPT);
                $utilisateur->setMotDePasse($hashedPassword);
            }
            
            $em->flush();

            $this->addFlash('success', 'Utilisateur modifié avec succès.');
            return $this->redirectToRoute('utilisateur_index');
        }

        return $this->render('utilisateur/edit.html.twig', [
            'form' => $form,
            'utilisateur' => $utilisateur,
        ]);
    }

    // ─── DELETE ───────────────────────────────────────────────────────────────
    #[Route('/utilisateurs/{id}/supprimer', name: 'utilisateur_delete', methods: ['POST'], requirements: ['id' => '\d+'])]
    public function delete(Request $request, Utilisateur $utilisateur, EntityManagerInterface $em): Response
    {
        // Bloquer la suppression des comptes administrateurs
        $roleNom = $utilisateur->getRole()?->getNomRole();
        if (in_array($roleNom, ['Administrateur', 'Admin', 'Moderateur'])) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer un compte administrateur.');
            return $this->redirectToRoute('utilisateur_index');
        }

        if ($this->isCsrfTokenValid('delete' . $utilisateur->getId(), $request->request->get('_token'))) {
            // Supprimer les réclamations liées
            $reclamations = $em->getRepository(\App\Entity\Reclamation::class)->findBy(['utilisateur' => $utilisateur]);
            foreach ($reclamations as $r) {
                $em->remove($r);
            }
            $em->flush();
            $em->remove($utilisateur);
            $em->flush();
            $this->addFlash('success', 'Utilisateur supprimé avec succès.');
        } else {
            $this->addFlash('danger', 'Token CSRF invalide.');
        }

        return $this->redirectToRoute('utilisateur_index');
    }
}
