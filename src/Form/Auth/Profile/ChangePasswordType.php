<?php

namespace App\Form\Auth\Profile;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Security\Core\Validator\Constraints\UserPassword;
use Symfony\Component\Validator\Constraints\NotBlank;
use Symfony\Component\Validator\Constraints\Regex;

final class ChangePasswordType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('current_password', PasswordType::class, [
                'label' => 'Mot de passe actuel',
                'mapped' => false, // 👈 Très important : on ne lie pas ce champ à l'entité
                'row_attr' => [
                    'class' => 'form-group',
                ],
                'constraints' => [
                    new NotBlank(message: 'Veuillez saisir votre mot de passe actuel !'),
                    // 🔥 La magie Symfony opère ici :
                    new UserPassword(message: 'Le mot de passe actuel est incorrect.'), 
                ],
            ])
            ->add('new_password', RepeatedType::class, [
                'type' => PasswordType::class,
                'invalid_message' => 'Les deux mots de passes doivent être identiques !',
                'row_attr' => [
                    'class' => 'form-group',
                ],
                'required' => true,
                'first_options' => [
                    'label' => 'Nouveau mot de passe',
                    'row_attr' => [
                        'class' => 'form-group',
                    ],
                ],
                'second_options' => [
                    'label' => 'Confirmer le nouveau mot de passe',
                    'row_attr' => [
                        'class' => 'form-group',
                    ],
                ],
                'constraints' => [
                    new Regex(
                        '#^(?=.*[a-z])(?=.*[A-Z])(?=.*[0-9]){8,}#',
                        'Le mot de passe doit contenir au moins une masjuscule, une minuscule et un chiffre avec au moins 8 caractères !',
                        null,
                        true
                    ),

                    new NotBlank(message: 'Ce champ est obligatoire !')
                ],
            ])
            ->add('send', SubmitType::class, [
                'label' => 'Changer le mot de passe',
                'attr' => [
                    'class' => 'btn btn-primary'
                ],
                'row_attr' => [
                    'class' => 'form-group'
                ]
            ])
        ;
    }


    /**
     * @param OptionsResolver $resolver
     * 
     * @return void
     */
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            // Configure your form options here
        ]);
    }
}
