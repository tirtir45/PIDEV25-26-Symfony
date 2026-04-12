<?php

namespace App\Form;

use App\Entity\Publication;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;

class PublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr' => ['placeholder' => 'Entrez le titre de la publication']
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr' => ['rows' => 5, 'placeholder' => 'Détails de la publication']
            ])
            ->add('type_contrat', ChoiceType::class, [
                'label' => 'Type de contrat',
                'choices' => [
                    'CDI' => 'CDI',
                    'CDD' => 'CDD',
                    'Stage' => 'Stage',
                    'Freelance' => 'Freelance',
                    'Alternance' => 'Alternance',
                ],
                'getter' => function (Publication $publication) {
                    return $publication->getType_contrat();
                },
                'setter' => function (Publication $publication, ?string $type) {
                    $publication->setType_contrat($type);
                },
            ])
            ->add('departement', TextType::class, [
                'label' => 'Département',
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'attr' => ['placeholder' => 'Ex: Paris, Lyon...']
            ])
            ->add('date_publication', DateType::class, [
                'label' => 'Date de publication',
                'widget' => 'single_text',
                 'getter' => function (Publication $publication) {
                    return $publication->getDate_publication();
                },
                // On force le formulaire à écrire avec ta méthode :
                'setter' => function (Publication $publication, ?\DateTimeInterface $date) {
                    $publication->setDate_publication($date);
                },
            ])
            ->add('date_expiration', DateType::class, [
                'label' => 'Date d\'expiration',
                'widget' => 'single_text',
                 'getter' => function (Publication $publication) {
                    return $publication->getDate_expiration();
                },
                'setter' => function (Publication $publication, ?\DateTimeInterface $date) {
                    $publication->setDate_expiration($date);
                },
            ])
            ->add('statut', ChoiceType::class, [
                'label' => 'Statut',
                'choices' => [
                    'Active' => 'active',
                    'Inactive' => 'inactive',
                    'Expirée' => 'expired',
                ],
            ])
            
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Publication::class,
        ]);
    }
}