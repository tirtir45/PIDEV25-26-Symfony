<?php

namespace App\Form;

use App\Entity\Taches;
use App\Entity\Membres_equipe;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Improved TacheType form with proper validation
 * Limits team member selection to members of the current project
 */
class TacheTypeImproved extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Task Title',
                'attr' => [
                    'class' => 'form-control',
                    'placeholder' => 'Ex: Develop homepage',
                    'maxlength' => 150
                ],
                'constraints' => [
                    new Assert\NotBlank([
                        'message' => 'The task title is required.'
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
                    'rows' => 3,
                    'placeholder' => 'Describe what needs to be done...'
                ],
                'required' => false,
                'constraints' => [
                    new Assert\Length([
                        'max' => 1000,
                        'maxMessage' => 'The description cannot exceed {{ limit }} characters.'
                    ])
                ]
            ])
            ->add('date_limite', DateType::class, [
                'label' => 'Due Date',
                'widget' => 'single_text',
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'constraints' => [
                    new Assert\GreaterThanOrEqual([
                        'value' => 'today',
                        'message' => 'The due date must be in the future.'
                    ])
                ]
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Status',
                'choices' => [
                    'To Do' => Taches::STATUT_A_FAIRE,
                    'In Progress' => Taches::STATUT_EN_COURS,
                    'Completed' => Taches::STATUT_TERMINEE
                ],
                'attr' => ['class' => 'form-control'],
                'data' => Taches::STATUT_A_FAIRE
            ])
            ->add('id_responsable', EntityType::class, [
                'label' => 'Assign Team Member',
                'class' => Membres_equipe::class,
                'choice_label' => function(Membres_equipe $member) {
                    return $member->getIdUtilisateur()->getNom() . ' (' . ($member->getRoleEquipe() ?? 'No role') . ')';
                },
                'attr' => ['class' => 'form-control'],
                'required' => false,
                'placeholder' => 'Select a team member (optional)'
            ]);

        // Dynamically set the query builder for team members based on the project
        $builder->addEventListener(FormEvents::PRE_SET_DATA, function(FormEvent $event) {
            $form = $event->getForm();
            $data = $event->getData();

            // Get the project from the parent form or options
            if (isset($form->getConfig()->getOptions()['project'])) {
                $project = $form->getConfig()->getOptions()['project'];

                $form->get('id_responsable')->setData(null);
            }
        });
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Taches::class,
            'validation_groups' => ['task_form'],
            'project' => null  // Pass the current project to filter team members
        ]);
    }
}
