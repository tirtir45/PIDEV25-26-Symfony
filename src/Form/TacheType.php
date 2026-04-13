<?php
// src/Form/TacheType.php

namespace App\Form;

use App\Entity\Taches;
use App\Entity\Membres_equipe;
use App\Entity\Utilisateurs;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

class TacheType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $membres = $options['membres'] ?? [];

        // Build assignee choices from team members
        $assigneeChoices = ['Non assigné' => null];
        foreach ($membres as $membre) {
            $user = $membre->getIdUtilisateur();
            if ($user) {
                $label = $user->getNom() . ' — ' . ($membre->getRoleEquipe() ?? 'Membre');
                $assigneeChoices[$label] = $user->getId();
            }
        }

        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Titre de la tâche',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le titre est obligatoire.']),
                    new Assert\Length([
                        'min'        => 3,
                        'max'        => 150,
                        'minMessage' => 'Minimum {{ limit }} caractères.',
                        'maxMessage' => 'Maximum {{ limit }} caractères.',
                    ]),
                ],
                'attr' => ['placeholder' => 'Ex: Développer la page d\'accueil'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['rows' => 3, 'placeholder' => 'Décrivez la tâche…'],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'À faire'  => Taches::STATUT_A_FAIRE,
                    'En cours' => Taches::STATUT_EN_COURS,
                    'Terminée' => Taches::STATUT_TERMINEE,
                ],
            ])
            ->add('date_limite', DateType::class, [
                'label'    => 'Date limite',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['min' => (new \DateTime())->format('Y-m-d')],
                'constraints' => [
                    new Assert\Callback(function ($value, ExecutionContextInterface $context) {
                        if ($value !== null && $value < new \DateTime('today')) {
                            $context->buildViolation('La date limite ne peut pas être dans le passé.')
                                ->addViolation();
                        }
                    }),
                ],
            ])
            ->add('id_responsable', ChoiceType::class, [
                'label'    => 'Responsable',
                'required' => false,
                'choices'  => $assigneeChoices,
                'mapped'   => false,
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Taches::class,
            'membres'    => [],
        ]);
        $resolver->setAllowedTypes('membres', 'array');
    }
}