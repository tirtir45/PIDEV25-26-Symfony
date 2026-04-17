<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // dateReservation is NOT in the form — it is set automatically to now() in the controller
        // This matches Java's AjouterReservationController which shows date as a read-only label
        $builder
            ->add('evenement', EntityType::class, [
                'class'         => Evenement::class,
                'choice_label'  => function (Evenement $e): string {
                    return $e->getTitre() . ' [' . $e->getCapacite() . ' places]';
                },
                'placeholder'   => '— Sélectionner un événement —',
                'label'         => 'Événement',
                'attr'          => ['class' => 'form-select'],
                'constraints'   => [new Assert\NotNull(message: 'Veuillez sélectionner un événement.')],
            ])
            ->add('utilisateur', EntityType::class, [
                'class'         => Utilisateur::class,
                'choice_label'  => function (Utilisateur $u): string {
                    return ($u->getNom() ?? '(sans nom)') . ' — ' . ($u->getEmail() ?? '');
                },
                'placeholder'   => '— Sélectionner un utilisateur —',
                'label'         => 'Utilisateur',
                'attr'          => ['class' => 'form-select'],
                'constraints'   => [new Assert\NotNull(message: 'Veuillez sélectionner un utilisateur.')],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservation::class,
        ]);
    }
}
