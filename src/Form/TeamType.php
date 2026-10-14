<?php

namespace App\Form;

use App\Entity\Team;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\HiddenType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;

class TeamType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('name', TextType::class, ['empty_data' => '']);

        if ($options['with_pokemon']) {
            // Id of the team draft of this page, and JSON list of the tokens of the chosen Pokémon (see TeamDraft)
            $builder
                ->add('draft', HiddenType::class, ['mapped' => false])
                ->add('pokemonTokens', HiddenType::class, ['mapped' => false]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'data_class' => Team::class,
            'with_pokemon' => true,
        ]);
        $resolver->setAllowedTypes('with_pokemon', 'bool');
    }
}
