<?php

namespace App\Form;

use App\Entity\Lignes_commande;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Lignes_commandeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('id_commande')
            ->add('id_ressource')
            ->add('quantite')
            ->add('date_deb')
            ->add('date_fin')
            ->add('prix_ligne')
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Lignes_commande::class,
        ]);
    }
}
