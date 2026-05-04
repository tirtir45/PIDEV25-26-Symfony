<?php

namespace App\Controller;

use App\Entity\Conversations;
use App\Entity\Conversation_participants;
use App\Entity\Messages;
use App\Repository\UtilisateursRepository;
use App\Repository\ConversationsRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

class MessagerieController extends AbstractController
{
    private function getCurrentUserId(Request $request): ?int
    {
        return $request->getSession()->get('user_id');
    }

    #[Route('/messagerie', name: 'app_messagerie', methods: ['GET'])]
    public function index(Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): Response
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $currentUser = $userRepo->find($userId);
        $conversations = $this->getUserConversations($em, $userId);
        $users = $userRepo->findBy(['actif' => true]);

        return $this->render('messagerie/index.html.twig', [
            'conversations' => $conversations,
            'currentUser'   => $currentUser,
            'users'         => $users,
            'activeConv'    => null,
            'messages'      => [],
        ]);
    }

    #[Route('/messagerie/conversation/{id}', name: 'app_messagerie_conversation', methods: ['GET'])]
    public function conversation(int $id, Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): Response
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $currentUser = $userRepo->find($userId);

        // Verify user is participant
        $participation = $em->createQueryBuilder()
            ->select('cp')
            ->from(Conversation_participants::class, 'cp')
            ->where('cp.id_conversation = :conv')
            ->andWhere('cp.id_utilisateur = :user')
            ->setParameter('conv', $id)
            ->setParameter('user', $currentUser)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$participation) {
            $this->addFlash('error', 'Accès refusé à cette conversation.');
            return $this->redirectToRoute('app_messagerie');
        }

        $conversation = $em->getRepository(Conversations::class)->find($id);
        $conversations = $this->getUserConversations($em, $userId);
        $users = $userRepo->findBy(['actif' => true]);

        // Load messages (not deleted)
        $messages = $em->createQueryBuilder()
            ->select('m', 'u')
            ->from(Messages::class, 'm')
            ->join('m.id_expediteur', 'u')
            ->where('m.id_conversation = :conv')
            ->andWhere('m.est_supprime = false')
            ->setParameter('conv', $conversation)
            ->orderBy('m.date_envoi', 'ASC')
            ->getQuery()
            ->getResult();

        // Mark messages as read
        $em->createQueryBuilder()
            ->update(Messages::class, 'm')
            ->set('m.est_lu', true)
            ->where('m.id_conversation = :conv')
            ->andWhere('m.id_expediteur != :me')
            ->andWhere('m.est_lu = false')
            ->setParameter('conv', $conversation)
            ->setParameter('me', $currentUser)
            ->getQuery()
            ->execute();

        // Update dernier_message_lu
        $lastMsg = end($messages);
        if ($lastMsg) {
            $participation->setDernier_message_lu($lastMsg->getId_message());
            $em->flush();
        }

        // Get participants for group info
        $participants = $em->createQueryBuilder()
            ->select('cp', 'u')
            ->from(Conversation_participants::class, 'cp')
            ->join('cp.id_utilisateur', 'u')
            ->where('cp.id_conversation = :conv')
            ->setParameter('conv', $conversation)
            ->getQuery()
            ->getResult();

        return $this->render('messagerie/index.html.twig', [
            'conversations' => $conversations,
            'currentUser'   => $currentUser,
            'users'         => $users,
            'activeConv'    => $conversation,
            'messages'      => $messages,
            'participants'  => $participants,
        ]);
    }

    #[Route('/messagerie/nouvelle', name: 'app_messagerie_nouvelle', methods: ['POST'])]
    public function nouvelleConversation(Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): Response
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $currentUser = $userRepo->find($userId);
        $type        = $request->request->get('type', 'individuelle'); // individuelle | groupe
        $titre       = trim((string) $request->request->get('titre', ''));
        $destIds     = $request->request->all('destinataires'); // array of user ids

        if (empty($destIds)) {
            $this->addFlash('error', 'Veuillez sélectionner au moins un destinataire.');
            return $this->redirectToRoute('app_messagerie');
        }

        // For individual chat: check if conversation already exists
        if ($type === 'individuelle' && count($destIds) === 1) {
            $destId   = (int) $destIds[0];
            $existing = $this->findIndividualConversation($em, $userId, $destId);
            if ($existing) {
                return $this->redirectToRoute('app_messagerie_conversation', ['id' => $existing->getId_conversation()]);
            }
            $dest  = $userRepo->find($destId);
            $titre = $dest ? $dest->getNom() : 'Conversation';
        }

        if (empty($titre)) {
            $titre = 'Groupe';
        }

        $now = new \DateTime();

        $conv = new Conversations();
        $conv->setType($type);
        $conv->setTitre($titre);
        $conv->setDate_creation($now);
        $conv->setDerniere_activite($now);
        $em->persist($conv);

        // Add current user as participant
        $allParticipantIds = array_unique(array_merge([$userId], array_map('intval', $destIds)));
        foreach ($allParticipantIds as $pid) {
            $u = $userRepo->find($pid);
            if (!$u) continue;
            $cp = new Conversation_participants();
            $cp->setId_conversation($conv);
            $cp->setId_utilisateur($u);
            $cp->setDate_ajout($now);
            $cp->setEst_archive(false);
            $cp->setDernier_message_lu(0);
            $em->persist($cp);
        }

        $em->flush();

        return $this->redirectToRoute('app_messagerie_conversation', ['id' => $conv->getId_conversation()]);
    }

    #[Route('/messagerie/envoyer/{id}', name: 'app_messagerie_envoyer', methods: ['POST'])]
    public function envoyerMessage(int $id, Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $currentUser = $userRepo->find($userId);
        if (!$currentUser) {
            return new JsonResponse(['error' => 'Utilisateur introuvable'], 404);
        }

        $conversation = $em->getRepository(Conversations::class)->find($id);
        if (!$conversation) {
            return new JsonResponse(['error' => 'Conversation introuvable'], 404);
        }

        // Verify participation via DQL to avoid proxy issues
        $participation = $em->createQueryBuilder()
            ->select('cp')
            ->from(Conversation_participants::class, 'cp')
            ->where('cp.id_conversation = :conv')
            ->andWhere('cp.id_utilisateur = :user')
            ->setParameter('conv', $conversation)
            ->setParameter('user', $currentUser)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();

        if (!$participation) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $contenu = trim((string) $request->request->get('contenu', ''));
        if (empty($contenu)) {
            return new JsonResponse(['error' => 'Message vide'], 400);
        }

        $now = new \DateTime();

        $msg = new Messages();
        $msg->setId_conversation($conversation);
        $msg->setId_expediteur($currentUser);
        $msg->setContenu($contenu);
        $msg->setDate_envoi($now);
        $msg->setEst_lu(false);
        $msg->setEst_modifie(false);
        $msg->setEst_supprime(false);
        $msg->setType_contenu('texte');
        $msg->setPiece_jointe('');
        $em->persist($msg);

        $conversation->setDerniere_activite($now);
        $em->flush();

        return new JsonResponse([
            'id'            => $msg->getId_message(),
            'contenu'       => $msg->getContenu(),
            'date_envoi'    => $now->format('H:i'),
            'expediteur'    => $currentUser->getNom(),
            'expediteur_id' => $userId,
            'is_mine'       => true,
        ]);
    }

    #[Route('/messagerie/messages/{id}', name: 'app_messagerie_messages_json', methods: ['GET'])]
    public function getMessages(int $id, Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $currentUser  = $userRepo->find($userId);
        $conversation = $em->getRepository(Conversations::class)->find($id);

        if (!$conversation) {
            return new JsonResponse(['error' => 'Conversation introuvable'], 404);
        }

        $participation = $em->getRepository(Conversation_participants::class)->findOneBy([
            'id_conversation' => $conversation,
            'id_utilisateur'  => $currentUser,
        ]);

        if (!$participation) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $since = (int) $request->query->get('since', 0);

        $qb = $em->createQueryBuilder()
            ->select('m', 'u')
            ->from(Messages::class, 'm')
            ->join('m.id_expediteur', 'u')
            ->where('m.id_conversation = :conv')
            ->andWhere('m.est_supprime = false')
            ->setParameter('conv', $conversation)
            ->orderBy('m.date_envoi', 'ASC');

        if ($since > 0) {
            $qb->andWhere('m.id_message > :since')->setParameter('since', $since);
        }

        $messages = $qb->getQuery()->getResult();

        // Mark new messages as read
        if ($since > 0 && !empty($messages)) {
            $em->createQueryBuilder()
                ->update(Messages::class, 'm')
                ->set('m.est_lu', true)
                ->where('m.id_conversation = :conv')
                ->andWhere('m.id_expediteur != :me')
                ->andWhere('m.est_lu = false')
                ->setParameter('conv', $conversation)
                ->setParameter('me', $currentUser)
                ->getQuery()->execute();

            $lastMsg = end($messages);
            if ($lastMsg) {
                $participation->setDernier_message_lu($lastMsg->getId_message());
                $em->flush();
            }
        }

        $data = [];
        foreach ($messages as $msg) {
            $data[] = [
                'id'           => $msg->getId_message(),
                'contenu'      => $msg->getContenu(),
                'date_envoi'   => $msg->getDate_envoi()->format('H:i'),
                'expediteur'   => $msg->getId_expediteur()->getNom(),
                'expediteur_id'=> $msg->getId_expediteur()->getId(),
                'is_mine'      => $msg->getId_expediteur()->getId() === $userId,
            ];
        }

        return new JsonResponse($data);
    }

    #[Route('/messagerie/supprimer/{id}', name: 'app_messagerie_supprimer', methods: ['POST'])]
    public function supprimerMessage(int $id, Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $currentUser = $userRepo->find($userId);
        $msg = $em->getRepository(Messages::class)->find($id);

        if (!$msg) {
            return new JsonResponse(['error' => 'Message introuvable'], 404);
        }

        if ((int)$msg->getId_expediteur()->getId() !== (int)$userId) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $msg->setEst_supprime(true);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }
    #[Route('/messagerie/archiver/{id}', name: 'app_messagerie_archiver', methods: ['POST'])]
    public function archiverConversation(int $id, Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return new JsonResponse(['error' => 'Non authentifié'], 401);
        }

        $currentUser  = $userRepo->find($userId);
        $conversation = $em->getRepository(Conversations::class)->find($id);

        $participation = $em->getRepository(Conversation_participants::class)->findOneBy([
            'id_conversation' => $conversation,
            'id_utilisateur'  => $currentUser,
        ]);

        if (!$participation) {
            return new JsonResponse(['error' => 'Accès refusé'], 403);
        }

        $participation->setEst_archive(true);
        $em->flush();

        return new JsonResponse(['success' => true]);
    }

    #[Route('/messagerie/non-lus-par-conv', name: 'app_messagerie_non_lus_par_conv', methods: ['GET'])]
    public function getNonLusParConv(Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) return new JsonResponse([]);

        $currentUser = $userRepo->find($userId);

        $rows = $em->createQueryBuilder()
            ->select('IDENTITY(m.id_conversation) as conv_id, COUNT(m.id_message) as cnt')
            ->from(Messages::class, 'm')
            ->join(Conversation_participants::class, 'cp', 'WITH', 'cp.id_conversation = m.id_conversation AND cp.id_utilisateur = :me')
            ->where('m.id_expediteur != :me')
            ->andWhere('m.est_lu = false')
            ->andWhere('m.est_supprime = false')
            ->setParameter('me', $currentUser)
            ->groupBy('m.id_conversation')
            ->getQuery()->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int)$row['conv_id']] = (int)$row['cnt'];
        }

        return new JsonResponse($result);
    }

    #[Route('/messagerie/non-lus', name: 'app_messagerie_non_lus', methods: ['GET'])]
    public function getNonLus(Request $request, EntityManagerInterface $em, UtilisateursRepository $userRepo): JsonResponse
    {
        $userId = $this->getCurrentUserId($request);
        if (!$userId) {
            return new JsonResponse(['count' => 0]);
        }

        $currentUser = $userRepo->find($userId);

        $count = $em->createQueryBuilder()
            ->select('COUNT(m.id_message)')
            ->from(Messages::class, 'm')
            ->join('m.id_conversation', 'c')
            ->join(Conversation_participants::class, 'cp', 'WITH', 'cp.id_conversation = c AND cp.id_utilisateur = :me')
            ->where('m.id_expediteur != :me')
            ->andWhere('m.est_lu = false')
            ->andWhere('m.est_supprime = false')
            ->setParameter('me', $currentUser)
            ->getQuery()
            ->getSingleScalarResult();

        return new JsonResponse(['count' => (int) $count]);
    }

    // ---- Helpers ----

    private function getUserConversations(EntityManagerInterface $em, int $userId): array
    {
        $convs = $em->createQueryBuilder()
            ->select('c')
            ->from(Conversations::class, 'c')
            ->join(Conversation_participants::class, 'cp', 'WITH', 'cp.id_conversation = c')
            ->where('cp.id_utilisateur = :uid')
            ->andWhere('cp.est_archive = false')
            ->setParameter('uid', $userId)
            ->orderBy('c.derniere_activite', 'DESC')
            ->getQuery()
            ->getResult();

        // Attach last message to each conversation
        foreach ($convs as $conv) {
            $lastMsg = $em->createQueryBuilder()
                ->select('m', 'u')
                ->from(Messages::class, 'm')
                ->join('m.id_expediteur', 'u')
                ->where('m.id_conversation = :conv')
                ->andWhere('m.est_supprime = false')
                ->setParameter('conv', $conv)
                ->orderBy('m.date_envoi', 'DESC')
                ->setMaxResults(1)
                ->getQuery()
                ->getOneOrNullResult();

            $conv->lastMessage = $lastMsg;
        }

        return $convs;
    }

    private function findIndividualConversation(EntityManagerInterface $em, int $userId1, int $userId2): ?Conversations
    {
        $result = $em->createQueryBuilder()
            ->select('c')
            ->from(Conversations::class, 'c')
            ->join(Conversation_participants::class, 'cp1', 'WITH', 'cp1.id_conversation = c AND cp1.id_utilisateur = :u1')
            ->join(Conversation_participants::class, 'cp2', 'WITH', 'cp2.id_conversation = c AND cp2.id_utilisateur = :u2')
            ->where('c.type = :type')
            ->setParameter('u1', $userId1)
            ->setParameter('u2', $userId2)
            ->setParameter('type', 'individuelle')
            ->setMaxResults(1)
            ->getQuery()
            ->getResult();

        return $result[0] ?? null;
    }
}
