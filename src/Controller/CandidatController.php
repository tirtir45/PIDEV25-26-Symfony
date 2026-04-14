<?php

namespace App\Controller;

use App\Repository\Demande_emploiRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/candidat')]
class CandidatController extends AbstractController
{
    #[Route('/mes-candidatures', name: 'app_candidat_mes_candidatures', methods: ['GET'])]
    #[IsGranted('ROLE_USER')]
    public function mesCandidatures(Demande_emploiRepository $demandeEmploiRepository): Response
    {
        $user = $this->getUser();

        // Récupérer toutes les candidatures de l'utilisateur connecté
        $candidatures = $demandeEmploiRepository->findBy(['candidat' => $user]);

        return $this->render('user/MesCandidatures.html.twig', [
            'candidatures' => $candidatures,
        ]);
    }
}