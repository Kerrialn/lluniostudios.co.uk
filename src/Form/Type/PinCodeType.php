<?php

declare(strict_types=1);

namespace App\Form\Type;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\Form\FormView;
use Symfony\Component\OptionsResolver\OptionsResolver;

/**
 * A one-time-code / PIN input rendered as a row of single-character boxes
 * (Flowbite pin-code style). Submits a single concatenated string value.
 */
final class PinCodeType extends AbstractType
{
    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'length' => 6,
        ]);
        $resolver->setAllowedTypes('length', 'int');
    }

    public function buildView(FormView $view, FormInterface $form, array $options): void
    {
        $view->vars['length'] = $options['length'];
    }

    public function getParent(): string
    {
        return TextType::class;
    }

    public function getBlockPrefix(): string
    {
        return 'pin_code';
    }
}
