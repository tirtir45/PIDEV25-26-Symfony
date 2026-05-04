<?php

namespace App\Form;

use App\Entity\Demande_emplois;
use App\Entity\Publications;
use App\Entity\Utilisateurs;
use Symfony\Bridge\Doctrine\Form\Type\EntityType;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class Demande_emploiType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $isAdmin = $options['is_admin'] ?? false;

        $builder
            ->add('publication', EntityType::class, [
                'class'        => Publications::class,
                'choice_label' => 'titre',
                'label'        => 'Offre concernée *',
                'placeholder'  => 'Choisir une offre',
                'required'     => true,
                'disabled'     => !$isAdmin, // Users can't change the pre-selected offer
                'getter'       => fn(Demande_emplois $d) => $d->getPublication(),
                'setter'       => fn(Demande_emplois $d, $v) => $d->setPublication($v),
            ])
            ->add('cvFile', \Symfony\Component\Form\Extension\Core\Type\FileType::class, [
                'label'    => 'CV (PDF) *',
                'mapped'   => false,
                'required' => $options['require_cv'] ?? true,
                'constraints' => array_filter([
                    new \Symfony\Component\Validator\Constraints\File([
                        'maxSize' => '2048k',
                        'mimeTypes' => ['application/pdf', 'application/x-pdf'],
                        'mimeTypesMessage' => 'Veuillez uploader un fichier PDF valide.',
                    ]),
                    ($options['require_cv'] ?? true)
                        ? new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'Veuillez uploader votre CV.'])
                        : null,
                ]),
            ])
            ->add('lettreMotivation', TextareaType::class, [
                'label'    => 'Lettre de motivation *',
                'required' => true,
                'attr'     => ['rows' => 6, 'placeholder' => 'Rédigez votre lettre de motivation...'],
                'constraints' => [
                    new \Symfony\Component\Validator\Constraints\NotBlank(['message' => 'La lettre de motivation est obligatoire.']),
                    new \Symfony\Component\Validator\Constraints\Length([
                        'min' => 20,
                        'minMessage' => 'La lettre de motivation doit contenir au moins 20 caractères.',
                    ]),
                ],
                'getter'   => fn(Demande_emplois $d) => $d->getLettreMotivation(),
                'setter'   => fn(Demande_emplois $d, ?string $v) => $d->setLettreMotivation($v),
            ])
            ->add('motivationCiblee', TextareaType::class, [
                'label'    => 'Motivation ciblée',
                'required' => false,
                'attr'     => ['rows' => 4, 'placeholder' => 'Motivation spécifique au poste (optionnel)...'],
                'getter'   => fn(Demande_emplois $d) => $d->getMotivationCiblee(),
                'setter'   => fn(Demande_emplois $d, ?string $v) => $d->setMotivationCiblee($v),
            ]);

        // Only admins can pick a candidat and change status
        if ($isAdmin) {
            $builder
                ->add('candidat', EntityType::class, [
                    'class'        => Utilisateurs::class,
                    'choice_label' => fn(Utilisateurs $u) => $u->getNom() . ' (' . $u->getEmail() . ')',
                    'label'        => 'Candidat *',
                    'placeholder'  => 'Choisir un candidat',
                    'required'     => true,
                    'getter'       => fn(Demande_emplois $d) => $d->getCandidat(),
                    'setter'       => fn(Demande_emplois $d, $v) => $d->setCandidat($v),
                ])
                ->add('statutDemande', ChoiceType::class, [
                    'label'   => 'Statut',
                    'choices' => [
                        'En attente'   => 'En attente',
                        'En entretien' => 'En entretien',
                        'Acceptée'     => 'Acceptée',
                        'Refusée'      => 'Refusée',
                    ],
                    'required' => true,
                    'getter'   => fn(Demande_emplois $d) => $d->getStatutDemande(),
                    'setter'   => fn(Demande_emplois $d, string $v) => $d->setStatutDemande($v),
                ])
                ->add('dateDemande', DateType::class, [
                    'label'  => 'Date de candidature',
                    'widget' => 'single_text',
                    'getter' => fn(Demande_emplois $d) => $d->getDateDemande(),
                    'setter' => fn(Demande_emplois $d, $v) => $d->setDateDemande($v),
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class'  => Demande_emplois::class,
            'is_admin'    => false,
            'require_cv'  => true,
        ]);
    }
}
