<?php
namespace App\Service;

use App\Entity\Reservation;
use Dompdf\Dompdf;
use Dompdf\Options;
use Endroid\QrCode\Bacon\MatrixFactory;
use Endroid\QrCode\Encoding\Encoding;
use Endroid\QrCode\ErrorCorrectionLevel;
use Endroid\QrCode\QrCode;
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

        // Build QR code as an HTML table — works with DOMPDF, needs zero PHP extensions
        $qrHtml = $this->buildQrTable($verifyUrl);

        $html = $this->twig->render('pdf/facture.html.twig', [
            'reservation' => $reservation,
            'evenement'   => $reservation->getEvenement(),
            'utilisateur' => $reservation->getUtilisateur(),
            'qr_html'     => $qrHtml,
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

    /**
     * Renders a QR code as a pure HTML table (no GD, no SVG, no extensions needed).
     */
    private function buildQrTable(string $data): string
    {
        $qrCode = new QrCode(
            data: $data,
            encoding: new Encoding('UTF-8'),
            errorCorrectionLevel: ErrorCorrectionLevel::High,
            size: 200,
            margin: 2,
        );

        $matrix     = (new MatrixFactory())->create($qrCode);
        $blockCount = $matrix->getBlockCount();
        $blockSize  = round(140 / $blockCount, 2); // fit into 140px total

        $html = sprintf(
            '<table style="border-collapse:collapse;border-spacing:0;margin:0 auto;'
            . 'background:#fff;padding:0;" cellpadding="0" cellspacing="0">'
        );

        for ($row = 0; $row < $blockCount; $row++) {
            $html .= '<tr>';
            for ($col = 0; $col < $blockCount; $col++) {
                $dark  = $matrix->getBlockValue($row, $col) === 1;
                $bg    = $dark ? '#000000' : '#ffffff';
                $html .= sprintf(
                    '<td style="width:%.2fpx;height:%.2fpx;background:%s;'
                    . 'padding:0;border:none;font-size:0;line-height:0;"></td>',
                    $blockSize, $blockSize, $bg
                );
            }
            $html .= '</tr>';
        }

        $html .= '</table>';

        return $html;
    }
}
