<?php

namespace App\Controller;

use App\Entity\Ressources;
use App\Form\RessourceType;
use App\Repository\RessourcesRepository;
use App\Service\RecommendationService;
use App\Service\SmartModerationService;
use App\Service\SmsService;
use App\Service\VisionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/ressources')]
final class RessourcesController extends AbstractController
{

    #[Route('/fournisseur/mes-ressources', name: 'app_fournisseur_ressources', methods: ['GET'])]
    public function fournisseurDashboard(Request $request, RessourcesRepository $ressourcesRepository): Response
    {
        $request->getSession()->set('nav_ctx', 'fournisseur');
        $userId = $request->getSession()->get('user_id');
        $user = $userId ? $ressourcesRepository->getEntityManager()->getRepository(\App\Entity\Utilisateurs::class)->find($userId) : null;
        $isFournisseur = $this->isUserFournisseur($user);

        $search = trim((string) $request->query->get('q', ''));
        $sort = (string) $request->query->get('sort', 'created_desc');

        $statsQb = $ressourcesRepository->createQueryBuilder('r');
        $qb = $ressourcesRepository->createQueryBuilder('r');

        if ($isFournisseur && $user) {
            $statsQb->andWhere('r.id_fournisseur = :user')->setParameter('user', $user);
            $qb->andWhere('r.id_fournisseur = :user')->setParameter('user', $user);
        }

        if ($search !== '') {
            $qb
                ->andWhere('LOWER(r.nom) LIKE :search OR LOWER(r.type_r) LIKE :search OR LOWER(r.offre) LIKE :search OR LOWER(r.etat) LIKE :search')
                ->setParameter('search', '%'.mb_strtolower($search).'%');
        }

        switch ($sort) {
            case 'name_asc':      $qb->orderBy('r.nom', 'ASC'); break;
            case 'name_desc':     $qb->orderBy('r.nom', 'DESC'); break;
            case 'quantity_desc': $qb->orderBy('r.quantite', 'DESC'); break;
            case 'price_asc':     $qb->orderBy('r.prix_achat', 'ASC'); break;
            case 'price_desc':    $qb->orderBy('r.prix_achat', 'DESC'); break;
            case 'created_asc':   $qb->orderBy('r.created_at', 'ASC'); break;
            case 'created_desc':
            default:
                $qb->orderBy('r.created_at', 'DESC');
                $sort = 'created_desc';
                break;
        }

        $ressources            = $qb->getQuery()->getResult();
        $allRessourcesForStats = $statsQb->getQuery()->getResult();

        $totalItemsForSale = 0;
        $totalItemsForRent = 0;
        $saleListings      = 0;
        $rentListings      = 0;
        $availableListings = 0;

        foreach ($allRessourcesForStats as $ressource) {
            $qty   = max(0, (int) $ressource->getQuantite());
            $offre = mb_strtolower(trim((string) $ressource->getOffre()));
            if ($qty > 0) {
                ++$availableListings;
            }
            if (in_array($offre, ['achat', 'a vendre', 'vente', 'vendre'], true)) {
                ++$saleListings;
                $totalItemsForSale += $qty;
            }
            if (in_array($offre, ['louer', 'location', 'a louer', 'rent'], true)) {
                ++$rentListings;
                $totalItemsForRent += $qty;
            }
        }

        return $this->render('ressources/fournisseur_dashboard.html.twig', [
            'ressources' => $ressources,
            'stats' => [
                'items_achat'    => $totalItemsForSale,
                'items_louer'    => $totalItemsForRent,
                'listings_achat' => $saleListings,
                'listings_louer' => $rentListings,
                'disponibles'    => $availableListings,
            ],
            'filters' => ['q' => $search, 'sort' => $sort],
        ]);
    }

    public function __construct(
        private readonly SmsService $smsService,
        private readonly VisionService $visionService,
        private readonly int $lowStockThreshold
    ) {
    }

    #[Route(name: 'app_ressources_index', methods: ['GET'])]
    public function index(Request $request, RessourcesRepository $ressourcesRepository, RecommendationService $recommendationService, SmartModerationService $moderationService): Response
    {
        $isFournisseurCatalog = ($request->getSession()->get('user_role') === 'Fournisseur');
        $request->getSession()->set('nav_ctx', $isFournisseurCatalog ? 'fournisseur' : 'entrepreneur');

        $filters = [
            'q'            => trim((string) $request->query->get('q', '')),
            'offer'        => mb_strtolower(trim((string) $request->query->get('offer', 'all'))),
            'availability' => mb_strtolower(trim((string) $request->query->get('availability', 'all'))),
            'category'     => mb_strtolower(trim((string) $request->query->get('category', 'all'))),
            'sort'         => mb_strtolower(trim((string) $request->query->get('sort', 'name_asc'))),
        ];

        if (!in_array($filters['offer'], ['all', 'achat', 'louer'], true)) {
            $filters['offer'] = 'all';
        }
        if (!in_array($filters['availability'], ['all', 'disponible', 'rupture', 'indisponible'], true)) {
            $filters['availability'] = 'all';
        }
        if (!in_array($filters['sort'], ['name_asc', 'name_desc', 'price_asc', 'price_desc', 'quantity_asc', 'quantity_desc'], true)) {
            $filters['sort'] = 'name_asc';
        }

        $allRessources  = $ressourcesRepository->findAll();
        $baseRessources = array_values(array_filter($allRessources, static function (Ressources $ressource) use ($isFournisseurCatalog): bool {
            // NEVER show banned resources in the main shop or provider list
            if ($ressource->isBanned()) {
                return false;
            }
            
            // If it's a provider viewing their OWN catalog, show everything else
            if ($isFournisseurCatalog) {
                return true;
            }

            // Normal user (Entrepreneur) filtering
            $quantity = max(0, (int) $ressource->getQuantite());
            if ($quantity === 0) {
                return true;
            }
            return (bool) $ressource->getDisponibilite();
        }));

        $categoryOptions = [];
        foreach ($baseRessources as $ressource) {
            $type = trim((string) $ressource->getType_r());
            if ($type !== '') {
                $normalizedType = mb_strtolower($type);
                if (!array_key_exists($normalizedType, $categoryOptions)) {
                    $categoryOptions[$normalizedType] = mb_convert_case($normalizedType, MB_CASE_TITLE, 'UTF-8');
                }
            }
        }
        natcasesort($categoryOptions);

        $ressources = array_values(array_filter($baseRessources, function (Ressources $ressource) use ($filters): bool {
            $quantity    = max(0, (int) $ressource->getQuantite());
            $isAvailable = (bool) $ressource->getDisponibilite() && $quantity > 0;
            $isRupture   = $quantity === 0;

            if ($filters['q'] !== '') {
                $haystack = mb_strtolower(implode(' ', [
                    (string) $ressource->getNom(),
                    (string) $ressource->getDescription(),
                    (string) $ressource->getType_r(),
                    (string) $ressource->getOffre(),
                ]));
                if (!str_contains($haystack, mb_strtolower($filters['q']))) {
                    return false;
                }
            }
            if ($filters['offer'] !== 'all' && !$this->matchesOfferFilter($ressource, $filters['offer'])) {
                return false;
            }
            if ($filters['availability'] === 'disponible' && !$isAvailable) {
                return false;
            }
            if ($filters['availability'] === 'rupture' && !$isRupture) {
                return false;
            }
            if ($filters['availability'] === 'indisponible' && ($isRupture || (bool) $ressource->getDisponibilite())) {
                return false;
            }
            if ($filters['category'] !== 'all') {
                $type = mb_strtolower(trim((string) $ressource->getType_r()));
                if ($type !== mb_strtolower($filters['category'])) {
                    return false;
                }
            }
            return true;
        }));

        usort($ressources, function (Ressources $left, Ressources $right) use ($filters): int {
            return match ($filters['sort']) {
                'name_desc'    => strcasecmp((string) $right->getNom(), (string) $left->getNom()),
                'price_asc'    => $this->resolveCatalogPrice($left) <=> $this->resolveCatalogPrice($right),
                'price_desc'   => $this->resolveCatalogPrice($right) <=> $this->resolveCatalogPrice($left),
                'quantity_asc' => ((int) $left->getQuantite()) <=> ((int) $right->getQuantite()),
                'quantity_desc'=> ((int) $right->getQuantite()) <=> ((int) $left->getQuantite()),
                default        => strcasecmp((string) $left->getNom(), (string) $right->getNom()),
            };
        });

        if ($filters['category'] !== 'all' && !array_key_exists($filters['category'], $categoryOptions)) {
            $filters['category'] = 'all';
        }

        $cart          = $request->getSession()->get('supplier_cart', []);
        $achatCount    = 0;
        $louerCount    = 0;
        $disponibleCount = 0;
        $categories    = [];

        foreach ($ressources as $ressource) {
            $offre = mb_strtolower(trim((string) $ressource->getOffre()));
            if (in_array($offre, ['achat', 'a vendre', 'vente', 'vendre'], true)) {
                ++$achatCount;
            }
            if (in_array($offre, ['louer', 'location', 'a louer', 'rent'], true)) {
                ++$louerCount;
            }
            if (max(0, (int) $ressource->getQuantite()) > 0) {
                ++$disponibleCount;
            }
            $type = $ressource->getType_r();
            if ($type !== null && $type !== '') {
                $categories[$type] = true;
            }
        }

        $userId = $request->getSession()->get('user_id');
        $currentUser = $userId ? $ressourcesRepository->getEntityManager()->getRepository(\App\Entity\Utilisateurs::class)->find($userId) : null;
        $recommendedData = $recommendationService->getRecommendations($currentUser, $baseRessources);

        $recommendationMap = [];
        foreach ($recommendedData as $item) {
            $recommendationMap[$item['ressource']->getId_ressource()] = $item;
        }

        return $this->render('ressources/index.html.twig', [
            'ressources'        => $ressources,
            'rec_map'           => $recommendationMap,
            'has_projects'      => $currentUser ? !$currentUser->getProjetss()->isEmpty() : false,
            'catalog_mode'      => $isFournisseurCatalog ? 'fournisseur' : 'entrepreneur',
            'cart_count'        => is_array($cart) ? count($cart) : 0,
            'filters'           => $filters,
            'filter_categories' => $categoryOptions,
            'stats' => [
                'total'       => count($ressources),
                'achat'       => $achatCount,
                'louer'       => $louerCount,
                'disponibles' => $disponibleCount,
                'categories'  => count($categories),
            ],
        ]);
    }

    #[Route('/new', name: 'app_ressources_new', methods: ['GET', 'POST'])]
    public function new(Request $request, EntityManagerInterface $entityManager, SmartModerationService $moderationService): Response
    {
        $this->syncNavContextFromRequest($request);
        $request->getSession()->set('nav_ctx', 'fournisseur');

        $ressource = new Ressources();

        $currentFournisseur = $this->resolveCurrentFournisseur($request, $entityManager);
        if ($currentFournisseur === null) {
            $currentFournisseur = $this->resolveFallbackFournisseur($entityManager);
        }
        if ($currentFournisseur !== null) {
            $ressource->setId_fournisseur($currentFournisseur);
        }

        $form = $this->createForm(RessourceType::class, $ressource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            if ($currentFournisseur !== null) {
                $ressource->setId_fournisseur($currentFournisseur);
            }

            $uploadDir   = $this->getParameter('kernel.project_dir')
                . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . 'uploads'
                . DIRECTORY_SEPARATOR . 'ressources';

            $newFilename = $this->handleImageUpload($form, $uploadDir);
            if ($newFilename !== null) {
                $ressource->setImage_r($newFilename);
            }

            $this->normalizeOfferDependentFields($ressource);
            
            // Run Analytics (Text + Image)
            $moderationService->moderate($ressource);

            // Run Vision Analysis
            if ($ressource->getImageR()) {
                $imagePath = $uploadDir . DIRECTORY_SEPARATOR . $ressource->getImageR();
                $visionResult = $this->visionService->analyzeImage($imagePath, $ressource->getTypeR());
                
                if ($visionResult['is_dangerous']) {
                    $ressource->setModerationReason($ressource->getModerationReason() . ' | IA IMAGE: Contenu dangereux détecté (' . implode(', ', $visionResult['detected_labels']) . ')');
                } elseif ($visionResult['is_mismatch']) {
                    $ressource->setModerationReason($ressource->getModerationReason() . ' | IA IMAGE: Mismatch catégorie (Vu: ' . implode(', ', $visionResult['detected_labels']) . ')');
                }
            }
            
            $entityManager->persist($ressource);
            $entityManager->flush();

            if ($ressource->getModerationScore() > 0 || str_contains($ressource->getModerationReason() ?? '', 'IA IMAGE')) {
                $this->addFlash('info', 'Votre ressource est en ligne mais a été soumise à une vérification IA automatique.');
            }

            return $this->redirectToRoute('app_fournisseur_ressources', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ressources/new.html.twig', [
            'ressource' => $ressource,
            'form'      => $form,
        ]);
    }

    #[Route('/{idRessource}/edit', name: 'app_ressources_edit', methods: ['GET', 'POST'])]
    public function edit(Request $request, #[MapEntity(mapping: ['idRessource' => 'id_ressource'])] Ressources $ressource, EntityManagerInterface $entityManager, SmartModerationService $moderationService): Response
    {
        $this->syncNavContextFromRequest($request);
        $request->getSession()->set('nav_ctx', 'fournisseur');

        $form = $this->createForm(RessourceType::class, $ressource);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $uploadDir   = $this->getParameter('kernel.project_dir')
                . DIRECTORY_SEPARATOR . 'public'
                . DIRECTORY_SEPARATOR . 'uploads'
                . DIRECTORY_SEPARATOR . 'ressources';

            $newFilename = $this->handleImageUpload($form, $uploadDir);
            if ($newFilename !== null) {
                $ressource->setImage_r($newFilename);
            }

            $this->normalizeOfferDependentFields($ressource);
            
            // Re-run moderation (Text)
            $moderationService->moderate($ressource);
            
            // Re-run Vision Analysis if a new image was uploaded
            if ($newFilename !== null) {
                $imagePath = $uploadDir . DIRECTORY_SEPARATOR . $newFilename;
                $visionResult = $this->visionService->analyzeImage($imagePath, $ressource->getTypeR());
                
                if ($visionResult['is_dangerous'] || $visionResult['is_mismatch']) {
                    $reason = $visionResult['is_dangerous'] ? 'Contenu dangereux' : 'Mismatch catégorie';
                    $ressource->setModerationReason($ressource->getModerationReason() . " | IA IMAGE: $reason (Vu: " . implode(', ', $visionResult['detected_labels']) . ")");
                }
            }
            
            $entityManager->flush();

            if ($ressource->isBanned()) {
                $this->addFlash('warning', 'Modifications enregistrées. Note : Cet article est actuellement suspendu pour modération.');
            }

            return $this->redirectToRoute('app_fournisseur_ressources', [], Response::HTTP_SEE_OTHER);
        }

        return $this->render('ressources/edit.html.twig', [
            'ressource' => $ressource,
            'form'      => $form,
        ]);
    }

    #[Route('/{idRessource}', name: 'app_ressources_show', methods: ['GET'])]
    public function show(Request $request, #[MapEntity(mapping: ['idRessource' => 'id_ressource'])] Ressources $ressource): Response
    {
        $this->syncNavContextFromRequest($request);
        $request->getSession()->set('nav_ctx', 'fournisseur');

        return $this->render('ressources/show.html.twig', [
            'ressource' => $ressource,
        ]);
    }

    #[Route('/{idRessource}', name: 'app_ressources_delete', methods: ['POST'])]
    public function delete(Request $request, #[MapEntity(mapping: ['idRessource' => 'id_ressource'])] Ressources $ressource, EntityManagerInterface $entityManager): Response
    {
        $this->syncNavContextFromRequest($request);

        if ($this->isCsrfTokenValid('delete'.$ressource->getId_ressource(), $request->getPayload()->getString('_token'))) {
            $entityManager->remove($ressource);
            $entityManager->flush();
        }

        return $this->redirectToRoute('app_fournisseur_ressources', [], Response::HTTP_SEE_OTHER);
    }

    // ── Private helpers ───────────────────────────────────────────────────

    private function handleImageUpload(FormInterface $form, string $uploadDir): ?string
    {
        /** @var UploadedFile|null $file */
        $file = $form->get('image_r')->getData();

        if (!$file instanceof UploadedFile) {
            return null;
        }

        if (!is_dir($uploadDir)) {
            mkdir($uploadDir, 0775, true);
        }

        $safeBase    = preg_replace('/[^a-zA-Z0-9_-]/', '_', pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME));
        $extension   = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $newFilename = $safeBase . '_' . uniqid() . '.' . $extension;

        $file->move($uploadDir, $newFilename);

        return $newFilename;
    }

    private function normalizeOfferDependentFields(Ressources $ressource): void
    {
        $offre = $ressource->getOffre();
        if ($offre === 'achat') {
            $ressource->setPrix_louer(0.0);
            $ressource->setUnite_louer('');
        } elseif ($offre === 'louer') {
            $ressource->setPrix_achat(0.0);
        }
        $ressource->setPrix_achat($this->normalizeNumericValue($ressource->getPrix_achat()));
        $ressource->setPrix_louer($this->normalizeNumericValue($ressource->getPrix_louer()));
        $this->syncAvailabilityWithQuantity($ressource);
    }

    private function syncAvailabilityWithQuantity(Ressources $ressource): void
    {
        $quantity = max(0, (int) $ressource->getQuantite());
        $ressource->setQuantite($quantity);

        // Low Stock Alert Logic
        if ($quantity <= $this->lowStockThreshold) {
            if (!$ressource->isLowStockAlertSent()) {
                if ($this->smsService->sendLowStockAlert($ressource)) {
                    $ressource->setLowStockAlertSent(true);
                }
            }
        } else {
            // Reset alert flag if stock is above threshold
            $ressource->setLowStockAlertSent(false);
        }

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

    private function normalizeNumericValue(mixed $value): float
    {
        if ($value === null) {
            return 0.0;
        }
        $normalized = str_replace(',', '.', trim((string) $value));
        if ($normalized === '' || !is_numeric($normalized)) {
            return 0.0;
        }
        return (float) $normalized;
    }

    private function resolveCurrentFournisseur(Request $request, EntityManagerInterface $em): ?\App\Entity\Utilisateurs
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return null;
        }
        $user = $em->getRepository(\App\Entity\Utilisateurs::class)->find($userId);
        if (!$user instanceof \App\Entity\Utilisateurs) {
            return null;
        }
        return $user;
    }

    private function resolveFallbackFournisseur(EntityManagerInterface $entityManager): ?\App\Entity\Utilisateurs
    {
        $users = $entityManager->getRepository(\App\Entity\Utilisateurs::class)->findBy([], ['id_utilisateur' => 'ASC']);
        foreach ($users as $user) {
            if (!$user instanceof \App\Entity\Utilisateurs) {
                continue;
            }
            $role = $user->getId_role();
            if ($role !== null && method_exists($role, 'getNom_role')) {
                $roleName = mb_strtolower((string) $role->getNom_role());
                if (str_contains($roleName, 'fournisseur')) {
                    return $user;
                }
            }
        }
        return null;
    }

    private function matchesOfferFilter(Ressources $ressource, string $offerFilter): bool
    {
        $offre = mb_strtolower(trim((string) $ressource->getOffre()));
        if ($offerFilter === 'achat') {
            return in_array($offre, ['achat', 'a vendre', 'vente', 'vendre'], true);
        }
        if ($offerFilter === 'louer') {
            return in_array($offre, ['louer', 'location', 'a louer', 'rent'], true);
        }
        return true;
    }

    private function isCurrentUserFournisseur(Request $request): bool
    {
        return $request->getSession()->get('user_role') === 'Fournisseur';
    }

    private function resolveCatalogPrice(Ressources $ressource): float
    {
        $offre = mb_strtolower(trim((string) $ressource->getOffre()));
        if (in_array($offre, ['louer', 'location', 'a louer', 'rent'], true)) {
            return (float) $ressource->getPrix_louer();
        }
        return (float) $ressource->getPrix_achat();
    }

    private function isUserFournisseur(?\App\Entity\Utilisateurs $user): bool
    {
        if (!$user) {
            return false;
        }
        $role = $user->getRole();
        if ($role === null) {
            return false;
        }
        $roleName = mb_strtolower((string) $role->getNomRole());
        return str_contains($roleName, 'fournisseur');
    }

    private function syncNavContextFromRequest(Request $request): void
    {
        $ctx = (string) $request->query->get('ctx', '');
        if (in_array($ctx, ['fournisseur', 'entrepreneur'], true)) {
            $request->getSession()->set('nav_ctx', $ctx);
        }
    }
}