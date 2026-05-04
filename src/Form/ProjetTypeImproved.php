<?php

namespace App\Form;

use App\Entity\Projets;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Improved ProjetType form with proper validation
 * Handles both create and edit operations
 */
class ProjetTypeImproved extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Project Title',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Mobile application for health management',
                    'maxlength' => 150
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'The project title is required.'
                    ]),
                    new Assert\Length([
                        'min' => 3,
                        'max' => 150,
                        'minMessage' => 'The title must be at least {{ limit }} characters.',
                        'maxMessage' => 'The title cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 5,
                    'placeholder' => 'Describe your project in detail...'
                ],
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 2000,
                        'maxMessage' => 'The description cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('secteur', ChoiceType::class, [
                'label' => 'Sector',
                'choices' => [
                    'Technology' => 'Technologie',
                    'Health' => 'Santé',
                    'Ecology' => 'Écologie',
                    'Finance' => 'Finance',
                    'Education' => 'Éducation',
                    'Commerce' => 'Commerce',
                    'Craftsmanship' => 'Artisanat',
                    'Agriculture' => 'Agriculture',
                    'Other' => 'Autre'
                ],
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'placeholder' => 'Select a sector (optional)'
            ])
            ->add('objectifs', TextareaType::class, [
                'label' => 'Objectives',
                'attr' => [
                    'class' => 'form-control',
                    'rows' => 3,
                    'placeholder' => 'What are your objectives?'
                ],
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'The objectives cannot exceed {{ limit }} characters.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projets::class,
            'validation_groups' => ['project_form']
        ]);
    }
}
