<?php

namespace App\Service;

use Symfony\Component\Process\Process;

class CvParserService
{
    public function extractText(string $pdfPath): string
    {
        $process = new Process([
            'python',
            'python/extract_pdf.py',
            $pdfPath
        ]);

        $process->run();

        if (!$process->isSuccessful()) {
            throw new \Exception(
                $process->getErrorOutput()
            );
        }

        return trim($process->getOutput());
    }
}