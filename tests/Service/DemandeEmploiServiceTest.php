<?php

namespace App\Tests\Service;

use PHPUnit\Framework\TestCase;
use App\Service\DemandeEmploiService;
use App\Entity\Demande_emplois;

class DemandeEmploiServiceTest extends TestCase
{
    public function testVerifierCv()
    {
        $service = new DemandeEmploiService();

        $this->assertTrue(
            $service->verifierCv('cv.pdf')
        );

        $this->assertFalse(
            $service->verifierCv('')
        );
    }

    public function testVerifierLettreMotivation()
    {
        $service = new DemandeEmploiService();

        $lettreValide =
            "Je suis motivé pour rejoindre votre entreprise.";

        $lettreCourte = "Bonjour";

        $this->assertTrue(
            $service->verifierLettreMotivation($lettreValide)
        );

        $this->assertFalse(
            $service->verifierLettreMotivation($lettreCourte)
        );
    }

    public function testDemandeValide()
    {
        $service = new DemandeEmploiService();

        $demande = new Demande_emplois();

        $demande->setCvUrl('cv.pdf');

        $demande->setLettreMotivation(
            "Je suis extrêmement motivé pour ce poste."
        );

        $this->assertTrue(
            $service->demandeValide($demande)
        );
    }

    public function testDemandeInvalideSansCv()
    {
        $service = new DemandeEmploiService();

        $demande = new Demande_emplois();

        $demande->setCvUrl('');

        $demande->setLettreMotivation(
            "Je suis extrêmement motivé pour ce poste."
        );

        $this->assertFalse(
            $service->demandeValide($demande)
        );
    }

    public function testDemandeInvalideLettreCourte()
    {
        $service = new DemandeEmploiService();

        $demande = new Demande_emplois();

        $demande->setCvUrl('cv.pdf');

        $demande->setLettreMotivation('Bonjour');

        $this->assertFalse(
            $service->demandeValide($demande)
        );
    }
}