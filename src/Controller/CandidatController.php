<?php

namespace App\Controller;

use App\Repository\Demande_emploisRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/candidat')]
class CandidatController extends AbstractController
{
    #[Route('/mes-candidatures', name: 'app_candidat_mes_candidatures', methods: ['GET'])]
    public function mesCandidatures(Request $request, Demande_emploisRepository $repo): Response
    {
        $userId = $request->getSession()->get('user_id');
        if (!$userId) {
            return $this->redirectToRoute('app_login');
        }

        $candidatures = $repo->findByCandidat($userId);

        return $this->render('demande_emploi/mes_candidatures.html.twig', [
            'candidatures' => $candidatures,
        ]);
    }
}
