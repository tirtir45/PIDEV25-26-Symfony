<?php

namespace App\Form;

use App\Entity\Evenement;
use App\Entity\Reservation;
use App\Entity\Utilisateur;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class ReservationType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('evenement', EntityType::class, [
                'class'        => Evenement::class,
                'choice_label' => fn(Evenement $e): string =>
                    $e->getTitre() . ' [' . $e->getCapacite() . ' places — ' .
                    ($e->getPrix() > 0 ? $e->getPrix() . ' DT' : 'Gratuit') . ']',
                'placeholder'  => '— Sélectionner un événement —',
                'label'        => 'Événement',
                'attr'         => ['class' => 'form-select'],
                'constraints'  => [new Assert\NotNull(message: 'Veuillez sélectionner un événement.')],
            ])
            ->add('utilisateur', EntityType::class, [
                'class'        => Utilisateur::class,
                'choice_label' => fn(Utilisateur $u): string =>
                    ($u->getNom() ?? '(sans nom)') . ' — ' . ($u->getEmail() ?? ''),
                'placeholder'  => '— Sélectionner un utilisateur —',
                'label'        => 'Utilisateur',
                'attr'         => ['class' => 'form-select'],
                'constraints'  => [new Assert\NotNull(message: 'Veuillez sélectionner un utilisateur.')],
            ])
            ->add('statutPaiement', ChoiceType::class, [
                'label'   => 'Statut du paiement',
                'choices' => [
                    '✅ Payé'       => 'paid',
                    '🆓 Gratuit'    => 'free',
                    '⏳ En attente' => 'pending',
                ],
                'attr'    => ['class' => 'form-select'],
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
