<?php

namespace App\Form;

use App\Entity\Membres_equipe;
use App\Entity\Utilisateurs;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Improved MembreEquipeType form
 * Handles team member creation and editing with proper validation
 * Only allows entrepreneurs to add team members
 */
class MembreEquipeTypeImproved extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_utilisateur', EntityType::class, [
                'label' => 'Team Member',
                'class' => Utilisateurs::class,
                'choice_label' => function(Utilisateurs $user) {
                    return $user->getNom() . ' (' . $user->getEmail() . ')';
                },
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Select an existing member or create a new one'
                ],
                'constraints' => [
                    new Assert\NotNull([
                        'message' => 'Please select a team member.'
                    ])
                ]
            ])
            ->add('role_equipe', TextType::class, [
                'label' => 'Role in Team',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Frontend Developer, Designer, Project Manager'
                ],
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 100,
                        'maxMessage' => 'The role cannot exceed {{ limit }} characters.'
                    ])
                ]
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Membres_equipe::class,
            'validation_groups' => ['team_member_form']
        ]);
    }
}
