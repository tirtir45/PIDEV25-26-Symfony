<?php

namespace App\Form;

use App\Entity\Membres_equipe;
use App\Entity\Projets;
use App\Entity\Utilisateurs;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class MembreEquipeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $em        = $options['em'];
        $projet    = $options['projet'];
        $membreObj = $options['membre'] ?? null;

        $eligibleUsers = $em->getRepository(Utilisateurs::class)
            ->createQueryBuilder('u')
            ->join('u.role', 'r')
            ->where('r.nomRole IN (:roles)')
            ->setParameter('roles', ['Entrepreneur', 'Candidat'])
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();

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
            $userChoices[$user->getNom() . ' (' . ($user->getEmail() ?? '') . ')'] = $user->getId();
        }

        if (!$membreObj) {
            $builder->add('id_utilisateur', ChoiceType::class, [
                'label'       => 'Membre à inviter',
                'choices'     => $userChoices,
                'mapped'      => false,
                'placeholder' => '— Sélectionnez un utilisateur —',
                'attr'        => ['class' => 'form-control'],
            ]);
        }

        $builder->add('role_equipe', TextType::class, [
            'label'  => 'Rôle dans l\'équipe',
            'mapped' => false,
            'attr'   => [
                'class'       => 'form-control',
                'placeholder' => 'Ex: Développeur, Designer, Chef de projet…',
            ],
            'data' => $membreObj ? $membreObj->getRoleEquipe() : null,
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
