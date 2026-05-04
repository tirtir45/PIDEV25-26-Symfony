<?php

namespace App\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class FichierProjetType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('fichier', FileType::class, [
            'label' => 'Fichier',
            'mapped' => false,
            'constraints' => [
                new Assert\NotBlank(['message' => 'Veuillez sélectionner un fichier.']),
                new Assert\File([
                    'maxSize' => '10M',
                    'maxSizeMessage' => 'Le fichier ne doit pas dépasser 10 Mo.',
                ]),
            ],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([]);
    }
}
