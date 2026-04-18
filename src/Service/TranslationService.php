<?php

namespace App\Service;

use Stichoza\GoogleTranslate\GoogleTranslate;

class TranslationService
{
    private GoogleTranslate $translator;

    public function __construct()
    {
        $this->translator = new GoogleTranslate();
    }

    /**
     * Traduit un texte vers la langue cible.
     * Détecte automatiquement la langue source.
     *
     * @param string $text       Texte à traduire
     * @param string $targetLang Langue cible (fr, en, ar, ...)
     * @return array{translated: string, sourceLang: string}
     */
    public function translate(string $text, string $targetLang = 'fr'): array
    {
        try {
            $this->translator->setSource(null); // détection automatique
            $this->translator->setTarget($targetLang);

            $translated = $this->translator->translate($text);
            $sourceLang = $this->translator->getLastDetectedSource() ?? 'unknown';

            return [
                'translated' => $translated ?? $text,
                'sourceLang' => $sourceLang,
            ];
        } catch (\Throwable $e) {
            // Si la traduction échoue, on retourne le texte original
            return [
                'translated' => $text,
                'sourceLang' => 'unknown',
            ];
        }
    }

    /**
     * Détecte uniquement la langue d'un texte.
     */
    public function detectLanguage(string $text): string
    {
        try {
            $this->translator->setSource(null)->setTarget('fr');
            $this->translator->translate($text);
            return $this->translator->getLastDetectedSource() ?? 'unknown';
        } catch (\Throwable) {
            return 'unknown';
        }
    }
}
