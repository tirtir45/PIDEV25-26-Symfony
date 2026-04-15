<?php

namespace App\Controller;

use App\Entity\Commandes;
use App\Entity\Lignes_commande;
use App\Entity\Ressources;
use App\Entity\Utilisateurs;
use App\Repository\RessourcesRepository;
use App\Service\EmailService;
use App\Service\PdfService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/panier')]
final class PanierController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly PdfService $pdfService,
        private readonly EmailService $emailService,
        private readonly \App\Service\SmsService $smsService,
        private readonly int $lowStockThreshold
    ) {
    }

    #[Route('', name: 'app_panier_index', methods: ['GET'])]
    public function index(Request $request, RessourcesRepository $ressourcesRepository): Response
    {
        $this->markSupplierContext($request);

        $items = $this->buildCartItems($request, $ressourcesRepository);
        $selectedItems = array_values(array_filter($items, static fn (array $item): bool => (bool) ($item['selected'] ?? false)));
        $totals = $this->calculateTotals($selectedItems);

        $commande = new Commandes();
        $form = $this->createForm(\App\Form\CommandesType::class, $commande);

        return $this->render('panier/index.html.twig', [
            'items' => $items,
            'totals' => $totals,
            'cart_count' => count($items),
            'selected_count' => count($selectedItems),
            'form' => $form->createView(),
        ]);
    }

    #[Route('/ajouter/{idRessource}', name: 'app_panier_add', methods: ['POST'])]
    public function add(Request $request, #[MapEntity(mapping: ['idRessource' => 'id_ressource'])] Ressources $ressource): Response
    {
        $this->markSupplierContext($request);

        $draftCommande = $this->getOrCreateDraftCommande($this->entityManager);
        $resourceId = (int) $ressource->getId_ressource();
        $quantity = max(1, (int) $request->request->get('quantite', 1));
        $today = new \DateTime();
        $defaultStart = $today->format('Y-m-d');
        $defaultEnd = (clone $today)->modify('+1 day')->format('Y-m-d');
        $isRental = $this->isRental($ressource);
        $dateDeb = (string) $request->request->get('date_deb', $defaultStart);
        $dateFin = (string) $request->request->get('date_fin', $defaultEnd);
        if (!$isRental) {
            $dateDeb = $defaultStart;
            $dateFin = $defaultStart;
        }

        [$startDate, $endDate] = $this->normalizeDateRange($dateDeb, $dateFin, $isRental);

        $existingLine = $this->entityManager->getRepository(Lignes_commande::class)->findOneBy([
            'id_commande' => $draftCommande,
            'id_ressource' => $ressource,
        ]);

        if (!$ressource->getDisponibilite()) {
            $this->addFlash('warning', 'Cette ressource est indisponible.');

            $referer = (string) $request->headers->get('referer', '');
            if ($referer !== '') {
                return $this->redirect($referer, Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_ressources_index', [], Response::HTTP_SEE_OTHER);
        }

        if (!$this->reserveStock($ressource, $quantity)) {
            $this->addFlash('warning', 'Stock insuffisant pour cette ressource.');

            $referer = (string) $request->headers->get('referer', '');
            if ($referer !== '') {
                return $this->redirect($referer, Response::HTTP_SEE_OTHER);
            }

            return $this->redirectToRoute('app_ressources_index', [], Response::HTTP_SEE_OTHER);
        }

        if ($existingLine instanceof Lignes_commande) {
            $existingLine->setQuantite(max(1, (int) $existingLine->getQuantite() + $quantity));
            $existingLine->setDate_deb($startDate);
            $existingLine->setDate_fin($endDate);
            $existingLine->setPrix_ligne(number_format(
                $this->calculateLineTotal(
                    $ressource,
                    (int) $existingLine->getQuantite(),
                    $startDate,
                    $endDate,
                ),
                2,
                '.',
                '',
            ));
        } else {
            $line = new Lignes_commande();
            $line->setId_ligne($this->nextLigneId($this->entityManager));
            $line->setId_commande($draftCommande);
            $line->setId_ressource($ressource);
            $line->setQuantite($quantity);
            $line->setDate_deb($startDate);
            $line->setDate_fin($endDate);
            $line->setPrix_ligne(number_format($this->calculateLineTotal($ressource, $quantity, $startDate, $endDate), 2, '.', ''));
            $this->entityManager->persist($line);
        }

        $this->entityManager->flush();
        $this->updateSelectedLineIds($request, $resourceId, true);

        $this->addFlash('success', 'Ressource ajoutée au panier.');

        $referer = (string) $request->headers->get('referer', '');
        if ($referer !== '') {
            return $this->redirect($referer, Response::HTTP_SEE_OTHER);
        }

        return $this->redirectToRoute('app_ressources_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{idRessource}/update', name: 'app_panier_update', methods: ['POST'])]
    public function update(Request $request, #[MapEntity(mapping: ['idRessource' => 'id_ressource'])] Ressources $ressource): Response
    {
        $this->markSupplierContext($request);

        $draftCommande = $this->getOrCreateDraftCommande($this->entityManager);
        $resourceId = (int) $ressource->getId_ressource();
        $line = $this->entityManager->getRepository(Lignes_commande::class)->findOneBy([
            'id_commande' => $draftCommande,
            'id_ressource' => $ressource,
        ]);

        if ($line instanceof Lignes_commande) {
            $currentQuantity = max(1, (int) $line->getQuantite());
            $requestedQuantity = max(1, (int) $request->request->get('quantite', $line->getQuantite() ?? 1));
            $adjustedQuantity = $requestedQuantity;
            $delta = $requestedQuantity - $currentQuantity;

            if ($delta > 0) {
                if (!$this->reserveStock($ressource, $delta)) {
                    $adjustedQuantity = $currentQuantity;
                    $this->addFlash('warning', 'Stock insuffisant pour augmenter la quantité.');
                }
            } elseif ($delta < 0) {
                $this->restoreStock($ressource, abs($delta));
            }

            $line->setQuantite($adjustedQuantity);
            $today = new \DateTime();
            $defaultStart = $today->format('Y-m-d');
            $defaultEnd = (clone $today)->modify('+1 day')->format('Y-m-d');

            $startDate = $this->parseDate($defaultStart, new \DateTime());
            $endDate = $this->parseDate($defaultEnd, new \DateTime('+1 day'));

            if ($this->isRental($ressource)) {
                [$startDate, $endDate] = $this->normalizeDateRange(
                    (string) $request->request->get('date_deb', $defaultStart),
                    (string) $request->request->get('date_fin', $defaultEnd),
                    true,
                );
            } else {
                [$startDate, $endDate] = $this->normalizeDateRange($defaultStart, $defaultStart, false);
            }

            $line->setDate_deb($startDate);
            $line->setDate_fin($endDate);
            $line->setPrix_ligne(number_format(
                $this->calculateLineTotal(
                    $ressource,
                    (int) $line->getQuantite(),
                    $startDate,
                    $endDate,
                ),
                2,
                '.',
                '',
            ));
            $this->entityManager->flush();
        }

        $this->updateSelectedLineIds($request, $resourceId, $request->request->getBoolean('selected', false));

        return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/selection', name: 'app_panier_selection', methods: ['POST'])]
    public function selection(Request $request): Response
    {
        $this->markSupplierContext($request);

        $selectedIds = array_map('intval', (array) $request->request->all('selected_ids'));
        $this->saveSelectedLineIds($request, $selectedIds);

        return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{idRessource}/remove', name: 'app_panier_remove', methods: ['POST'])]
    public function remove(Request $request, #[MapEntity(mapping: ['idRessource' => 'id_ressource'])] Ressources $ressource): Response
    {
        $this->markSupplierContext($request);

        $draftCommande = $this->getOrCreateDraftCommande($this->entityManager);
        $resourceId = (int) $ressource->getId_ressource();
        $line = $this->entityManager->getRepository(Lignes_commande::class)->findOneBy([
            'id_commande' => $draftCommande,
            'id_ressource' => $ressource,
        ]);

        if ($line instanceof Lignes_commande) {
            $this->restoreStock($ressource, max(1, (int) $line->getQuantite()));
            $this->entityManager->remove($line);
            $this->entityManager->flush();
        }

        $this->removeSelectedLineId($request, $resourceId);

        return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/confirmer', name: 'app_panier_checkout', methods: ['POST'])]
    public function checkout(
        Request $request,
        RessourcesRepository $ressourcesRepository,
    ): Response {
        $this->markSupplierContext($request);

        $draftCommande = $this->getOrCreateDraftCommande($this->entityManager);
        $selectedLineIds = $this->getSelectedLineIds($request);

        $draftLines = $this->entityManager->getRepository(Lignes_commande::class)->findBy([
            'id_commande' => $draftCommande,
        ]);

        $selectedLines = array_values(array_filter($draftLines, static function(Lignes_commande $line) use ($selectedLineIds): bool {
            $r = $line->getId_ressource();
            return $r instanceof Ressources && in_array((int) $r->getId_ressource(), $selectedLineIds, true);
        }));

        if ($selectedLines === [] && $draftLines !== []) {
            $selectedLines = $draftLines;
        }

        if ($selectedLines === []) {
            $this->addFlash('warning', 'Sélectionnez au moins une ressource.');

            return $this->redirectToRoute('app_panier_index', [], Response::HTTP_SEE_OTHER);
        }

        // Handle Delivery Form
        $dummyCommande = new Commandes();
        $form = $this->createForm(\App\Form\CommandesType::class, $dummyCommande);
        $form->handleRequest($request);

        // Group lines by Fournisseur
        $linesByFournisseur = [];
        foreach ($selectedLines as $line) {
            $ressource = $line->getId_ressource();
            if (!$ressource instanceof Ressources) continue;
            
            $fournisseur = $ressource->getId_fournisseur();
            $fournisseurId = $fournisseur ? $fournisseur->getId() : 0;
            $linesByFournisseur[$fournisseurId][] = $line;
        }

        $entrepreneur = $this->resolveEntrepreneur($this->entityManager);
        $ordersCreated = 0;

        foreach ($linesByFournisseur as $sid => $lines) {
            $commande = new Commandes();
            $commande->setIdCommande($this->nextCommandeId($this->entityManager));
            $commande->setIdEntrepreneur($entrepreneur);
            
            // Assign supplier if found
            if ($sid > 0) {
                $supplier = $this->entityManager->getRepository(Utilisateurs::class)->find($sid);
                $commande->setIdFournisseur($supplier);
            }

            $commande->setStatut('SUBMITTED');
            $commande->setDateCreation(new \DateTime());
            $commande->setTrackingNumber('');
            $commande->setCarrierCode('');
            
            // Set delivery info from form
            $commande->setAdresseLivraison($dummyCommande->getAdresseLivraison());
            $commande->setVilleLivraison($dummyCommande->getVilleLivraison());
            $commande->setCodePostal($dummyCommande->getCodePostal());
            $commande->setTelephoneLivraison($dummyCommande->getTelephoneLivraison());

            $subtotal = 0.0;
            foreach ($lines as $line) {
                $ressource = $line->getId_ressource();
                $lineTotal = $this->calculateLineTotal(
                    $ressource,
                    max(1, (int) $line->getQuantite()),
                    $line->getDate_deb(),
                    $line->getDate_fin(),
                );
                $line->setId_commande($commande);
                $line->setPrix_ligne(number_format($lineTotal, 2, '.', ''));
                $subtotal += $lineTotal;
            }

            $tva = $subtotal * 0.19;
            $totalTtc = $subtotal + $tva;
            $commande->setTotalGlobal(number_format($totalTtc, 2, '.', ''));

            $this->entityManager->persist($commande);
            $ordersCreated++;

            // ─── Email & PDF Generation ───
            try {
                $attachments = [];
                $resourceNames = $this->buildResourceNamesMap($lines, $ressourcesRepository);
                
                // 1. Generate Receipt PDF
                $receiptContent = $this->pdfService->generatePdf('emails/receipt_pdf.html.twig', [
                    'order' => $commande,
                    'lines' => $lines,
                    'resource_names' => $resourceNames,
                ]);
                $attachments['recu_commande_' . $commande->getIdCommande() . '.pdf'] = $receiptContent;

                // 2. Generate Contracts for "Espace" resources
                foreach ($lines as $line) {
                    $res = $line->getId_ressource();
                    if ($res instanceof Ressources && mb_strtolower(trim((string) $res->getType_r())) === 'espace') {
                        $contractContent = $this->pdfService->generatePdf('emails/contract_pdf.html.twig', [
                            'order' => $commande,
                            'line' => $line,
                            'resource' => $res,
                        ]);
                        $attachments['contrat_espace_' . $res->getId_ressource() . '.pdf'] = $contractContent;
                    }
                }

                // 3. Send Email
                if ($entrepreneur && $entrepreneur->getEmail()) {
                    $this->emailService->sendOrderConfirmation(
                        $entrepreneur->getEmail(),
                        $entrepreneur->getNom() ?? 'Client',
                        $attachments
                    );
                }
            } catch (\Exception $e) {
                $this->addFlash('error', 'Erreur Email : ' . $e->getMessage());
            }
        }

        $this->entityManager->flush();

        // Cleanup draft
        $remainingDraftLines = $this->entityManager->getRepository(Lignes_commande::class)->findBy([
            'id_commande' => $draftCommande,
        ]);

        if ($remainingDraftLines === []) {
            $this->entityManager->remove($draftCommande);
            $this->entityManager->flush();
        }

        $this->removeSelectedLineIds($request, $selectedLineIds);
        $this->addFlash('success', sprintf('%d commande(s) enregistrée(s) avec succès. Email envoyé à : %s', $ordersCreated, $entrepreneur->getEmail()));

        return $this->redirectToRoute('app_commandes_index', [], Response::HTTP_SEE_OTHER);
    }

    private function markSupplierContext(Request $request): void
    {
        $request->getSession()->set('nav_ctx', 'entrepreneur');
    }

    private function getSelectedLineIds(Request $request): array
    {
        $selected = $request->getSession()->get('supplier_cart_selected_line_ids', []);

        return array_values(array_filter(array_map('intval', is_array($selected) ? $selected : [])));
    }

    private function saveSelectedLineIds(Request $request, array $selectedLineIds): void
    {
        $request->getSession()->set('supplier_cart_selected_line_ids', array_values(array_unique(array_map('intval', $selectedLineIds))));
    }

    private function updateSelectedLineIds(Request $request, int $resourceId, bool $selected): void
    {
        $selectedLineIds = $this->getSelectedLineIds($request);

        if ($selected) {
            if (!in_array($resourceId, $selectedLineIds, true)) {
                $selectedLineIds[] = $resourceId;
            }
        } else {
            $selectedLineIds = array_values(array_filter($selectedLineIds, static fn (int $id): bool => $id !== $resourceId));
        }

        $this->saveSelectedLineIds($request, $selectedLineIds);
    }

    private function removeSelectedLineId(Request $request, int $resourceId): void
    {
        $selectedLineIds = array_values(array_filter($this->getSelectedLineIds($request), static fn (int $id): bool => $id !== $resourceId));
        $this->saveSelectedLineIds($request, $selectedLineIds);
    }

    private function removeSelectedLineIds(Request $request, array $resourceIds): void
    {
        $resourceIds = array_map('intval', $resourceIds);
        $selectedLineIds = array_values(array_filter($this->getSelectedLineIds($request), static fn (int $id): bool => !in_array($id, $resourceIds, true)));
        $this->saveSelectedLineIds($request, $selectedLineIds);
    }

    private function getOrCreateDraftCommande(EntityManagerInterface $entityManager): Commandes
    {
        $entrepreneur = $this->resolveEntrepreneur($entityManager);
 
        $draft = $entityManager->createQueryBuilder()
            ->select('c')
            ->from(Commandes::class, 'c')
            ->where('c.id_entrepreneur = :entrepreneur')
            ->andWhere('c.statut IN (:draftStatuses)')
            ->setParameter('entrepreneur', $entrepreneur)
            ->setParameter('draftStatuses', ['PANIER', 'Panier', 'panier'])
            ->orderBy('c.id_commande', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
 
        if ($draft instanceof Commandes) {
            return $draft;
        }
 
        $draft = new Commandes();
        $draft->setIdCommande($this->nextCommandeId($entityManager));
        $draft->setIdEntrepreneur($entrepreneur);
        $draft->setStatut('PANIER');
        $draft->setDateCreation(new \DateTime());
        $draft->setTotalGlobal('0.00');
        $draft->setTrackingNumber('');
        $draft->setCarrierCode('');

        $entityManager->persist($draft);
        $entityManager->flush();

        return $draft;
    }

    private function resolveEntrepreneur(EntityManagerInterface $entityManager): Utilisateurs
    {
        $session = $this->container->get('request_stack')->getCurrentRequest()->getSession();
        $userId = $session->get('user_id');
        
        if ($userId) {
            $user = $entityManager->getRepository(Utilisateurs::class)->find($userId);
            if ($user instanceof Utilisateurs) {
                return $user;
            }
        }
 
        // Dev/test fallback
        $fallback = $entityManager->getRepository(Utilisateurs::class)->findOneBy([], ['id' => 'ASC']);
        if ($fallback instanceof Utilisateurs) {
            return $fallback;
        }
 
        throw new \RuntimeException('Aucun utilisateur disponible pour créer un panier.');
    }

    private function buildCartItems(Request $request, RessourcesRepository $ressourcesRepository): array
    {
        $items = [];
        $draftCommande = $this->getOrCreateDraftCommande($this->entityManager);
        $selectedLineIds = $this->getSelectedLineIds($request);
        $lines = $this->entityManager->getRepository(Lignes_commande::class)->findBy([
            'id_commande' => $draftCommande,
        ]);

        if ($selectedLineIds === [] && $lines !== []) {
            $selectedLineIds = array_map(static function(Lignes_commande $line): int {
                $r = $line->getId_ressource();
                return $r instanceof Ressources ? (int) $r->getId_ressource() : 0;
            }, $lines);
            $this->saveSelectedLineIds($request, $selectedLineIds);
        }

        foreach ($lines as $line) {
            $ressource = $line->getId_ressource();
            if (!$ressource instanceof Ressources) {
                continue;
            }

            $unitPrice = $this->resolveUnitPrice($ressource);
            $quantity = max(1, (int) $line->getQuantite());
            $lineTotal = $this->calculateLineTotal(
                $ressource,
                $quantity,
                $line->getDate_deb(),
                $line->getDate_fin(),
            );
            $items[] = [
                'line_id' => $line->getId_ligne(),
                'resource_id' => $ressource->getId_ressource(),
                'ressource' => $ressource,
                'quantite' => $quantity,
                'is_rental' => $this->isRental($ressource),
                'date_deb' => $this->isRental($ressource) ? $line->getDate_deb()->format('Y-m-d') : '',
                'date_fin' => $this->isRental($ressource) ? $line->getDate_fin()->format('Y-m-d') : '',
                'selected' => in_array((int) $ressource->getId_ressource(), $selectedLineIds, true),
                'unit_price' => $unitPrice,
                'line_total' => $lineTotal,
            ];
        }

        return $items;
    }

    private function calculateTotals(array $items): array
    {
        $subtotal = 0.0;

        foreach ($items as $item) {
            $subtotal += (float) ($item['line_total'] ?? 0);
        }

        $tva = $subtotal * 0.19;

        return [
            'items' => count($items),
            'subtotal' => $subtotal,
            'tva' => $tva,
            'total' => $subtotal + $tva,
        ];
    }

    private function resolveUnitPrice(Ressources $ressource): float
    {
        $offre = mb_strtolower(trim((string) $ressource->getOffre()));

        if (in_array($offre, ['louer', 'location', 'a louer', 'rent'], true)) {
            return (float) $ressource->getPrix_louer();
        }

        return (float) $ressource->getPrix_achat();
    }

    private function isRental(Ressources $ressource): bool
    {
        $offre = mb_strtolower(trim((string) $ressource->getOffre()));

        return in_array($offre, ['louer', 'location', 'a louer', 'rent'], true);
    }

    private function parseDate(string $value, \DateTime $fallback): \DateTime
    {
        if ($value !== '') {
            $parsed = \DateTime::createFromFormat('Y-m-d', $value);
            if ($parsed instanceof \DateTime) {
                return $parsed;
            }
        }

        return $fallback;
    }

    private function reserveStock(Ressources $ressource, int $quantity): bool
    {
        $quantity = max(0, $quantity);
        if ($quantity === 0) {
            return true;
        }

        $available = max(0, (int) $ressource->getQuantite());
        if ($available < $quantity) {
            return false;
        }

        $newQuantity = $available - $quantity;
        $ressource->setQuantite($newQuantity);
        
        // Low Stock Alert Logic
        if ($newQuantity <= $this->lowStockThreshold && !$ressource->isLowStockAlertSent()) {
            if ($this->smsService->sendLowStockAlert($ressource)) {
                $ressource->setLowStockAlertSent(true);
            }
        }

        $this->syncRessourceAvailabilityFromStock($ressource);

        return true;
    }

    private function restoreStock(Ressources $ressource, int $quantity): void
    {
        $quantity = max(0, $quantity);
        if ($quantity === 0) {
            return;
        }

        $current = max(0, (int) $ressource->getQuantite());
        $newQuantity = $current + $quantity;
        $ressource->setQuantite($newQuantity);

        // Reset alert flag if stock is restored above threshold
        if ($newQuantity > $this->lowStockThreshold) {
            $ressource->setLowStockAlertSent(false);
        }

        $this->syncRessourceAvailabilityFromStock($ressource);
    }

    private function syncRessourceAvailabilityFromStock(Ressources $ressource): void
    {
        $quantity = max(0, (int) $ressource->getQuantite());
        $ressource->setQuantite($quantity);

        if ($quantity === 0) {
            $ressource->setDisponibilite(false);
            $ressource->setEtat('Rupture de stock');

            return;
        }

        if (!$ressource->getDisponibilite()) {
            $ressource->setEtat('Indisponible');

            return;
        }

        $ressource->setEtat('Disponible');
    }

    private function normalizeDateRange(string $startValue, string $endValue, bool $isRental): array
    {
        $startDate = $this->parseDate($startValue, new \DateTime());

        if (!$isRental) {
            return [$startDate, clone $startDate];
        }

        $endDate = $this->parseDate($endValue, clone $startDate);
        if ($endDate < $startDate) {
            $endDate = clone $startDate;
        }

        return [$startDate, $endDate];
    }

    private function calculateLineTotal(
        Ressources $ressource,
        int $quantity,
        \DateTimeInterface $startDate,
        \DateTimeInterface $endDate,
    ): float {
        $quantity = max(1, $quantity);
        $unitPrice = $this->resolveUnitPrice($ressource);

        if (!$this->isRental($ressource)) {
            return $unitPrice * $quantity;
        }

        $periodCount = $this->calculateRentalPeriods($ressource, $startDate, $endDate);

        return $unitPrice * $quantity * $periodCount;
    }

    private function calculateRentalPeriods(Ressources $ressource, \DateTimeInterface $startDate, \DateTimeInterface $endDate): int
    {
        if ($endDate < $startDate) {
            return 1;
        }

        $seconds = $endDate->getTimestamp() - $startDate->getTimestamp();
        $days = max(1, (int) ceil($seconds / 86400));
        $unit = mb_strtolower(trim((string) $ressource->getUnite_louer()));

        if (str_contains($unit, 'heure') || str_contains($unit, 'hour')) {
            return max(1, (int) ceil($seconds / 3600));
        }

        if (str_contains($unit, 'semaine') || str_contains($unit, 'week')) {
            return max(1, (int) ceil($days / 7));
        }

        if (str_contains($unit, 'mois') || str_contains($unit, 'month')) {
            return max(1, (int) ceil($days / 30));
        }

        return $days;
    }

    private function nextCommandeId(EntityManagerInterface $entityManager): int
    {
        $value = $entityManager->createQuery('SELECT COALESCE(MAX(c.id_commande), 0) FROM App\\Entity\\Commandes c')->getSingleScalarResult();

        return ((int) $value) + 1;
    }

    private function nextLigneId(EntityManagerInterface $entityManager): int
    {
        $value = $entityManager->createQuery('SELECT COALESCE(MAX(l.id_ligne), 0) FROM App\\Entity\\Lignes_commande l')->getSingleScalarResult();

        return ((int) $value) + 1;
    }

    private function getCurrentUser(): ?Utilisateurs
    {
        $session = $this->container->get('request_stack')->getCurrentRequest()->getSession();
        $userId = $session->get('user_id');
        if (!$userId) {
            return null;
        }

        return $this->entityManager->getRepository(Utilisateurs::class)->find($userId);
    }

    private function buildResourceNamesMap(array $lines, RessourcesRepository $repo): array
    {
        $map = [];
        foreach ($lines as $line) {
            $r = $line->getId_ressource();
            if ($r instanceof Ressources) {
                $map[(int) $r->getId_ressource()] = $r->getNom();
            }
        }

        return $map;
    }
}