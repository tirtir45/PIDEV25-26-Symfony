<?php
// src/Form/MembreEquipeType.php

namespace App\Form;

use App\Entity\Membres_equipe;
use App\Entity\Projets;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class MembreEquipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $em        = $options['em'];
        $projet    = $options['projet'];
        $membreObj = $options['membre'] ?? null;

        // Only entrepreneurs and candidates can be added
        $eligibleUsers = $em->getRepository(Utilisateurs::class)
            ->createQueryBuilder('u')
            ->join('u.role', 'r')
            ->where('r.nomRole IN (:roles)')
            ->setParameter('roles', ['Entrepreneur', 'Candidat'])
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();

        // Exclude already-added members (except the one being edited)
        $existingMemberIds = [];
        if ($projet) {
            $existingMembers = $em->getRepository(Membres_equipe::class)->findBy(['id_projet' => $projet]);
            foreach ($existingMembers as $m) {
                if ($membreObj && $m->getIdMembre() === $membreObj->getIdMembre()) continue;
                if ($m->getIdUtilisateur()) {
                    $existingMemberIds[] = $m->getIdUtilisateur()->getId();
                }
            }
        }

        $userChoices = [];
        foreach ($eligibleUsers as $user) {
            if (in_array($user->getId(), $existingMemberIds)) continue;
            $label                = $user->getNom() . ' (' . $user->getRole() . ')';
            $userChoices[$label]  = $user->getId();
        }

        if (!$membreObj) {
            // Add mode: show user selector
            $builder->add('id_utilisateur', ChoiceType::class, [
                'label'       => 'Membre à inviter',
                'choices'     => $userChoices,
                'mapped'      => false,
                'placeholder' => '— Sélectionnez un utilisateur —',
                'constraints' => [
                    new Assert\NotBlank(['message' => 'Veuillez sélectionner un utilisateur.']),
                ],
            ]);
        }

        $builder->add('role_equipe', TextType::class, [
            'label'       => 'Rôle dans l\'équipe',
            'mapped'      => false,
            'constraints' => [
                new Assert\NotBlank(['message' => 'Le rôle est obligatoire.']),
                new Assert\Length(['max' => 100, 'maxMessage' => 'Maximum {{ limit }} caractères.']),
            ],
            'attr' => [
                'placeholder' => 'Ex: Développeur, Designer, Chef de projet…',
            ],
            'data' => $membreObj ? $membreObj->getRoleEquipe() : null,
        ]);

        // Custom fields — dynamic key-value pairs stored as JSON
        $builder->add('champs_personnalises', CollectionType::class, [
            'entry_type'    => CustomFieldType::class,
            'allow_add'     => true,
            'allow_delete'  => true,
            'mapped'        => false,
            'label'         => 'Champs personnalisés',
            'required'      => false,
            'by_reference'  => false,
            'attr'          => ['class' => 'custom-fields-collection'],
        ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => null,
            'membre'     => null,
        ]);
        $resolver->setRequired(['em', 'projet']);
        $resolver->setAllowedTypes('em', EntityManagerInterface::class);
        $resolver->setAllowedTypes('projet', [Projets::class, 'null']);
        $resolver->setAllowedTypes('membre', [Membres_equipe::class, 'null']);
    }
}