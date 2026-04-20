<?php

namespace App\Controller;

use App\Entity\Ressources;
use App\Entity\ResourceReport;
use App\Service\SmartModerationService;
use App\Service\SmsService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/moderation')]
class AdminModerationController extends AbstractController
{
    #[Route('/', name: 'admin_moderation_index')]
    public function index(EntityManagerInterface $em, SmartModerationService $moderationService): Response
    {
        // Automatically cleanup expired banned items
        $moderationService->cleanupExpiredBannedResources($em);

        // Get all banned resources or those with specific moderation scores/flags
        $riskyResources = $em->getRepository(Ressources::class)->createQueryBuilder('r')
            ->where('r.moderation_score > 0')
            ->orWhere('r.is_banned = true')
            ->orWhere('r.moderation_reason LIKE :ia_flag')
            ->setParameter('ia_flag', '%IA IMAGE%')
            ->orderBy('r.moderation_score', 'DESC')
            ->getQuery()
            ->getResult();

        $reports = $em->getRepository(ResourceReport::class)->findBy(['status' => 'PENDING'], ['createdAt' => 'DESC']);

        return $this->render('admin/moderation.html.twig', [
            'resources' => $riskyResources,
            'reports' => $reports
        ]);
    }

    #[Route('/ban/{id}', name: 'admin_moderation_ban', methods: ['POST'])]
    public function ban(Ressources $resource, EntityManagerInterface $em, SmsService $smsService): Response
    {
        $resource->setIsBanned(true);
        $resource->setBannedAt(new \DateTime());
        $resource->setDisponibilite(false);
        $resource->setQuantite(0);
        $resource->setEtat('Banni / Sécurisé');
        
        $em->flush();
        
        // Notify Supplier via SMS with status feedback
        $smsStatus = $smsService->sendModerationAlert($resource);
        
        if ($smsStatus['success']) {
            $this->addFlash('success', 'La ressource a été bannie et le fournisseur notifié par SMS.');
        } else {
            $this->addFlash('warning', 'Ressource bannie, mais l\'SMS n\'a pas pu être envoyé : ' . $smsStatus['message']);
        }

        return $this->redirectToRoute('admin_moderation_index');
    }

    #[Route('/approve/{id}', name: 'admin_moderation_approve', methods: ['POST'])]
    public function approve(Ressources $resource, EntityManagerInterface $em): Response
    {
        $resource->setIsBanned(false);
        $resource->setBannedAt(null);
        $resource->setModerationScore(0);
        $resource->setModerationReason(null);
        $em->flush();
        $this->addFlash('success', 'La ressource a été approuvée.');
        return $this->redirectToRoute('admin_moderation_index');
    }

    #[Route('/report/{id}/resolve', name: 'admin_report_resolve', methods: ['POST'])]
    public function resolveReport(ResourceReport $report, EntityManagerInterface $em): Response
    {
        $report->setStatus('RESOLVED');
        $em->flush();
        $this->addFlash('success', 'Signalement résolu.');
        return $this->redirectToRoute('admin_moderation_index');
    }

    #[Route('/report/{id}/reject', name: 'admin_report_reject', methods: ['POST'])]
    public function rejectReport(ResourceReport $report, EntityManagerInterface $em): Response
    {
        $report->setStatus('REJECTED');
        $em->flush();
        $this->addFlash('info', 'Signalement rejeté.');
        return $this->redirectToRoute('admin_moderation_index');
    }
}
