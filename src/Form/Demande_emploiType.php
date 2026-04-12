<?php

namespace App\Form;

use App\Entity\Demande_emploi;
use App\Entity\Publication;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Demande_emploiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('publication_id', EntityType::class, [
                'class' => Publication::class,
                'choice_label' => 'titre', // ou 'titre' selon votre entité
                'label' => 'Offre concernée *',
                'placeholder' => 'Choisir une offre',
                'required' => true,
            ])
            ->add('candidat_id', EntityType::class, [
                'class' => Utilisateur::class,
                'choice_label' => function (Utilisateur $u) {
                    return $u->getNom() . ' (' . $u->getEmail() . ')';
                },
                'label' => 'Candidat *',
                'placeholder' => 'Choisir un candidat',
                'required' => true,
            ])
            ->add('cv_url', TextType::class, [
                'label' => 'CV (nom du fichier PDF) *',
                'required' => true,
                'attr' => [
                    'placeholder' => 'Ex: cv-jean-dupont.pdf',
                ],
                'help' => 'Entrez le nom du fichier PDF (ex: cv-mon-nom.pdf).',
            ])
            ->add('lettre_motivation', TextareaType::class, [
                'label' => 'Lettre de motivation *',
                'required' => true,
                'attr' => [
                    'rows' => 6,
                    'placeholder' => 'Rédigez votre lettre de motivation...',
                ],
            ])
            ->add('motivation_ciblee', TextareaType::class, [
                'label' => 'Motivation ciblée',
                'required' => false,
                'attr' => [
                    'rows' => 4,
                    'placeholder' => 'Motivation spécifique au poste (optionnel)...',
                ],
            ])
            ->add('statut_demande', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'En attente' => 'En attente',
                    'Acceptée' => 'Acceptée',
                    'Refusée' => 'Refusée',
                ],
                'required' => true,
            ])
            ->add('date_demande', DateType::class, [
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