<?php

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\NumberType;
use Symfony\Component\OptionsResolver\OptionsResolver;

class IntegerType extends AbstractType
{
    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'scale' => 0,
            'attr' => [
                'class' => 'custom-number-input',
                'min' => 0,
                'max' => 999,
            ],
            'template' => 'form/custom_number_widget.html.twig',
        ]);
    }

    public function getParent(): string
    {
        return NumberType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'integer_type';
    }
}

