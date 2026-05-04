<?php

namespace App\Form;

use App\Entity\Sprints;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class SprintType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom du sprint',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 100])],
                'attr' => ['placeholder' => 'Ex: Sprint 1 - MVP'],
            ])
            ->add('objectif', TextareaType::class, [
                'label' => 'Objectif',
                'required' => false,
                'attr' => ['rows' => 3, 'placeholder' => 'Objectif principal de ce sprint…'],
            ])
            ->add('date_debut', DateType::class, [
                'label' => 'Date de début',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('date_fin', DateType::class, [
                'label' => 'Date de fin',
                'widget' => 'single_text',
                'constraints' => [new Assert\NotBlank()],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Planifié'  => 'planifie',
                    'Actif'     => 'actif',
                    'Terminé'   => 'termine',
                ],
            ])
            ->add('capacite_equipe', IntegerType::class, [
                'label' => 'Capacité équipe (h)',
                'required' => false,
                'attr' => ['min' => 0, 'placeholder' => '0'],
            ])
            ->add('velocite_prevue', IntegerType::class, [
                'label' => 'Vélocité prévue (pts)',
                'required' => false,
                'attr' => ['min' => 0, 'placeholder' => '0'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Sprints::class]);
    }
}
