<?php

namespace App\Controller;

use App\Repository\Demande_emploisRepository;
use App\Service\RhEmailService;
use App\Service\ZoomService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\Extension\Core\Type\DateTimeType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/admin/entretiens')]
class EntretienController extends AbstractController
{
    private function requireAdmin(Request $request): ?Response
    {
        if (!$request->getSession()->get('user_id')) {
            return $this->redirectToRoute('app_login');
        }
        if ($request->getSession()->get('user_role') !== 'Administrateur') {
            throw $this->createAccessDeniedException();
        }
        return null;
    }

    #[Route('/{id}/planifier', name: 'app_entretien_planifier', methods: ['GET', 'POST'])]
    public function planifier(int $id, Request $request, Demande_emploisRepository $repo, ZoomService $zoomService, RhEmailService $emailService): Response
    {
        if ($r = $this->requireAdmin($request)) return $r;

        $demande = $repo->find($id);
        if (!$demande) throw $this->createNotFoundException();

        $candidat    = $demande->getCandidat();
        $publication = $demande->getPublication();

        $form = $this->createFormBuilder([
            'topic'   => 'Entretien pour la candidature à « ' . ($publication?->getTitre() ?? 'offre') . ' »',
            'message' => 'Bonjour ' . ($candidat?->getNom() ?? 'candidat') . ",\n\nNous vous proposons un entretien pour votre candidature.\n\n"
                . 'Offre : ' . ($publication?->getTitre() ?? 'N/D') . "\n"
                . 'Statut : ' . ($demande->getStatutDemande() ?? 'N/D') . "\n\n"
                . 'Merci de rejoindre la réunion via le lien que vous recevrez par email.',
        ])
            ->add('topic', TextType::class, ['label' => 'Sujet de la réunion', 'required' => true])
            ->add('startTime', DateTimeType::class, [
                'label'    => "Date et heure de l'entretien",
                'widget'   => 'single_text',
                'required' => true,
            ])
            ->add('message', TextareaType::class, [
                'label'    => 'Message au candidat',
                'required' => true,
                'attr'     => ['rows' => 6],
            ])
            ->add('send', SubmitType::class, ['label' => 'Créer la réunion Zoom et envoyer le mail'])
            ->getForm();

        $form->handleRequest($request);
        $meetingUrl = null;

        if ($form->isSubmitted() && $form->isValid()) {
            $data      = $form->getData();
            $startTime = $data['startTime'];

            if ($startTime instanceof \DateTimeInterface) {
                $startTimeUtc = (clone $startTime)->setTimezone(new \DateTimeZone('UTC'));
                $meetingUrl   = $zoomService->createMeeting($data['topic'], $startTimeUtc->format('Y-m-d\TH:i:s\Z'));

                $emailContent = '<p>' . nl2br(htmlspecialchars($data['message'], ENT_QUOTES, 'UTF-8')) . '</p>'
                    . '<p><strong>Lien de réunion :</strong> <a href="' . htmlspecialchars($meetingUrl, ENT_QUOTES, 'UTF-8') . '">' . htmlspecialchars($meetingUrl, ENT_QUOTES, 'UTF-8') . '</a></p>';

                $emailService->sendEmail(
                    $candidat->getEmail(),
                    'Entretien prévu pour votre candidature',
                    $emailContent,
                    true
                );

                $this->addFlash('success', 'Réunion Zoom créée et email envoyé au candidat.');
            }
        }

        return $this->render('entretien/planifier.html.twig', [
            'demande'    => $demande,
            'form'       => $form->createView(),
            'meetingUrl' => $meetingUrl,
        ]);
    }
}
