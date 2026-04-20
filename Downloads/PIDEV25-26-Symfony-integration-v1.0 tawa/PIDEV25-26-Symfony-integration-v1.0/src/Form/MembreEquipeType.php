<?php

namespace App\Form;

use App\Entity\Membres_equipe;
use App\Entity\Utilisateurs;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembreEquipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_utilisateur', EntityType::class, [
                'label' => 'Membre',
                'class' => Utilisateurs::class,
                'choice_label' => 'nom',
                'attr' => ['class' => 'form-control'],
                'placeholder' => 'Sélectionnez un membre'
            ])
            ->add('role_equipe', TextType::class, [
                'label' => 'Rôle dans l\'équipe',
                'attr' => ['class' => 'form-control', 'placeholder' => 'Ex: Développeur Frontend']
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Membres_equipe::class,
        ]);
    }
}