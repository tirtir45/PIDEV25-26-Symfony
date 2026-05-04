<?php

namespace App\Form;

use App\Entity\Projets;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\UrlType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre du projet',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex: Application mobile pour la santé'],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => ['class' => 'form-control', 'rows' => 5],
            ])
            ->add('secteur', ChoiceType::class, [
                'label'   => 'Secteur',
                'choices' => [
                    'Technologie' => 'Technologie',
                    'Santé'       => 'Santé',
                    'Écologie'    => 'Écologie',
                    'Finance'     => 'Finance',
                    'Éducation'   => 'Éducation',
                    'Commerce'    => 'Commerce',
                    'Artisanat'   => 'Artisanat',
                    'Agriculture' => 'Agriculture',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('objectifs', TextareaType::class, [
                'label'    => 'Objectifs',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('budget_estime', NumberType::class, [
                'label'    => 'Budget estimé (€)',
                'required' => false,
                'scale'    => 2,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Ex: 50000'],
            ])
            ->add('duree_estimee', IntegerType::class, [
                'label'    => 'Durée estimée (mois)',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Ex: 12', 'min' => 1],
            ])
            ->add('nb_membres_equipe', IntegerType::class, [
                'label'    => 'Nombre de membres',
                'mapped'   => false,
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'Ex: 5', 'min' => 1],
            ])
            ->add('statut_juridique', ChoiceType::class, [
                'label'       => 'Statut juridique',
                'required'    => false,
                'placeholder' => '-- Sélectionner --',
                'choices'     => [
                    'Auto-entrepreneur'  => 'Auto-entrepreneur',
                    'SARL'               => 'SARL',
                    'SA'                 => 'SA',
                    'SAS'                => 'SAS',
                    'Association'        => 'Association',
                    'Non encore défini'  => 'Non encore défini',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('financement_actuel', ChoiceType::class, [
                'label'       => 'Financement actuel',
                'required'    => false,
                'placeholder' => '-- Sélectionner --',
                'choices'     => [
                    'Fonds propres'        => 'Fonds propres',
                    'Investisseurs'        => 'Investisseurs',
                    'Subventions'          => 'Subventions',
                    'Crowdfunding'         => 'Crowdfunding',
                    'Prêt bancaire'        => 'Prêt bancaire',
                    'Pas de financement'   => 'Pas de financement',
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('site_web', UrlType::class, [
                'label'          => 'Site web',
                'required'       => false,
                'default_protocol' => 'https',
                'attr'           => ['class' => 'form-control', 'placeholder' => 'https://monprojet.com'],
            ])
            ->add('experiences_anterieures', TextareaType::class, [
                'label'    => 'Expériences antérieures',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('partenaires_potentiels', TextareaType::class, [
                'label'    => 'Partenaires potentiels',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('email_contact', TextType::class, [
                'label'    => 'Email de contact',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => 'contact@monprojet.com'],
            ])
            ->add('telephone_contact', TextType::class, [
                'label'    => 'Téléphone de contact',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'placeholder' => '+216 XX XXX XXX'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projets::class,
        ]);
    }
}
