<?php

namespace App\Controller;

use App\Entity\Utilisateurs;
use App\Entity\Roles;
use App\Repository\UtilisateursRepository;
use App\Repository\RolesRepository;
use App\Service\NotificationService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;

#[Route('/admin/utilisateurs')]
class AdminUserController extends AbstractController
{
    private function checkAdmin(Request $request): bool
    {
        return $request->getSession()->get('user_id')
            && $request->getSession()->get('user_role') === 'Administrateur';
    }

    #[Route('', name: 'admin_user_index')]
    public function index(Request $request, UtilisateursRepository $userRepo, RolesRepository $roleRepo): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_login');
        }

        $search = $request->query->get('search', '');
        $roleId = $request->query->get('role', '');
        
        $qb = $userRepo->createQueryBuilder('u')
            ->leftJoin('u.role', 'r')
            ->addSelect('r')
            ->where('r.nomRole != :adminRole OR r.id IS NULL')
            ->setParameter('adminRole', 'Administrateur');

        if ($search) {
            $qb->andWhere('u.nom LIKE :search OR u.email LIKE :search OR u.telephone LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($roleId) {
            $qb->andWhere('r.id = :roleId')
               ->setParameter('roleId', $roleId);
        }

        $users = $qb->getQuery()->getResult();
        $roles = $roleRepo->createQueryBuilder('r')
            ->where('r.nomRole IN (:roles)')
            ->setParameter('roles', ['Candidat', 'Entrepreneur', 'Fournisseur'])
            ->orderBy('r.nomRole', 'ASC')
            ->getQuery()->getResult();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'roles' => $roles,
            'search' => $search,
            'currentRole' => $roleId
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Utilisateurs $user, EntityManagerInterface $em, Request $request, NotificationService $notifier): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getRole() && $user->getRole()->getNomRole() === 'Administrateur') {
            $this->addFlash('error', 'Impossible de désactiver un compte Administrateur.');
            return $this->redirectToRoute('admin_user_index');
        }

        $user->setActif(!$user->isActif());
        $em->flush();

        $status = $user->isActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Le compte de {$user->getNom()} a été {$status}.");

        // Email à l'utilisateur
        $notifier->sendAccountStatusEmail($user, $user->isActif());

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Utilisateurs $user, EntityManagerInterface $em, Request $request, NotificationService $notifier): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_login');
        }

        if ($user->getId() === $request->getSession()->get('user_id')) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_user_index');
        }

        if ($user->getRole() && $user->getRole()->getNomRole() === 'Administrateur') {
            $this->addFlash('error', 'Impossible de supprimer un compte Administrateur.');
            return $this->redirectToRoute('admin_user_index');
        }

        // Email avant suppression
        $notifier->sendAccountDeletedEmail($user);

        $nom = $user->getNom();

        // Supprimer les données liées
        foreach ($em->getRepository(\App\Entity\Reclamations::class)->findBy(['utilisateur' => $user]) as $r) {
            $em->remove($r);
        }
        foreach ($em->getRepository(\App\Entity\Notification::class)->findBy(['utilisateur' => $user]) as $n) {
            $em->remove($n);
        }
        $em->flush();

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', "L'utilisateur {$nom} a été supprimé.");

        return $this->redirectToRoute('admin_user_index');
    }
}
