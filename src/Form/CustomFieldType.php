<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class CustomFieldType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('cle', TextType::class, [
                'label' => 'Clé',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'La clé est obligatoire.']),
                    new Assert\Length(['max' => 100, 'maxMessage' => 'Maximum {{ limit }} caractères.']),
                ],
                'attr' => ['placeholder' => 'Nom du champ'],
            ])
            ->add('valeur', TextType::class, [
                'label' => 'Valeur',
                'constraints' => [
                    new Assert\Length(['max' => 255, 'maxMessage' => 'Maximum {{ limit }} caractères.']),
                ],
                'attr' => ['placeholder' => 'Valeur du champ'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null, // No data class, it's an array
        ]);
    }
}