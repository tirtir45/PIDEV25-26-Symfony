<?php

namespace App\Form;

use App\Entity\RessourceProjet;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RessourceProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom de la ressource',
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['max' => 100])],
                'attr' => ['placeholder' => 'Ex: Salle de réunion A'],
            ])
            ->add('type', ChoiceType::class, [
                'label' => 'Type',
                'choices' => [
                    'Matériel'  => 'materiel',
                    'Logiciel'  => 'logiciel',
                    'Salle'     => 'salle',
                    'Véhicule'  => 'vehicule',
                    'Autre'     => 'autre',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => ['rows' => 3],
            ])
            ->add('emplacement', TextType::class, [
                'label' => 'Emplacement',
                'required' => false,
                'attr' => ['placeholder' => 'Ex: Bâtiment B, 2ème étage'],
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Disponible'   => RessourceProjet::STATUT_DISPONIBLE,
                    'Occupé'       => RessourceProjet::STATUT_OCCUPE,
                    'Maintenance'  => RessourceProjet::STATUT_MAINTENANCE,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => RessourceProjet::class]);
    }
}
