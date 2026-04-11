<?php

namespace App\Controller;

use App\Entity\Commandes;
use App\Entity\Utilisateurs;
use App\Form\CommandesType;
use App\Repository\CommandesRepository;
use App\Repository\Lignes_commandeRepository;
use App\Repository\RessourcesRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use App\Entity\Ressources;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/commandes')]
final class CommandesController extends AbstractController
{
    #[Route(name: 'app_commandes_index', methods: ['GET'])]
    public function index(Request $request, CommandesRepository $commandesRepository, Lignes_commandeRepository $lignesCommandeRepository, RessourcesRepository $ressourcesRepository, EntityManagerInterface $em): Response
    {
        $userId = $request->getSession()->get('user_id');
        $user = $userId ? $em->getRepository(Utilisateurs::class)->find($userId) : null;
        
        $request->getSession()->set('nav_ctx', 'entrepreneur');
        $visibleStatuses = ['SUBMITTED', 'COMPLETED'];

        $qb = $commandesRepository->createQueryBuilder('c')
            ->andWhere('UPPER(c.statut) IN (:statuses)')
            ->setParameter('statuses', $visibleStatuses)
            ->orderBy('c.date_creation', 'DESC');

        if ($user instanceof Utilisateurs) {
            $qb
                ->andWhere('c.id_entrepreneur = :entrepreneurId')
                ->setParameter('entrepreneurId', (int) $user->getId());
        }

        $commandes = $qb->getQuery()->getResult();

        $orderNumbers = $this->buildOrderNumbers($commandes);
        $orders = [];
        foreach ($commandes as $commande) {
            try {
                $lines = $lignesCommandeRepository->findBy(['id_commande' => $commande->getIdCommande()]);
                $resourceNames = $this->buildRessourceNamesMap($lines, $ressourcesRepository);
                $orders[] = [
                    'commande' => $commande,
                    'order_number' => $orderNumbers[(int) $commande->getIdCommande()] ?? null,
                    'lines' => $lines,
                    'resource_names' => $resourceNames,
                    'total' => array_reduce($lines, static function (float $carry, $line): float {
                         try {
                            return $carry + (float) $line->getPrix_ligne();
                         } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                            return $carry;
                         }
                    }, 0.0),
                ];
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                // If the order or any required relation is missing, skip it
                continue;
            }
        }

        return $this->render('commandes/index.html.twig', [
            'orders' => $orders,
        ]);
    }

    #[Route('/new', name: 'app_commandes_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager): Response
    {
        $commande = new Commandes();
        $form = $this->createForm(CommandesType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->persist($commande);
            $entityManager->flush();

            return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commandes/new.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    #[Route('/fournisseur/ventes', name: 'app_fournisseur_ventes', methods: ['GET'])]
    public function fournisseurVentes(Request $request, Lignes_commandeRepository $lignesCommandeRepository, CommandesRepository $commandesRepository, RessourcesRepository $ressourcesRepository, EntityManagerInterface $em): Response
    {
        $request->getSession()->set('nav_ctx', 'fournisseur');
        $isFournisseur = ($request->getSession()->get('user_role') === 'Fournisseur');

        $userId = $request->getSession()->get('user_id');
        $user = $userId ? $em->getRepository(Utilisateurs::class)->find($userId) : null;
        $fournisseurId = $user instanceof Utilisateurs ? (int) $user->getId() : null;
        $statusFilter = mb_strtoupper(trim((string) $request->query->get('status', 'ALL')));
        if (!in_array($statusFilter, ['ALL', 'SUBMITTED', 'COMPLETED', 'CANCELLED'], true)) {
            $statusFilter = 'ALL';
        }

        $search = mb_strtolower(trim((string) $request->query->get('q', '')));

        $resourceCache = [];
        $commandeCache = [];
        $sales = [];
        $revenueTotal = 0.0;
        $soldUnits = 0;
        $submittedCount = 0;
        $completedCount = 0;
        $cancelledCount = 0;

        foreach ($lignesCommandeRepository->findAll() as $line) {
            try {
                $resource = $line->getId_ressource();
                if (!$resource instanceof Ressources) {
                    continue;
                }
                // Check if resource exists in database
                $resourceId = (int) $resource->getId_ressource();
                $owner = $resource->getId_fournisseur();
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                continue;
            }
            if (!array_key_exists($resourceId, $resourceCache)) {
                $resourceCache[$resourceId] = $resource;
            }

            $ressource = $resourceCache[$resourceId];
            if ($ressource === null) {
                continue;
            }

            $owner = $ressource->getId_fournisseur();
            $ownerId = $owner instanceof Utilisateurs ? (int) $owner->getId() : (int) $owner;
            if ($isFournisseur && $fournisseurId !== null && $ownerId !== $fournisseurId) {
                continue;
            }

            try {
                $commande = $line->getId_commande();
                if (!$commande instanceof Commandes) {
                    continue;
                }
                $commandeId = (int) $commande->getIdCommande();
                if (!array_key_exists($commandeId, $commandeCache)) {
                    $commandeCache[$commandeId] = $commande;
                }
     
                $commande = $commandeCache[$commandeId];
                if (!$commande instanceof Commandes) {
                    continue;
                }
    
                $status = mb_strtoupper(trim((string) $commande->getStatut()));
            } catch (\Doctrine\ORM\EntityNotFoundException $e) {
                continue;
            }
 
            if (!in_array($status, ['SUBMITTED', 'COMPLETED', 'CANCELLED'], true)) {
                continue;
            }

            if ($statusFilter !== 'ALL' && $status !== $statusFilter) {
                continue;
            }

            $resourceName = trim((string) $ressource->getNom());
            if ($search !== '' && !str_contains(mb_strtolower($resourceName), $search)) {
                continue;
            }

            if ($status === 'SUBMITTED') {
                ++$submittedCount;
            }

            if ($status === 'COMPLETED') {
                ++$completedCount;
            }

            if ($status === 'CANCELLED') {
                ++$cancelledCount;
            }

            $quantity = max(1, (int) $line->getQuantite());
            $lineTotal = (float) $line->getPrix_ligne();
            $soldUnits += $quantity;
            $revenueTotal += $lineTotal;

            $sales[] = [
                'commande' => $commande,
                'line' => $line,
                'ressource' => $ressource,
                'resource_name' => $resourceName !== '' ? $resourceName : ('Ressource #'.$resourceId),
                'status' => $status,
                'quantity' => $quantity,
                'line_total' => $lineTotal,
            ];
        }

        usort($sales, static function (array $left, array $right): int {
            /** @var Commandes $leftCommande */
            $leftCommande = $left['commande'];
            /** @var Commandes $rightCommande */
            $rightCommande = $right['commande'];

            $dateCompare = $rightCommande->getDate_creation() <=> $leftCommande->getDate_creation();
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return ((int) $rightCommande->getIdCommande()) <=> ((int) $leftCommande->getIdCommande());
        });

        return $this->render('commandes/fournisseur_ventes.html.twig', [
            'sales' => $sales,
            'stats' => [
                'total_ventes' => count($sales),
                'revenu_total' => $revenueTotal,
                'produits_vendus' => $soldUnits,
                'submitted' => $submittedCount,
                'completed' => $completedCount,
                'cancelled' => $cancelledCount,
            ],
            'filters' => [
                'q' => (string) $request->query->get('q', ''),
                'status' => mb_strtolower($statusFilter),
            ],
        ]);
    }

    #[Route('/fournisseur/{idCommande}/complete', name: 'app_fournisseur_ventes_complete', methods: ['POST'])]
    public function completeVente(Request $request, int $idCommande, Lignes_commandeRepository $lignesCommandeRepository, CommandesRepository $commandesRepository, RessourcesRepository $ressourcesRepository, EntityManagerInterface $entityManager): Response
    {
        $commande = $commandesRepository->find($idCommande);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_fournisseur_ventes');
        }

        $request->getSession()->set('nav_ctx', 'fournisseur');

        $isFournisseur = ($request->getSession()->get('user_role') === 'Fournisseur');
        if (!$isFournisseur) {
            return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('complete-vente-'.$commande->getIdCommande(), (string) $request->request->get('_token'))) {
            $this->addFlash('warning', 'Action invalide.');

            return $this->redirectToRoute('app_fournisseur_ventes', [], Response::HTTP_SEE_OTHER);
        }

        $userId = $request->getSession()->get('user_id');
        $user = $userId ? $entityManager->getRepository(Utilisateurs::class)->find($userId) : null;
        if (!$user instanceof Utilisateurs) {
            $this->addFlash('warning', 'Connectez-vous pour mettre à jour une vente.');
 
            return $this->redirectToRoute('app_ressources_index', [], Response::HTTP_SEE_OTHER);
        }
 
        $fournisseurId = (int) $user->getId();
        $belongsToFournisseur = false;

        foreach ($lignesCommandeRepository->findBy(['id_commande' => $commande->getIdCommande()]) as $line) {
            $ressource = $line->getId_ressource();
            if (!$ressource instanceof Ressources) {
                continue;
            }

            $owner = $ressource->getId_fournisseur();
            $ownerId = $owner instanceof Utilisateurs ? (int) $owner->getId() : (int) $owner;
            if ($ownerId === $fournisseurId) {
                $belongsToFournisseur = true;
                break;
            }
        }

        if (!$belongsToFournisseur) {
            $this->addFlash('warning', 'Cette commande ne contient aucune de vos ressources.');

            return $this->redirectToRoute('app_fournisseur_ventes', [], Response::HTTP_SEE_OTHER);
        }

        $status = mb_strtoupper(trim((string) $commande->getStatut()));
        if ($status !== 'SUBMITTED') {
            $this->addFlash('warning', 'Seules les commandes soumises peuvent être marquées comme livrées.');

            return $this->redirectToRoute('app_fournisseur_ventes', [], Response::HTTP_SEE_OTHER);
        }

        $commande->setStatut('COMPLETED');
        $entityManager->flush();

        $this->addFlash('success', 'Commande marquée comme livrée (COMPLETED).');

        return $this->redirectToRoute('app_fournisseur_ventes', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{idCommande}', name: 'app_commandes_show', methods: ['GET'])]
    public function show(int $idCommande, Lignes_commandeRepository $lignesCommandeRepository, CommandesRepository $commandesRepository, RessourcesRepository $ressourcesRepository): Response
    {
        $commande = $commandesRepository->find($idCommande);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_commandes_index');
        }
        $lines = $lignesCommandeRepository->findBy(['id_commande' => $commande->getId_commande()]);
        $resourceNames = $this->buildRessourceNamesMap($lines, $ressourcesRepository);

        $sameUserOrders = $commandesRepository->findBy([
            'id_entrepreneur' => $commande->getIdEntrepreneur(),
        ], ['date_creation' => 'ASC', 'id_commande' => 'ASC']);
        $sameUserOrders = array_values(array_filter($sameUserOrders, static fn (Commandes $order): bool => in_array(mb_strtoupper((string) $order->getStatut()), ['SUBMITTED', 'COMPLETED'], true)));
        $orderNumbers = $this->buildOrderNumbers($sameUserOrders);

        return $this->render('commandes/show.html.twig', [
            'commande' => $commande,
            'lines' => $lines,
            'resource_names' => $resourceNames,
            'order_number' => $orderNumbers[(int) $commande->getIdCommande()] ?? null,
        ]);
    }

    #[Route('/{idCommande}/cancel', name: 'app_commandes_cancel', methods: ['POST'])]
    public function cancel(Request $request, int $idCommande, CommandesRepository $commandesRepository, EntityManagerInterface $entityManager, Lignes_commandeRepository $lignesCommandeRepository, RessourcesRepository $ressourcesRepository): Response
    {
        $commande = $commandesRepository->find($idCommande);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_commandes_index');
        }
        $userId = $request->getSession()->get('user_id');
        $user = $userId ? $entityManager->getRepository(Utilisateurs::class)->find($userId) : null;
        
        if (!$user instanceof Utilisateurs || (int) $commande->getIdEntrepreneur()->getId() !== (int) $user->getId()) {
            $this->addFlash('error', 'Vous n’avez pas l’autorisation d’annuler cette commande.');
            return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
        }

        if (!$this->isCsrfTokenValid('cancel'.$commande->getIdCommande(), (string) $request->request->get('_token'))) {
            $this->addFlash('error', 'Jeton de sécurité invalide.');
            return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
        }

        $status = mb_strtoupper(trim((string) $commande->getStatut()));
        if ($status !== 'SUBMITTED') {
            $this->addFlash('warning', 'Seules les commandes soumises peuvent être annulées.');
            return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
        }

        // Restore stock for each line
        foreach ($lignesCommandeRepository->findBy(['id_commande' => $commande->getIdCommande()]) as $line) {
            $ressource = $line->getId_ressource();
            if ($ressource instanceof Ressources) {
                $qty = max(1, (int) $line->getQuantite());
                $currentStock = (int) $ressource->getQuantite();
                $ressource->setQuantite($currentStock + $qty);
                $this->syncRessourceStatus($ressource);
            }
        }

        $commande->setStatut('CANCELLED');
        $entityManager->flush();

        $this->addFlash('success', 'Commande annulée avec succès. Le stock a été restauré.');

        return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
    }

    private function syncRessourceStatus(Ressources $ressource): void
    {
        $quantity = max(0, (int) $ressource->getQuantite());
        if ($quantity > 0) {
            $ressource->setDisponibilite(true);
            $ressource->setEtat('Disponible');
        }
    }

    #[Route('/{idCommande}/edit', name: 'app_commandes_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, int $idCommande, CommandesRepository $commandesRepository, EntityManagerInterface $entityManager): Response
    {
        $commande = $commandesRepository->find($idCommande);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_commandes_index');
        }
        $form = $this->createForm(CommandesType::class, $commande);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $entityManager->flush();

            return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('commandes/edit.html.twig', [
            'commande' => $commande,
            'form' => $form,
        ]);
    }

    #[Route('/{idCommande}', name: 'app_commandes_delete', methods: ['POST'])]
    public function delete(Request $request, int $idCommande, CommandesRepository $commandesRepository, EntityManagerInterface $entityManager): Response
    {
        $commande = $commandesRepository->find($idCommande);
        if (!$commande) {
            $this->addFlash('error', 'Commande introuvable.');
            return $this->redirectToRoute('app_commandes_index');
        }
        if ($this->isCsrfTokenValid('delete'.$commande->getIdCommande(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($commande);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
    }

    private function getCurrentUser(Request $request, EntityManagerInterface $em): ?Utilisateurs
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return null;
        }

        return $em->getRepository(Utilisateurs::class)->find($userId);
    }

    private function isCurrentUserFournisseur(Request $request): bool
    {
        return $request->getSession()->get('user_role') === 'Fournisseur';
    }

    private function buildOrderNumbers(array $commandes): array
    {
        usort($commandes, static function (Commandes $left, Commandes $right): int {
            $dateCompare = $left->getDate_creation() <=> $right->getDate_creation();
            if ($dateCompare !== 0) {
                return $dateCompare;
            }

            return ((int) $left->getIdCommande()) <=> ((int) $right->getIdCommande());
        });

        $numbers = [];
        $index = 1;
        foreach ($commandes as $commande) {
            $numbers[(int) $commande->getIdCommande()] = $index;
            ++$index;
        }

        return $numbers;
    }

    private function buildRessourceNamesMap(array $lines, RessourcesRepository $ressourcesRepository): array
    {
        $resourceIds = array_values(array_unique(array_map(static function ($line): int {
            $r = $line->getId_ressource();
            return $r instanceof \App\Entity\Ressources ? (int) $r->getId_ressource() : 0;
        }, $lines)));
        
        $map = [];
 
        foreach ($resourceIds as $resourceId) {
            if ($resourceId === 0) {
                continue;
            }
            $ressource = $ressourcesRepository->find($resourceId);
            if ($ressource === null) {
                continue;
            }
 
            $name = trim((string) $ressource->getNom());
            $map[$resourceId] = $name !== '' ? $name : ('Ressource #'.$resourceId);
        }
 
        return $map;
    }
}
