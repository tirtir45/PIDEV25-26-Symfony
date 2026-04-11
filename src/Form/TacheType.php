<?php

namespace App\Form;

use App\Entity\Taches;
use App\Entity\Utilisateurs;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TacheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre de la tâche',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Développer la page d\'accueil']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['class' => 'form-control', 'rows' => 3],
                'required' => false
            ])
            ->add('date_limite', DateType::class, [
                'label' => 'Date limite',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'required' => false
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'À faire' => Taches::STATUT_A_FAIRE,
                    'En cours' => Taches::STATUT_EN_COURS,
                    'Terminée' => Taches::STATUT_TERMINEE
                ],
                'attr' => ['class' => 'form-control']
            ])
            ->add('id_responsable', EntityType::class, [
                'label' => 'Responsable',
                'class' => Utilisateurs::class,
                'choice_label' => 'nom',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'placeholder' => 'Sélectionnez un membre'
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Taches::class,
        ]);
    }
}