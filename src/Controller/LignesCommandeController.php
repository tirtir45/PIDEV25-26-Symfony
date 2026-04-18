<?php

namespace App\Controller;

use App\Entity\Lignes_commande;
use App\Form\Lignes_commandeType;
use App\Repository\Lignes_commandeRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/lignes/commande')]
final class LignesCommandeController extends AbstractController
{
    #[Route(name: 'app_lignes_commande_index', methods: ['GET'])]
    public function index(Lignes_commandeRepository $lignesCommandeRepository): Response
    {
        return $this->render('lignes_commande/index.html.twig', [
            'lignes_commandes' => $lignesCommandeRepository->findAll(),
        ]);
    }

    #[Route('/new', name: 'app_lignes_commande_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $lignesCommande = new Lignes_commande();
        $form = $this->createForm(Lignes_commandeType::class, $lignesCommande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($lignesCommande);
            $entityManager->flush();

            return $this->redirectToRoute('app_lignes_commande_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lignes_commande/new.html.twig', [
            'lignes_commande' => $lignesCommande,
            'form' => $form,
        ]);
    }

    #[Route('/{idLigne}', name: 'app_lignes_commande_show', methods: ['GET'])]
    public function show(Lignes_commande $lignesCommande): Response
    {
        return $this->render('lignes_commande/show.html.twig', [
            'lignes_commande' => $lignesCommande,
        ]);
    }

    #[Route('/{idLigne}/edit', name: 'app_lignes_commande_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, Lignes_commande $lignesCommande, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(Lignes_commandeType::class, $lignesCommande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_lignes_commande_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('lignes_commande/edit.html.twig', [
            'lignes_commande' => $lignesCommande,
            'form' => $form,
        ]);
    }

    #[Route('/{idLigne}', name: 'app_lignes_commande_delete', methods: ['POST'])]
    public function delete(Request $request, Lignes_commande $lignesCommande, EntityManagerInterface $entityManager): Response
    {
        if ($this->isCsrfTokenValid('delete'.$lignesCommande->getId_ligne(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($lignesCommande);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_lignes_commande_index', [], Response::HTTP_SEE_OTHER);
    }
}
