<?php
 
namespace App\Form\Auth\Profile;
 
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\PasswordType;
use Symfony\Component\Form\Extension\Core\Type\RepeatedType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints\Length;
use Symfony\Component\Validator\Constraints\NotBlank;
 
class ConfidentialCodeType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        // Champ "code actuel" affiché uniquement si un code existe déjà
        if ($options['has_existing_code']) {
            $builder->add('current_code', PasswordType::class, [
                'label'    => 'Code actuel',
                'mapped'   => false,
                'required' => true,
                'attr'     => [
                    'class'          => 'form-control',
                    'autocomplete'   => 'current-password',
                    'placeholder'    => '••••',
                ],
            ]);
        }
 
        $builder
            ->add('new_code', RepeatedType::class, [
                'type'             => PasswordType::class,
                'mapped'           => false,
                'invalid_message'  => 'Les deux codes doivent être identiques.',
                'required'         => true,
                'first_options'    => [
                    'label' => $options['has_existing_code'] ? 'Nouveau code' : 'Définir un code',
                    'attr'  => [
                        'class'        => 'form-control',
                        'autocomplete' => 'new-password',
                        'placeholder'  => '••••',
                        'minlength'    => 4,
                    ],
                    'help'  => 'Minimum 4 caractères.',
                ],
                'second_options'   => [
                    'label' => 'Confirmer le code',
                    'attr'  => [
                        'class'        => 'form-control',
                        'autocomplete' => 'new-password',
                        'placeholder'  => '••••',
                        'minlength'    => 4,
                    ],
                ],
                'constraints'      => [
                    new NotBlank(message: 'Le code ne peut pas être vide.'),
                    new Length(
                        min: 4,
                        minMessage: 'Le code doit contenir au moins {{ limit }} caractères.',
                    ),
                ],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Enregistrer',
                'attr'  => ['class' => 'btn btn-primary'],
            ])
        ;
    }
 
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'has_existing_code' => false,
        ]);
 
        $resolver->setAllowedTypes('has_existing_code', 'bool');
    }
}