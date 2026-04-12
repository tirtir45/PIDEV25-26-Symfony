<?php

namespace App\Form;

use App\Entity\Utilisateur;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class RegisterType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('nom', TextType::class, [
                'label' => 'Nom complet',
                'attr'  => ['placeholder' => 'Votre nom', 'class' => 'form-control'],
                'constraints' => [new Assert\NotBlank(), new Assert\Length(['min' => 2, 'max' => 255])],
            ])
            ->add('email', EmailType::class, [
                'label' => 'Email',
                'attr'  => ['placeholder' => 'email@example.com', 'class' => 'form-control'],
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ])
            ->add('motDePasse', RepeatedType::class, [
                'type'            => PasswordType::class,
                'mapped'          => true,
                'first_options'   => [
                    'label' => 'Mot de passe',
                    'attr'  => ['placeholder' => 'Minimum 6 caractères', 'class' => 'form-control'],
                    'constraints' => [new Assert\NotBlank(), new Assert\Length(['min' => 6])],
                ],
                'second_options'  => [
                    'label' => 'Confirmer le mot de passe',
                    'attr'  => ['placeholder' => 'Répétez le mot de passe', 'class' => 'form-control'],
                ],
                'invalid_message' => 'Les mots de passe ne correspondent pas.',
            ])
            ->add('telephone', TextType::class, [
                'label'    => 'Téléphone',
                'required' => false,
                'attr'     => ['placeholder' => '+216 XX XXX XXX', 'class' => 'form-control'],
            ])
            ->add('idRole', ChoiceType::class, [
                'label' => 'Type de compte',
                'choices' => [
                    'Administrateur' => Utilisateur::ROLE_ADMIN_ID,
                    'Entrepreneur' => Utilisateur::ROLE_ENTREPRENEUR_ID,
                    'Candidat' => Utilisateur::ROLE_CANDIDAT_ID,
                ],
                'expanded' => false,
                'multiple' => false,
                'attr' => ['class' => 'form-control'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults(['data_class' => Utilisateur::class]);
    }
}