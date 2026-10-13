<?php

declare(strict_types=1);

namespace App\Form\Form;

use App\Entity\Quote;
use App\Enum\GiftSendTo;
use App\Enum\QuotePayment;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\CollectionType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\IntegerType;
use Symfony\Component\Form\Extension\Core\Type\MoneyType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The quote form in admin (Step 10b.1): a price for each lamp in each
 * design, extra named lines, delivery, full payment or deposit, the
 * estimated casting date and a note. Prices in pounds, stored in pence.
 *
 * For an enquiry ("Begin the Conversation", Step 7c.4: the 'gift' option),
 * a gift section, as the page no longer asks (decided 13 October 2026):
 * the needed-by date, send to the customer or directly to the recipient,
 * and for a direct gift the recipient's name, mobile and message. Saved on
 * the enquiry (DesignRequest::setGift()), so it carries through the quote,
 * the order and the emails exactly as a reservation's gift.
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

        if ($options['gift']) {
            $builder
                ->add('gift', CheckboxType::class, [
                    'required' => false,
                ])
                ->add('giftNeededBy', DateType::class, [
                    'required' => false,
                    'widget' => 'single_text',
                    'input' => 'datetime_immutable',
                    'invalid_message' => BasketProceedForm::NO_DATE,
                ])
                ->add('giftSendTo', EnumType::class, [
                    'class' => GiftSendTo::class,
                    'expanded' => true,
                    'required' => false,
                    'placeholder' => false,
                    'choice_label' => static fn (GiftSendTo $choice): string => $choice->label(),
                ])
                ->add('giftRecipientName', TextType::class, [
                    'required' => false,
                    'constraints' => [new Assert\Length(max: CardPaymentForm::MAX['name'], maxMessage: 'Please use {{ limit }} characters or fewer.')],
                ])
                ->add('giftRecipientPhone', TelType::class, [
                    'required' => false,
                    'constraints' => [
                        new Assert\Regex(pattern: '/^\+?[\d\s()-]{7,15}$/', message: 'Please enter a valid phone number.'),
                    ],
                ])
                ->add('giftMessage', TextareaType::class, [
                    'required' => false,
                    'constraints' => [new Assert\Length(max: CardPaymentForm::GIFT_MESSAGE_MAX, maxMessage: 'Please use {{ limit }} characters or fewer.')],
                    'attr' => [
                        'rows' => 3,
                        'maxlength' => CardPaymentForm::GIFT_MESSAGE_MAX,
                    ],
                ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        // An admin page: an ordinary session-held security token. The site's
        // "stateless" token needs the public pages' script, which admin
        // doesn't load (it was refused once a public form had been used).
        $resolver->setDefaults([
            'csrf_token_id' => 'admin_quote',
            // An enquiry's quote: the gift section (Step 7c.4).
            'gift' => false,
            'constraints' => [
                // A gift needs its date (not in the past), where to send it and, sent directly, the recipient's name.
                new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (! \is_array($data) || ! ($data['gift'] ?? false)) {
                        return;
                    }

                    $neededBy = $data['giftNeededBy'] ?? null;
                    if (! $neededBy instanceof \DateTimeInterface) {
                        $context->buildViolation(BasketProceedForm::NO_DATE)->atPath('[giftNeededBy]')->addViolation();
                    } elseif ($neededBy->format('Y-m-d') < (new \DateTimeImmutable('now', new \DateTimeZone('Europe/London')))->format('Y-m-d')) {
                        $context->buildViolation("The needed-by date can't be in the past.")->atPath('[giftNeededBy]')->addViolation();
                    }

                    $sendTo = $data['giftSendTo'] ?? null;
                    if (! $sendTo instanceof GiftSendTo) {
                        $context->buildViolation(BasketProceedForm::NO_SEND_TO)->atPath('[giftSendTo]')->addViolation();
                    } elseif ($sendTo->isDirect() && trim((string) ($data['giftRecipientName'] ?? '')) === '') {
                        $context->buildViolation("Please enter the recipient's full name.")->atPath('[giftRecipientName]')->addViolation();
                    }
                }),
            ],
        ]);
        $resolver->setAllowedTypes('gift', 'bool');
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
            // An enquiry's gift (Step 7c.4), from the enquiry.
            'gift' => ($request = $quote->getDesignRequest())->isGift(),
            'giftNeededBy' => $request->getGiftNeededBy(),
            'giftSendTo' => $request->getGiftSendTo(),
            'giftRecipientName' => $request->getGiftRecipientName(),
            'giftRecipientPhone' => $request->getGiftRecipientPhone(),
            'giftMessage' => $request->getGiftMessage(),
        ];
    }
}
