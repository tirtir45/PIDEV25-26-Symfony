<?php

namespace App\Form;

use App\Entity\Ressources;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RessourceType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Le nom de ressource est obligatoire']),
                    new Assert\Length(['min' => 3, 'minMessage' => 'Le nom doit contenir au moins 3 caractères']),
                ]
            ])
            ->add('offre', ChoiceType::class, [
                'required' => true,
                'empty_data' => '',
                'choices' => [
                    'Achat' => 'achat',
                    'Location' => 'louer',
                ],
                'placeholder' => 'Choisir une offre',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez choisir une offre']),
                ]
            ])
            ->add('type_r', ChoiceType::class, [
                'required' => true,
                'empty_data' => '',
                'choices' => [
                    'Materiel' => 'materiel',
                    'Service' => 'service',
                    'Espace' => 'espace',
                    'Equipement' => 'equipement',
                ],
                'placeholder' => 'Choisir un type',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez choisir un type']),
                ]
            ])
            ->add('image_r', TextType::class, [
                'required' => false,
                'empty_data' => '',
            ])
            ->add('prix_achat', NumberType::class, [
                'required' => false,
                'empty_data' => '0',
                'data' => '0',
                'constraints' => [
                    new Assert\PositiveOrZero(['message' => 'Le prix doit être positif']),
                ]
            ])
            ->add('disponibilite', CheckboxType::class, [
                'required' => false,
            ])
            ->add('prix_louer', NumberType::class, [
                'required' => false,
                'empty_data' => '0',
                'data' => '0',
                'constraints' => [
                    new Assert\PositiveOrZero(['message' => 'Le prix doit être positif']),
                ]
            ])
            ->add('unite_louer', ChoiceType::class, [
                'choices' => [
                    'Mois' => 'mois',
                    'Semaine' => 'semaine',
                    'Heure' => 'heure',
                    'Jour' => 'jour',
                ],
                'placeholder' => 'Choisir une unite',
                'required' => false,
                'empty_data' => '',
            ])
            ->add('quantite', IntegerType::class, [
                'required' => true,
                'empty_data' => '0',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La quantité est obligatoire']),
                    new Assert\GreaterThanOrEqual(['value' => 0, 'message' => 'La quantité doit être positive']),
                ]
            ])
            ->add('etat', ChoiceType::class, [
                'required' => true,
                'empty_data' => '',
                'choices' => [
                    'Neuf' => 'neuf',
                    'Deuxieme main' => 'deuxieme main',
                    'Ancien' => 'ancien',
                ],
                'placeholder' => 'Choisir un etat',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez choisir un état']),
                ]
            ])
            ->add('description', TextareaType::class, [
                'required' => true,
                'empty_data' => '',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La description est obligatoire']),
                    new Assert\Length(['min' => 10, 'minMessage' => 'La description doit contenir au moins 10 caractères']),
                ]
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Ressources::class,
        ]);
    }
}
