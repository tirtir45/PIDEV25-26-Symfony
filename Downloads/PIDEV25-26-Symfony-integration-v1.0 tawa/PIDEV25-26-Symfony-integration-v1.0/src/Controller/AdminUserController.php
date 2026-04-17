<?php

namespace App\Controller;

use App\Entity\Utilisateurs;
use App\Entity\Roles;
use App\Repository\UtilisateursRepository;
use App\Repository\RolesRepository;
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
            ->addSelect('r');

        if ($search) {
            $qb->andWhere('u.nom LIKE :search OR u.email LIKE :search OR u.telephone LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($roleId) {
            $qb->andWhere('r.id = :roleId')
               ->setParameter('roleId', $roleId);
        }

        $users = $qb->getQuery()->getResult();
        $roles = $roleRepo->findAll();

        return $this->render('admin/users.html.twig', [
            'users' => $users,
            'roles' => $roles,
            'search' => $search,
            'currentRole' => $roleId
        ]);
    }

    #[Route('/{id}/toggle-status', name: 'admin_user_toggle_status', methods: ['POST'])]
    public function toggleStatus(Utilisateurs $user, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_login');
        }

        $user->setActif(!$user->isActif());
        $em->flush();

        $status = $user->isActif() ? 'activé' : 'désactivé';
        $this->addFlash('success', "Le compte de {$user->getNom()} a été {$status}.");

        return $this->redirectToRoute('admin_user_index');
    }

    #[Route('/{id}/supprimer', name: 'admin_user_delete', methods: ['POST'])]
    public function delete(Utilisateurs $user, EntityManagerInterface $em, Request $request): Response
    {
        if (!$this->checkAdmin($request)) {
            return $this->redirectToRoute('app_login');
        }

        // Prevent self-deletion
        if ($user->getId() === $request->getSession()->get('user_id')) {
            $this->addFlash('error', 'Vous ne pouvez pas supprimer votre propre compte.');
            return $this->redirectToRoute('admin_user_index');
        }

        $em->remove($user);
        $em->flush();

        $this->addFlash('success', "L'utilisateur {$user->getNom()} a été supprimé.");

        return $this->redirectToRoute('admin_user_index');
    }
}
