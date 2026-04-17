<?php

namespace App\Form;

use App\Entity\Projets;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
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
                'attr' => ['class' => 'form-control']
            ])
            ->add('objectifs', TextareaType::class, [
                'label' => 'Objectifs',
                'attr' => ['class' => 'form-control', 'rows' => 3, 'placeholder' => 'Quels sont vos objectifs ?']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Projets::class,
        ]);
    }
}