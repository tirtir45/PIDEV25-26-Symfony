<?php
namespace App\Service;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\Writer\SvgWriter;
use Twig\Environment;

class FacturePdfService
{
    public function __construct(
        private readonly Environment $twig,
        private readonly string $baseUrl
    ) {}

    public function generatePdf(Reservation $reservation): string
    {
        $verifyUrl = $this->baseUrl . '/evenements/verify/' . $reservation->getTokenVerification();

        // Generate QR as inline SVG — no GD / no image extension required
        $qrSvg = null;
        try {
            $result = (new Builder(
                writer: new SvgWriter(),
                data: $verifyUrl,
                errorCorrectionLevel: ErrorCorrectionLevel::High,
                size: 160,
                margin: 6,
            ))->build();
            $qrSvg = $result->getString();
        } catch (\Throwable) {
            // leave null — template shows fallback text
        }

        $html = $this->twig->render('pdf/facture.html.twig', [
            'reservation' => $reservation,
            'evenement'   => $reservation->getEvenement(),
            'utilisateur' => $reservation->getUtilisateur(),
            'qr_svg'      => $qrSvg,
            'verify_url'  => $verifyUrl,
        ]);

        $options = new Options();
        $options->set('defaultFont', 'DejaVu Sans');
        $options->set('isHtml5ParserEnabled', true);

        $dompdf = new Dompdf($options);
        $dompdf->loadHtml($html, 'UTF-8');
        $dompdf->setPaper('A4', 'portrait');
        $dompdf->render();

        return $dompdf->output();
    }
}
