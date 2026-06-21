<?php

namespace App\Form\App;

use App\Entity\Category;
use App\Enum\ColorEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class CategoryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'label' => 'Nom de la catégorie',
                'attr' => [
                    'placeholder' => 'Nom de la catégorie',
                    'class' => 'form-control',
                ],
            ])
            ->add('description', TextareaType::class, [
                'label' => 'Description',
                'required' => false,
                'attr' => [
                    'placeholder' => 'Description de la catégorie',
                    'class' => 'form-control',
                    'rows' => 3,
                ],
            ])
            ->add('color', EnumType::class, [
                'class' => ColorEnum::class,
                'expanded' => false,
                'multiple' => false,
                'required' => false,
                'label' => 'Couleur',

                // ⚠️ Par défaut EnumType utilise le nom du case comme value (<option value="Blue">).
                // On force la valeur string de l'enum (ex: "blue") pour que CustomSelector
                // puisse synchroniser le <select> natif via data-value="blue".
                'choice_value' => fn(?ColorEnum $color): string => $color?->value ?? '',

                'choice_attr' => function (ColorEnum $color): array {
                    return [
                        'data-color' => $color->value,
                    ];
                },
            ])
            ->add('slug', TextType::class, [
                'label' => 'Slug',
                'required' => false,
                'attr' => [
                    'placeholder' => 'slug-de-la-categorie',
                    'class' => 'form-control',
                ],
                'help' => 'Laissez vide pour générer automatiquement.',
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
            'data_class' => Category::class,
        ]);
    }
}