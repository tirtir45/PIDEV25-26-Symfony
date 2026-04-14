<?php

namespace App\Controller;

use App\Entity\Demande_emploi;
use App\Entity\Publication;
use App\Form\PublicationType;
use App\Repository\PublicationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/publication')]
final class PublicationController extends AbstractController
{
    // ===================================================================
    // == SECTION PUBLIQUE (POUR LES CANDIDATS ET VISITEURS)
    // ===================================================================

    /**
     * Affiche la liste des offres d'emploi pour les candidats (page d'accueil "carrières").
     * Cette action correspond à votre fichier simpleUser.fxml.
     */
    #[Route('/offres', name: 'app_user_publication_index', methods: ['GET'])]
    public function publicIndex(Request $request, PublicationRepository $publicationRepository): Response
    {
        // 1. Récupérer les filtres de la requête
        $recherche = $request->query->get('recherche');
        $contrat = $request->query->get('contrat');
        $localisation = $request->query->get('localisation');

        // 2. Utiliser une méthode de recherche avancée dans le repository
        // Vous devrez créer cette méthode findByFilters (voir ci-dessous)
        $publications = $publicationRepository->findByFilters($recherche, $contrat, $localisation);

        // 3. Récupérer les localisations uniques pour le menu déroulant du filtre
        $localisations = $publicationRepository->findUniqueLocalisations();

        // 4. Rendre le template pour l'utilisateur simple
        return $this->render('user/publication.html.twig', [
            'publications' => $publications,
            'localisations' => $localisations,
        ]);
    }

    /**
     * Affiche le détail d'UNE publication.
     * Cette route est partagée entre les admins et les candidats.
     */
    #[Route('/{publication_id}', name: 'app_publication_show', methods: ['GET'], requirements: ['publication_id' => '\\d+'])]
    public function show(Publication $publication): Response
    {
        return $this->render('publication/show.html.twig', [
            'publication' => $publication,
        ]);
    }

    /**
     * Permet à un candidat connecté de postuler à une offre.
     */
    #[Route('/{publication_id}/postuler', name: 'app_publication_postuler', methods: ['GET'], requirements: ['publication_id' => '\\d+'])]
    #[IsGranted('ROLE_CANDIDAT')] // Seuls les utilisateurs avec ROLE_CANDIDAT peuvent accéder
    public function postuler(Publication $publication, EntityManagerInterface $entityManager): Response
    {
        $candidat = $this->getUser(); // Récupère l'utilisateur connecté

        // Vérifier que le candidat n'a pas déjà postulé
        $demandeExistante = $entityManager->getRepository(Demande_emploi::class)->findOneBy([
            'publication' => $publication,
            'candidat' => $candidat,
        ]);

        if ($demandeExistante) {
            $this->addFlash('error', 'Vous avez déjà postulé à cette offre.');
            return $this->redirectToRoute('app_publication_show', ['publication_id' => $publication->getPublicationId()]);
        }

        // Créer la nouvelle candidature
        $demande = new Demande_emploi();
        $demande->setPublication($publication);
        $demande->setCandidat($candidat);
        
        $entityManager->persist($demande);
        $entityManager->flush();

        $this->addFlash('success', 'Votre candidature a été envoyée avec succès !');
        return $this->redirectToRoute('app_publication_show', ['publication_id' => $publication->getPublicationId()]);
    }


    // ===================================================================
    // == SECTION ADMIN (POUR LA GESTION)
    // ===================================================================

    /**
     * Affiche la liste des publications pour l'administration.
     */
    #[Route('/admin', name: 'app_admin_publication_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function adminIndex(PublicationRepository $publicationRepository): Response
    {
        $publications = $publicationRepository->findAll();

        return $this->render('publication/index.html.twig', [
            'publications' => $publications,
        ]);
    }

    /**
     * Crée une nouvelle publication.
     */
    #[Route('/admin/new', name: 'app_admin_publication_new', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $publication = new Publication();
        $form = $this->createForm(PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($publication);
            $entityManager->flush();
            $this->addFlash('success', "L'offre '" . $publication->getTitre() . "' a été ajoutée avec succès !");

            return $this->redirectToRoute('app_admin_publication_index');
        }

        return $this->render('publication/new.html.twig', [
            'publication' => $publication,
            'form' => $form->createView(), // Utiliser createView() est une meilleure pratique
        ]);
    }

    /**
     * Modifie une publication existante.
     */
    #[Route('/admin/{publication_id}/edit', name: 'app_admin_publication_edit', methods: ['GET', 'POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function edit(Request $request, Publication $publication, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(PublicationType::class, $publication);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();
            $this->addFlash('success', "L'offre a été modifiée avec succès !");

            return $this->redirectToRoute('app_admin_publication_index');
        }

        return $this->render('publication/edit.html.twig', [
            'publication' => $publication,
            'form' => $form->createView(),
        ]);
    }

    /**
     * Supprime une publication.
     */
    #[Route('/admin/{publication_id}', name: 'app_admin_publication_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Publication $publication, EntityManagerInterface $entityManager): Response
    {
        // Correction pour la récupération du token depuis un formulaire web
        if ($this->isCsrfTokenValid('delete'.$publication->getPublication_id(), $request->request->get('_token'))) {
            $entityManager->remove($publication);
            $entityManager->flush();
            $this->addFlash('success', 'La publication a été supprimée avec succès !');
        }

        return $this->redirectToRoute('app_admin_publication_index');
    }
}