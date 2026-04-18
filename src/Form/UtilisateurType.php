<?php

namespace App\Form;

use App\Entity\Utilisateurs;
use App\Entity\Roles;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class UtilisateurType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isEdit = $options['is_edit'] ?? false;

        $builder
            ->add('nom', TextType::class, ['label' => 'Nom complet', 'required' => true])
            ->add('email', EmailType::class, ['label' => 'Email', 'required' => true])
            ->add('motDePasse', PasswordType::class, [
                'label' => 'Mot de passe',
                'required' => !$isEdit,
                'mapped' => false,
                'constraints' => $isEdit ? [
                    new Assert\Length(
                        min: 6,
                        max: 255,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Assert\Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.'
                    ),
                ] : [
                    new Assert\NotBlank(message: 'Le mot de passe est obligatoire.'),
                    new Assert\Length(
                        min: 6,
                        max: 255,
                        minMessage: 'Le mot de passe doit contenir au moins {{ limit }} caractères.',
                        maxMessage: 'Le mot de passe ne peut pas dépasser {{ limit }} caractères.'
                    ),
                    new Assert\Regex(
                        pattern: '/^(?=.*[a-z])(?=.*[A-Z])(?=.*\d).+$/',
                        message: 'Le mot de passe doit contenir au moins une majuscule, une minuscule et un chiffre.'
                    ),
                ],
            ])
            ->add('telephone', TelType::class, ['label' => 'Téléphone', 'required' => false])
            ->add('role', EntityType::class, [
                'class' => Roles::class,
                'choice_label' => 'nomRole',
                'label' => 'Rôle',
                'required' => false,
                'placeholder' => '-- Choisir un rôle --',
                'query_builder' => function (\Doctrine\ORM\EntityRepository $er) {
                    return $er->createQueryBuilder('r')
                        ->where('r.nomRole IN (:roles)')
                        ->setParameter('roles', ['Candidat', 'Membre equipe', 'Fournisseur', 'Entrepreneur'])
                        ->orderBy('r.nomRole', 'ASC');
                },
            ])
            ->add('bio', TextareaType::class, ['label' => 'Bio', 'required' => false])
            ->add('competences', TextareaType::class, ['label' => 'Compétences', 'required' => false])
            ->add('actif', CheckboxType::class, ['label' => 'Compte actif', 'required' => false]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Utilisateurs::class,
            'is_edit' => false,
        ]);
    }
}