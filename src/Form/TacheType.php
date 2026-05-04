<?php

namespace App\Form;

use App\Entity\Taches;
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
        $membres = $options['membres'] ?? [];

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
                'label' => 'Titre de la tâche',
                'attr'  => ['class' => 'form-control', 'placeholder' => 'Ex: Développer la page d\'accueil'],
            ])
            ->add('description', TextareaType::class, [
                'label'    => 'Description',
                'required' => false,
                'attr'     => ['class' => 'form-control', 'rows' => 3],
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'À faire'  => Taches::STATUT_A_FAIRE,
                    'En cours' => Taches::STATUT_EN_COURS,
                    'Terminée' => Taches::STATUT_TERMINEE,
                ],
                'attr' => ['class' => 'form-control'],
            ])
            ->add('priorite', ChoiceType::class, [
                'label'    => 'Priorité',
                'required' => false,
                'choices'  => [
                    'Haute'   => 'haute',
                    'Moyenne' => 'moyenne',
                    'Basse'   => 'basse',
                ],
                'placeholder' => '-- Priorité --',
                'attr'        => ['class' => 'form-control'],
            ])
            ->add('date_limite', DateType::class, [
                'label'    => 'Date limite',
                'widget'   => 'single_text',
                'required' => false,
                'attr'     => ['class' => 'form-control'],
            ])
            ->add('id_responsable', ChoiceType::class, [
                'label'    => 'Responsable',
                'required' => false,
                'choices'  => $assigneeChoices,
                'mapped'   => false,
                'attr'     => ['class' => 'form-control'],
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
