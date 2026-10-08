<?php

declare(strict_types=1);

namespace App\Form\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * An extra named line on a quote, e.g. "Custom pigment development £150".
 */
final class QuoteExtraLineType extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('label', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please name the line.'),
                    new Assert\Length(max: 255),
                ],
            ])
            ->add('price', MoneyType::class, [
                'currency' => 'GBP',
                'divisor' => 100,
                'input' => 'integer',
                'scale' => 2,
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter a price for the line.'),
                    new Assert\PositiveOrZero(message: 'Please enter an amount of £0 or more.'),
                ],
            ]);
    }
}
