<?php

namespace App\Form\Admin\User;

use App\Entity\User;
use App\Security\Enum\RoleEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class UserType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Récupération du premier rôle de l'utilisateur (hors ROLE_USER par défaut)
        $currentRoles = $options['current_roles'] ?? [];
        $defaultRole = null;
        foreach ($currentRoles as $role) {
            if ($role !== 'ROLE_USER') {
                $defaultRole = $role;
                break;
            }
        }

        $builder
            ->add('username', TextType::class, [
                'label' => 'Nom d\'utilisateur',
                'required' => true,
            ])
            ->add('email', TextType::class, [
                'label' => 'Email',
                'required' => true,
            ])
            ->add('roles', ChoiceType::class, [
                'label' => 'Rôles',
                'required' => true,
                'choices' => RoleEnum::choices(),
                'mapped' => false,
                'data' => $defaultRole,
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => User::class,
            'current_roles' => [],
        ]);

        $resolver->setAllowedTypes('current_roles', 'array');
    }
}
