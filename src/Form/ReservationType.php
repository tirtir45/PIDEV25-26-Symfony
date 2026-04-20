<?php

namespace App\Form;

use App\Entity\Evenements;
use App\Entity\Reservations;
use App\Entity\Utilisateurs;
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
                'class'        => Evenements::class,
                'choice_label' => fn(Evenements $e): string =>
                    $e->getTitre() . ' [' . $e->getCapacite() . ' places — ' .
                    ($e->getPrix() > 0 ? $e->getPrix() . ' DT' : 'Gratuit') . ']',
                'placeholder'  => '— Sélectionner un événement —',
                'label'        => 'Événement',
                'attr'         => ['class' => 'form-select'],
                'getter'       => fn(Reservations $r) => $r->getEvenement(),
                'setter'       => fn(Reservations $r, $v) => $r->setEvenement($v),
                'constraints'  => [new Assert\NotNull(message: 'Veuillez sélectionner un événement.')],
            ])
            ->add('utilisateur', EntityType::class, [
                'class'        => Utilisateurs::class,
                'choice_label' => fn(Utilisateurs $u): string =>
                    ($u->getNom() ?? '(sans nom)') . ' — ' . ($u->getEmail() ?? ''),
                'placeholder'  => '— Sélectionner un utilisateur —',
                'label'        => 'Utilisateur',
                'attr'         => ['class' => 'form-select'],
                'getter'       => fn(Reservations $r) => $r->getUtilisateur(),
                'setter'       => fn(Reservations $r, $v) => $r->setUtilisateur($v),
                'constraints'  => [new Assert\NotNull(message: 'Veuillez sélectionner un utilisateur.')],
            ])
            ->add('statutPaiement', ChoiceType::class, [
                'label'   => 'Statut du paiement',
                'choices' => [
                    'Payé'       => 'paid',
                    'Gratuit'    => 'free',
                    'En attente' => 'pending',
                ],
                'getter'  => fn(Reservations $r) => $r->getStatutPaiement(),
                'setter'  => fn(Reservations $r, string $v) => $r->setStatutPaiement($v),
                'attr'    => ['class' => 'form-select'],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Reservations::class,
        ]);
    }
}
