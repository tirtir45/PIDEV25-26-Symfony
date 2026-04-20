<?php

namespace App\Controller;

use App\Entity\Notification;
use App\Repository\UtilisateursRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class NotificationController extends AbstractController
{
    #[Route('/notifications', name: 'notifications_index', methods: ['GET'])]
    public function index(Request $request, NotificationService $notifier, UtilisateursRepository $userRepo): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) return $this->redirectToRoute('app_login');

        $user  = $userRepo->find($userId);
        $notifs = $notifier->getForUser($user, 30);
        $notifier->markAllRead($user);

        return $this->render('notifications/index.html.twig', [
            'notifications' => $notifs,
        ]);
    }

    #[Route('/notifications/count', name: 'notifications_count', methods: ['GET'])]
    public function count(Request $request, NotificationService $notifier, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) return new JsonResponse(['count' => 0]);

        $user  = $userRepo->find($userId);
        return new JsonResponse(['count' => $notifier->countUnread($user)]);
    }

    #[Route('/notifications/mark-read', name: 'notifications_mark_read', methods: ['POST'])]
    public function markRead(Request $request, NotificationService $notifier, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) return new JsonResponse(['ok' => false]);

        $user = $userRepo->find($userId);
        $notifier->markAllRead($user);
        return new JsonResponse(['ok' => true]);
    }
}
