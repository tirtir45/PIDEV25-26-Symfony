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
                size: 150,
                margin: 4,
            ))->build();
            // Strip XML declaration so DOMPDF accepts it as inline SVG
            $svg = preg_replace('/<\?xml[^>]+\?>\s*/', '', $result->getString());
            // Force fixed dimensions so DOMPDF renders at correct size
            $svg = preg_replace('/<svg\b/', '<svg width="140" height="140"', $svg, 1);
            $qrSvg = $svg;
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
