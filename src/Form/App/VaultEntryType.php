<?php
namespace App\Form\App;

use App\Entity\Entry;
use App\Enum\MoodEnum;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\SubmitType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class VaultEntryType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('title', TextType::class, [
                'label' => false,
                'required' => false,
                'attr' => [
                    'placeholder' => 'Titre du souvenir (optionnel)',
                    'maxlength' => 200,
                ],
            ])
            ->add('content', TextareaType::class, [
                'label' => 'Secret',
                'required' => false,
                'attr' => [
                    'rows' => 8,
                    'placeholder' => 'Écrivez votre secret… (Markdown supporté)',
                ],
            ])
            ->add('mood', EnumType::class, [
                'class' => MoodEnum::class,
                'label' => 'Humeur',
                'required' => false,
                'expanded' => true,
                'multiple' => false,
                'choice_label' => fn(?MoodEnum $mood) => $mood ? $mood->getEmoji() : '',
                // 🟢 Ajout de choice_attr pour mapper getLabel() sur l'attribut HTML title
                'choice_attr' => fn(?MoodEnum $mood) => $mood ? ['title' => $mood->getLabel()] : [],
            ])
            ->add('save', SubmitType::class, [
                'label' => 'Sceller le souvenir',
                'attr' => ['class' => 'btn btn-primary'],
            ])
        ;
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Entry::class,
        ]);
    }
}