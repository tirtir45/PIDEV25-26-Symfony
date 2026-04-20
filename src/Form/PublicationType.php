<?php

namespace App\Form;

use App\Entity\Publications;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class PublicationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('titre', TextType::class, [
                'label' => 'Titre',
                'attr'  => ['placeholder' => 'Entrez le titre de la publication'],
                'getter' => fn(Publications $p) => $p->getTitre(),
                'setter' => fn(Publications $p, ?string $v) => $p->setTitre($v),
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'attr'  => ['rows' => 5, 'placeholder' => 'Détails de la publication'],
                'getter' => fn(Publications $p) => $p->getDescription(),
                'setter' => fn(Publications $p, ?string $v) => $p->setDescription($v),
            ])
            ->add('type_contrat', ChoiceType::class, [
                'label'   => 'Type de contrat',
                'choices' => [
                    'CDI' => 'CDI', 'CDD' => 'CDD', 'Stage' => 'Stage',
                    'Freelance' => 'Freelance', 'Alternance' => 'Alternance',
                ],
                'getter' => fn(Publications $p) => $p->getType_contrat(),
                'setter' => fn(Publications $p, ?string $v) => $p->setType_contrat($v),
            ])
            ->add('departement', TextType::class, [
                'label'  => 'Département',
                'getter' => fn(Publications $p) => $p->getDepartement(),
                'setter' => fn(Publications $p, ?string $v) => $p->setDepartement($v),
            ])
            ->add('localisation', TextType::class, [
                'label' => 'Localisation',
                'attr'  => ['placeholder' => 'Ex: Tunis, Sfax...'],
                'getter' => fn(Publications $p) => $p->getLocalisation(),
                'setter' => fn(Publications $p, ?string $v) => $p->setLocalisation($v),
            ])
            ->add('date_publication', DateType::class, [
                'label'  => 'Date de publication',
                'widget' => 'single_text',
                'getter' => fn(Publications $p) => $p->getDate_publication(),
                'setter' => fn(Publications $p, $v) => $p->setDate_publication($v),
            ])
            ->add('date_expiration', DateType::class, [
                'label'  => "Date d'expiration",
                'widget' => 'single_text',
                'getter' => fn(Publications $p) => $p->getDate_expiration(),
                'setter' => fn(Publications $p, $v) => $p->setDate_expiration($v),
            ])
            ->add('statut', ChoiceType::class, [
                'label'   => 'Statut',
                'choices' => [
                    'Active' => 'active', 'Inactive' => 'inactive', 'Expirée' => 'expired',
                ],
                'getter' => fn(Publications $p) => $p->getStatut(),
                'setter' => fn(Publications $p, ?string $v) => $p->setStatut($v),
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Publications::class]);
    }
}
