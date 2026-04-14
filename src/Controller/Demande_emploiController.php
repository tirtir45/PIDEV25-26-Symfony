<?php

namespace App\Controller;

use App\Entity\Demande_emploi;
use App\Form\Demande_emploiType;
use App\Repository\Demande_emploiRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/demande-emploi')]
class Demande_emploiController extends AbstractController
{
    #[Route('/', name: 'app_demande_emploi_index', methods: ['GET'])]
    #[IsGranted('ROLE_ADMIN')]
    public function index(Request $request, Demande_emploiRepository $demandeEmploiRepository, EntityManagerInterface $entityManager): Response
    {
        $publicationId = $request->query->get('publication_id');
        if ($publicationId) {
            $publication = $entityManager->getRepository(\App\Entity\Publication::class)->find($publicationId);
            $demandes = $demandeEmploiRepository->findBy(['publication' => $publication]);
        } else {
            $demandes = $demandeEmploiRepository->findAll();
        }

        return $this->render('demande_emploi/index.html.twig', [
            'demandes' => $demandes,
        ]);
    }

    #[Route('/new', name: 'app_demande_emploi_new', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $demande = new Demande_emploi();

        // Pré-remplissage si publication_id est passé en query (ex: depuis show.html.twig)
        $publicationId = $request->query->get('publication_id');
        if ($publicationId) {
            $publication = $entityManager->getRepository(\App\Entity\Publication::class)->find($publicationId);
            if ($publication) {
                $demande->setPublication($publication);
            }
        }

        $form = $this->createForm(Demande_emploiType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // Ici on ne fait PAS de move() : on stocke juste le nom du fichier tel que saisi
            $entityManager->persist($demande);
            $entityManager->flush();

            $this->addFlash('success', 'La candidature a été ajoutée avec succès !');

            // Redirige vers la publication concernée (si connue), sinon vers la liste
            $pub = $demande->getPublication();
            if ($pub) {
                return $this->redirectToRoute('app_publication_show', [
                    'publication_id' => $pub->getPublication_id(),
                ]);
            }
            return $this->redirectToRoute('app_demande_emploi_index');
        }

        return $this->render('demande_emploi/new.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}/edit', name: 'app_demande_emploi_edit', methods: ['GET', 'POST'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function edit(Request $request, Demande_emploi $demande, EntityManagerInterface $entityManager): Response
    {
        // Sécurité métier : on ne permet d'éditer que si le statut est "En attente" ou NULL/vide
        $statut = $demande->getStatutDemande();
        if ($statut && $statut !== 'En attente') {
            $this->addFlash('error', 'Cette candidature n\'est plus modifiable (statut : ' . $statut . ').');
            return $this->redirectToRoute('app_publication_show', [
                'publication_id' => $demande->getPublication()->getPublication_id(),
            ]);
        }

        $form = $this->createForm(Demande_emploiType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            // On stocke le nom du fichier tel que saisi (pas de move() ici)
            $entityManager->flush();

            $this->addFlash('success', 'La candidature a été modifiée !');

            $pub = $demande->getPublication();
            if ($pub) {
                return $this->redirectToRoute('app_publication_show', [
                    'publication_id' => $pub->getPublication_id(),
                ]);
            }
            return $this->redirectToRoute('app_demande_emploi_index');
        }

        return $this->render('demande_emploi/edit.html.twig', [
            'demande' => $demande,
            'form' => $form->createView(),
        ]);
    }

    #[Route('/{id}', name: 'app_demande_emploi_show', methods: ['GET'])]
    #[IsGranted('IS_AUTHENTICATED_FULLY')]
    public function show(Demande_emploi $demande): Response
    {
        return $this->render('demande_emploi/show.html.twig', [
            'demande_emploi' => $demande,
        ]);
    }

    #[Route('/{id}', name: 'app_demande_emploi_delete', methods: ['POST'])]
    #[IsGranted('ROLE_ADMIN')]
    public function delete(Request $request, Demande_emploi $demande, EntityManagerInterface $entityManager): Response
    {
        $id = $demande->getDemande_id();
        if ($this->isCsrfTokenValid('delete'.$id, $request->request->get('_token'))) {
            $entityManager->remove($demande);
            $entityManager->flush();
            $this->addFlash('success', 'Candidature supprimée !');
        }
        return $this->redirectToRoute('app_demande_emploi_index');
    }
}