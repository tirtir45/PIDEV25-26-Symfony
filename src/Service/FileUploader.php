<?php

namespace App\Service;

use App\Entity\FichierProjet;
use App\Entity\Projets;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class FileUploader
{
    public function __construct(
        private string $uploadDir,
        private EntityManagerInterface $em
    ) {}

    public function upload(UploadedFile $file, Projets $projet, Utilisateurs $uploadedBy): FichierProjet
    {
        $extension   = $file->guessExtension() ?? $file->getClientOriginalExtension();
        $nomFichier  = uniqid('proj_', true) . '.' . $extension;

        $file->move($this->uploadDir, $nomFichier);

        $fichier = new FichierProjet();
        $fichier->setProjet($projet);
        $fichier->setUploadedBy($uploadedBy);
        $fichier->setNomOriginal($file->getClientOriginalName());
        $fichier->setNomFichier($nomFichier);
        $fichier->setMimeType($file->getMimeType() ?? 'application/octet-stream');
        $fichier->setTaille($file->getSize() ?: 0);
        $fichier->setExtension($extension);

        $this->em->persist($fichier);
        $this->em->flush();

        return $fichier;
    }
}
