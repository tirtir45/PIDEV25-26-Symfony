<?php
// src/Form/ProjetType.php

namespace App\Form;

use App\Entity\Projets;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
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
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Application mobile pour la santé']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 5, 'placeholder' => 'Décrivez votre projet en détail...']
            ])
            ->add('secteur', ChoiceType::class, [
                'label' => 'Secteur',
                'choices' => [
                    'Technologie' => 'Technologie',
                    'Santé' => 'Santé',
                    'Écologie' => 'Écologie',
                    'Finance' => 'Finance',
                    'Éducation' => 'Éducation',
                    'Commerce' => 'Commerce',
                    'Artisanat' => 'Artisanat',
                    'Agriculture' => 'Agriculture'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('objectifs', TextareaType::class, [
                'label' => 'Objectifs',
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Quels sont vos objectifs ?']
            ])
            ->add('budget_estime', MoneyType::class, [
                'label' => 'Budget estimé (€)',
                'currency' => 'EUR',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 50000']
            ])
            ->add('duree_estimee', IntegerType::class, [
                'label' => 'Durée estimée (mois)',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 6', 'min' => 1, 'max' => 36]
            ])
            ->add('nb_membres_equipe', IntegerType::class, [
                'label' => 'Nombre de membres dans l\'équipe',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: 3', 'min' => 1, 'max' => 50]
            ])
            ->add('statut_juridique', ChoiceType::class, [
                'label' => 'Statut juridique',
                'choices' => [
                    'Auto-entrepreneur' => 'Auto-entrepreneur',
                    'EURL' => 'EURL',
                    'SASU' => 'SASU',
                    'SAS' => 'SAS',
                    'SARL' => 'SARL',
                    'Entreprise individuelle' => 'Entreprise individuelle',
                    'Association' => 'Association',
                    'Autre' => 'Autre'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('financement_actuel', ChoiceType::class, [
                'label' => 'Financement actuel',
                'choices' => [
                    'Autofinancement' => 'Autofinancement',
                    'Prêt bancaire' => 'Prêt bancaire',
                    'Investisseurs' => 'Investisseurs',
                    'Crowdfunding' => 'Crowdfunding',
                    'Subventions' => 'Subventions',
                    'Aucun financement' => 'Aucun financement'
                ],
                'attr' => ['class' => 'form-select']
            ])
            ->add('partenaires_potentiels', TextareaType::class, [
                'label' => 'Partenaires potentiels',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 2, 'placeholder' => 'Décrivez vos partenaires potentiels...']
            ])
            ->add('email_contact', EmailType::class, [
                'label' => 'Email de contact',
                'attr' => ['class' => 'form-control', 'placeholder' => 'contact@entreprise.com']
            ])
            ->add('telephone_contact', TelType::class, [
                'label' => 'Téléphone de contact',
                'attr' => ['class' => 'form-control', 'placeholder' => '06 12 34 56 78']
            ])
            ->add('site_web', UrlType::class, [
                'label' => 'Site web',
                'required' => false,
                'attr' => ['class' => 'form-control', 'placeholder' => 'https://www.monsite.com']
            ])
            ->add('experiences_anterieures', TextareaType::class, [
                'label' => 'Expériences antérieures',
                'required' => false,
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Décrivez vos expériences dans le domaine...']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projets::class,
        ]);
    }
}