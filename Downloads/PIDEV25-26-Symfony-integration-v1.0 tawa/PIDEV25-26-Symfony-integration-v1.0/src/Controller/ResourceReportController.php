<?php

namespace App\Controller;

use App\Entity\Ressources;
use App\Entity\ResourceReport;
use App\Entity\Utilisateurs;
use App\Service\SmartModerationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class ResourceReportController extends AbstractController
{
    #[Route('/ressource/report/{id}', name: 'app_resource_report', methods: ['GET', 'POST'])]
    public function report(Ressources $resource, Request $request, EntityManagerInterface $em, SmartModerationService $moderationService): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            $this->addFlash('error', 'Vous devez être connecté pour signaler un article.');
            return $this->redirectToRoute('app_login');
        }

        $user = $em->getRepository(Utilisateurs::class)->find($userId);

        if ($request->isMethod('POST')) {
            $report = new ResourceReport();
            $report->setRessource($resource);
            $report->setReporter($user);
            $report->setReason($request->request->get('reason', 'Contenu inapproprié'));
            $report->setDescription($request->request->get('description', ''));

            $em->persist($report);
            
            // Increment moderation score slightly on every report
            $resource->setModerationScore(min(100, $resource->getModerationScore() + 10));
            
            // Re-run AI analysis to see if the new context (report reason) confirms suspicion
            $moderationService->moderate($resource);

            $em->flush();

            $this->addFlash('info', 'Merci. Votre signalement a été transmis à nos modérateurs.');
            return $this->redirectToRoute('app_ressources_index');
        }

        return $this->render('ressources/report.html.twig', [
            'resource' => $resource
        ]);
    }
}
