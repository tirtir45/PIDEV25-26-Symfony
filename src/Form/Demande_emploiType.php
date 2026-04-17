<?php
// src/Form/Demande_emploiType.php

namespace App\Form;

use App\Entity\Demande_emploi;
use App\Entity\Publication;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

// On ajoute le 'use' pour le type de champ de VichUploaderBundle
use Vich\UploaderBundle\Form\Type\VichFileType;

class Demande_emploiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // On récupère la candidature depuis les options pour savoir si on est en mode création ou édition
        /** @var Demande_emploi|null $demande */
        $demande = $options['data'] ?? null;
        $isEdit = $demande && $demande->getDemande_id();

        $builder
            ->add('publication', EntityType::class, [
                'class' => Publication::class,
                'choice_label' => 'titre',
                'label' => 'Offre concernée *',
                'placeholder' => 'Choisir une offre',
                'required' => true,
            ])
            ->add('candidat', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => fn(Utilisateur $u) => $u->getNom() . ' (' . $u->getEmail() . ')',
                'label' => 'Candidat *',
                'placeholder' => 'Choisir un candidat',
                'required' => true,
            ])
            
            // --- BLOC MODIFIÉ ---
            // On remplace l'ancien champ 'cvUrl' par 'cvFile' de type VichFileType
            ->add('cvFile', VichFileType::class, [
                'label' => 'CV (fichier PDF, 2Mo max)',
                // Le CV n'est pas obligatoire en mode édition, mais il l'est en mode création.
                'required' => !$isEdit, 
                
                // Affiche la case à cocher "Supprimer"
                'allow_delete' => true, 
                'delete_label' => 'Supprimer le CV actuel ?',

                // Affiche le lien de téléchargement s'il y a déjà un fichier
                'download_uri' => true,
                'download_label' => 'Télécharger le CV actuel',
                'asset_helper' => true,
            ])
            // --- FIN DU BLOC MODIFIÉ ---

            ->add('lettreMotivation', TextareaType::class, [
                'label' => 'Lettre de motivation *',
                'required' => true,
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Rédigez votre lettre de motivation...',
                ],
            ])
            ->add('motivationCiblee', TextareaType::class, [
                'label' => 'Motivation ciblée',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Motivation spécifique au poste (optionnel)...',
                ],
            ])
            ->add('statutDemande', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'En attente',
                    'En entretien' => 'En entretien',
                    'Acceptée' => 'Acceptée',
                    'Refusée' => 'Refusée',
                ],
                'required' => true,
            ])
            ->add('dateDemande', DateType::class, [
                'label' => 'Date de candidature',
                'widget' => 'single_text',
                'required' => true,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Demande_emploi::class,
        ]);
    }
}