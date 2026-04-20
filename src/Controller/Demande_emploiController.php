<?php

namespace App\Controller;

use App\Entity\Demande_emplois;
use App\Form\Demande_emploiType;
use App\Repository\Demande_emploisRepository;
use App\Repository\PublicationsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/demande-emploi')]
class Demande_emploiController extends AbstractController
{
    private function requireAuth(Request $request): ?Response
    {
        if (!$request->getSession()->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }
        return null;
    }

    private function requireAdmin(Request $request): ?Response
    {
        if ($r = $this->requireAuth($request)) return $r;
        if ($request->getSession()->get('user_role') !== 'Administrateur') {
            throw $this->createAccessDeniedException();
        }
        return null;
    }

    #[Route('/', name: 'app_demande_emploi_index', methods: ['GET'])]
    public function index(Request $request, Demande_emploisRepository $repo, PublicationsRepository $pubRepo): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $publicationId = $request->query->get('publication_id');
        if ($publicationId) {
            $publication = $pubRepo->find($publicationId);
            $demandes    = $publication ? $repo->findByPublication($publication->getPublication_id()) : [];
        } else {
            $demandes = $repo->findAll();
        }

        return $this->render('demande_emploi/index.html.twig', ['demandes' => $demandes]);
    }

    #[Route('/new', name: 'app_demande_emploi_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $em, PublicationsRepository $pubRepo): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $demande = new Demande_emplois();

        $publicationId = $request->query->get('publication_id');
        if ($publicationId) {
            $publication = $pubRepo->find($publicationId);
            if ($publication) {
                $demande->setPublication($publication);
            }
        }

        $form = $this->createForm(Demande_emploiType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->persist($demande);
            $em->flush();

            $this->addFlash('success', 'La candidature a été ajoutée avec succès !');

            $pub = $demande->getPublication();
            if ($pub) {
                return $this->redirectToRoute('app_publication_show', ['publication_id' => $pub->getPublication_id()]);
            }
            return $this->redirectToRoute('app_demande_emploi_index');
        }

        return $this->render('demande_emploi/new.html.twig', ['demande' => $demande, 'form' => $form->createView()]);
    }

    #[Route('/{id}/edit', name: 'app_demande_emploi_edit', methods: ['GET', 'POST'])]
    public function edit(int $id, Request $request, Demande_emploisRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        $statut = $demande->getStatutDemande();
        if ($statut && $statut !== 'En attente') {
            $this->addFlash('error', 'Cette candidature n\'est plus modifiable (statut : ' . $statut . ').');
            $pub = $demande->getPublication();
            if ($pub) {
                return $this->redirectToRoute('app_publication_show', ['publication_id' => $pub->getPublication_id()]);
            }
            return $this->redirectToRoute('app_demande_emploi_index');
        }

        $form = $this->createForm(Demande_emploiType::class, $demande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $em->flush();
            $this->addFlash('success', 'La candidature a été modifiée !');

            $pub = $demande->getPublication();
            if ($pub) {
                return $this->redirectToRoute('app_publication_show', ['publication_id' => $pub->getPublication_id()]);
            }
            return $this->redirectToRoute('app_demande_emploi_index');
        }

        return $this->render('demande_emploi/edit.html.twig', ['demande' => $demande, 'form' => $form->createView()]);
    }

    #[Route('/{id}', name: 'app_demande_emploi_show', methods: ['GET'])]
    public function show(int $id, Demande_emploisRepository $repo, Request $request): Response
    {
        if ($r = $this->requireAuth($request)) return $r;

        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        return $this->render('demande_emploi/show.html.twig', ['demande_emploi' => $demande]);
    }

    #[Route('/{id}/delete', name: 'app_demande_emploi_delete', methods: ['POST'])]
    public function delete(int $id, Request $request, Demande_emploisRepository $repo, EntityManagerInterface $em): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        if ($this->isCsrfTokenValid('delete' . $demande->getDemande_id(), $request->request->get('_token'))) {
            $em->remove($demande);
            $em->flush();
            $this->addFlash('success', 'Candidature supprimée !');
        }
        return $this->redirectToRoute('app_demande_emploi_index');
    }
}
