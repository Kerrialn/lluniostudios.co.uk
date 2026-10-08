<?php

declare(strict_types=1);

namespace App\Form\Form;

use App\Entity\Quote;
use App\Enum\QuotePayment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * The quote form in admin (Step 10b.1): a price for each lamp in each
 * design, extra named lines, delivery, full payment or deposit, the
 * estimated casting date and a note. Prices in pounds, stored in pence.
 */
final class QuoteForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $money = static fn (string $message): array => [
            'currency' => 'GBP',
            'divisor' => 100,
            'input' => 'integer',
            'scale' => 2,
            'constraints' => [
                new Assert\NotBlank(message: $message),
                new Assert\PositiveOrZero(message: 'Please enter an amount of £0 or more.'),
            ],
        ];

        $builder
            ->add('lamps', CollectionType::class, [
                'entry_type' => MoneyType::class,
                'entry_options' => $money('Please enter a price for each lamp.'),
            ])
            ->add('extras', CollectionType::class, [
                'entry_type' => QuoteExtraLineType::class,
                'allow_add' => true,
                'allow_delete' => true,
                'delete_empty' => static fn (?array $line): bool => $line === null || (trim((string) ($line['label'] ?? '')) === '' && ($line['price'] ?? null) === null),
                'prototype' => true,
            ])
            ->add('delivery', MoneyType::class, $money('Please enter the delivery price.'))
            ->add('deliveryLabel', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please describe the delivery, e.g. "DPD Next Day Delivery".'),
                    new Assert\Length(max: 128),
                ],
            ])
            ->add('payment', EnumType::class, [
                'class' => QuotePayment::class,
                'choice_label' => static fn (QuotePayment $payment): string => $payment->label(),
                'expanded' => true,
            ])
            ->add('depositPercent', IntegerType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter the deposit percentage.'),
                    new Assert\Range(notInRangeMessage: 'The deposit must be between {{ min }}% and {{ max }}%.', min: 1, max: 99),
                ],
            ])
            ->add('castingDate', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
            ])
            ->add('note', TextareaType::class, [
                'required' => false,
                'constraints' => [new Assert\Length(max: 2000)],
                'attr' => [
                    'rows' => 4,
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // An admin page: an ordinary session-held security token. The site's
        // "stateless" token needs the public pages' script, which admin
        // doesn't load (it was refused once a public form had been used).
        $resolver->setDefaults([
            'csrf_token_id' => 'admin_quote',
        ]);
    }

    /**
     * The form's starting values from a quote.
     *
     * @return array<string, mixed>
     */
    public static function dataFrom(Quote $quote): array
    {
        return [
            'lamps' => array_map(static fn ($line): int => $line->getUnitPrice(), $quote->getLampLines()),
            'extras' => array_map(static fn ($line): array => [
                'label' => $line->getLabel(),
                'price' => $line->getUnitPrice(),
            ], $quote->getExtraLines()),
            'delivery' => $quote->getDeliveryPrice(),
            'deliveryLabel' => $quote->getDeliveryLabel(),
            'payment' => $quote->getPayment(),
            'depositPercent' => $quote->getDepositPercent(),
            'castingDate' => $quote->getCastingDate(),
            'note' => $quote->getNote(),
        ];
    }
}
