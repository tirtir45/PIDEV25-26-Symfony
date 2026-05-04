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
        $mimeType    = $file->getMimeType() ?? 'application/octet-stream';
        $taille      = $file->getSize() ?: 0;
        $nomOriginal = $file->getClientOriginalName();
        $nomFichier  = uniqid('proj_', true) . '.' . $extension;

        $file->move($this->uploadDir, $nomFichier);

        $fichier = new FichierProjet();
        $fichier->setProjet($projet);
        $fichier->setUploadedBy($uploadedBy);
        $fichier->setNomOriginal($nomOriginal);
        $fichier->setNomFichier($nomFichier);
        $fichier->setMimeType($mimeType);
        $fichier->setTaille($taille);
        $fichier->setExtension($extension);

        $this->em->persist($fichier);
        $this->em->flush();

        return $fichier;
    }
}
