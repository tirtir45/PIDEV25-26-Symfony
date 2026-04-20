<?php

namespace App\Form;

use App\Entity\Evenements;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class EvenementType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isNew = $options['is_new'];

        $dateConstraints = [new Assert\NotBlank(message: 'Le champ « Date » est obligatoire.')];
        if ($isNew) {
            $dateConstraints[] = new Assert\GreaterThan(
                value: new \DateTime('today'),
                message: 'La date doit être dans le futur (après aujourd\'hui).'
            );
        }

        $capaciteConstraints = [new Assert\NotBlank(message: 'Le champ « Capacité » est obligatoire.')];
        if ($isNew) {
            $capaciteConstraints[] = new Assert\Positive(message: 'La capacité doit être supérieure à 0.');
        } else {
            $capaciteConstraints[] = new Assert\PositiveOrZero(message: 'La capacité ne peut pas être négative.');
        }

        $builder
            ->add('titre', TextType::class, [
                'label'       => 'Titre *',
                'attr'        => ['placeholder' => 'Ex: Conférence Innovation 2026'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le champ « Titre » est obligatoire.'),
                    new Assert\Length(['max' => 150]),
                ],
            ])
            ->add('dateEvenement', DateType::class, [
                'label'         => 'Date *',
                'widget'        => 'single_text',
                'property_path' => 'date_evenement',
                'constraints'   => $dateConstraints,
            ])
            ->add('lieu', TextType::class, [
                'label'       => 'Lieu *',
                'attr'        => ['placeholder' => 'Ex: Salle des conférences, Tunis'],
                'constraints' => [new Assert\NotBlank(message: 'Le champ « Lieu » est obligatoire.')],
            ])
            ->add('capacite', IntegerType::class, [
                'label'       => 'Capacité *',
                'attr'        => ['placeholder' => 'Nombre de places', 'min' => $isNew ? 1 : 0],
                'constraints' => $capaciteConstraints,
            ])
            ->add('description', TextareaType::class, [
                'label'       => 'Description *',
                'attr'        => ['placeholder' => 'Décrivez votre événement en détail...', 'rows' => 5],
                'constraints' => [new Assert\NotBlank(message: 'Le champ « Description » est obligatoire.')],
            ])
            ->add('prix', NumberType::class, [
                'label'       => 'Prix (DT) *',
                'scale'       => 2,
                'attr'        => ['placeholder' => 'Ex: 50.00', 'min' => 0, 'step' => '0.01'],
                'constraints' => [
                    new Assert\NotBlank(message: 'Le champ « Prix » est obligatoire.'),
                    new Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif.'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Evenements::class,
            'is_new'     => true,
        ]);
        $resolver->setAllowedTypes('is_new', 'bool');
    }
}
