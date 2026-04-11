<?php
// src/Form/ProjetValidationType.php

namespace App\Form;

use App\Entity\Projets;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class ProjetValidationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('commentaire_admin', TextareaType::class, [
                'label' => 'Commentaire',
                'required' => false,
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 4,
                    'placeholder' => 'Ajoutez un commentaire pour l\'entrepreneur...'
                ]
            ])
            ->add('action', ChoiceType::class, [
                'label' => 'Décision',
                'choices' => [
                    'Accepter le projet' => 'accepter',
                    'Refuser le projet' => 'refuser'
                ],
                'expanded' => true,
                'mapped' => false,  // IMPORTANT: Cette ligne empêche Symfony de chercher la propriété "action" dans l'entité Projet
                'attr' => ['class' => 'decision-radio']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projets::class,
        ]);
    }
}