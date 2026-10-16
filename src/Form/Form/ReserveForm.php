<?php

declare(strict_types=1);

namespace App\Form\Form;

use App\Entity\DesignRequest;
use App\Enum\GiftSendTo;
use App\Shipping\DeliveryArea;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * "Reserve Your Design" (Site v4 prototype): the customer's details. "Custom
 * Details" is asked for only when a lamp has any Custom choice. The address
 * is in separate fields with DPD's limits, as on the Card Payment page, plus
 * a country (United Kingdom, Isle of Man, Guernsey or Jersey; decided
 * 8 October 2026) that must match the postcode.
 *
 * Gift options (9 October 2026): "This is a gift" with a "Needed by" date (a
 * preferred date, not a guarantee; any date from tomorrow, as in the basket
 * since 16 October 2026) and "Send to me" or "Send directly to the recipient"; sent
 * directly, the recipient's name (the address is theirs), an optional gift
 * message (a card in the box) and the recipient's mobile.
 *
 * Reference photos (10 October 2026): with a Custom choice, optional files
 * under "Custom Details" (e.g. a paint chip to match), with the Bespoke
 * Commissions upload rules (CommissionFields: PDF, DWG, JPG, PNG or HEIC,
 * 50MB each, 100MB in total).
 */
final class ReserveForm extends AbstractType
{
    /**
     * "Custom Details" limit; raised when descriptions are carried from the
     * configurator (ReserveController), so they always fit.
     */
    public const CUSTOM_DETAILS_MAX = 2000;

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('fullName', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter your full name.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => [
                    'autocomplete' => 'name',
                    'maxlength' => 255,
                ],
            ])
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter your email address.'),
                    new Assert\Email(message: 'Please enter a valid email address.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => [
                    'autocomplete' => 'email',
                    'maxlength' => 255,
                ],
            ])
            ->add('phone', TelType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter your phone number.'),
                    new Assert\Regex(pattern: '/^\+?[\d\s()-]{7,20}$/', message: 'Please enter a valid phone number.'),
                ],
                'attr' => [
                    'autocomplete' => 'tel',
                    'inputmode' => 'tel',
                    'maxlength' => 20,
                ],
            ])
            ->add('addressLine1', TextType::class, $this->text(CardPaymentForm::MAX['line'], 'shipping address-line1', 'Please enter the first line of your address.'))
            ->add('addressLine2', TextType::class, $this->text(CardPaymentForm::MAX['line'], 'shipping address-line2'))
            ->add('town', TextType::class, $this->text(CardPaymentForm::MAX['town'], 'shipping address-level2', 'Please enter your town or city.'))
            ->add('county', TextType::class, $this->text(CardPaymentForm::MAX['county'], 'shipping address-level1'))
            ->add('postcode', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter your postcode.'),
                    new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                        if (\is_string($value) && trim($value) !== '' && DeliveryArea::normalise($value) === null) {
                            $context->addViolation('Please enter a full postcode, e.g. SW1A 1AA.');
                        }
                    }),
                ],
                'attr' => [
                    'maxlength' => CardPaymentForm::MAX['postcode'],
                    'autocomplete' => 'shipping postal-code',
                    'autocapitalize' => 'characters',
                ],
            ])
            ->add('country', ChoiceType::class, [
                'choices' => array_flip(DesignRequest::COUNTRIES),
                'placeholder' => false,
                'constraints' => [new Assert\Choice(choices: array_keys(DesignRequest::COUNTRIES))],
                'attr' => [
                    'autocomplete' => 'shipping country',
                ],
            ])
            ->add('gift', CheckboxType::class, [
                'required' => false,
            ])
            ->add('giftNeededBy', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'invalid_message' => BasketProceedForm::NO_DATE,
                'attr' => [
                    'min' => $options['earliest']->format('Y-m-d'),
                ],
            ])
            ->add('giftSendTo', EnumType::class, [
                'class' => GiftSendTo::class,
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
                'choice_label' => static fn (GiftSendTo $choice): string => $choice->label(),
            ])
            ->add('giftRecipientName', TextType::class, $this->text(CardPaymentForm::MAX['name'], 'off'))
            ->add('giftMessage', TextareaType::class, $this->text(CardPaymentForm::GIFT_MESSAGE_MAX, 'off'))
            ->add('giftRecipientPhone', TelType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Regex(pattern: '/^\+?[\d\s()-]{7,15}$/', message: 'Please enter a valid phone number.'),
                ],
                'attr' => [
                    'autocomplete' => 'off',
                    'inputmode' => 'tel',
                    'maxlength' => CardPaymentForm::MAX['phone'],
                ],
            ])
            ->add('accessNotes', TextareaType::class, [
                'required' => false,
                'constraints' => [new Assert\Length(max: 2000)],
                'attr' => [
                    'rows' => 3,
                ],
            ]);

        if ($options['custom_choices']) {
            $builder->add('customDetails', TextareaType::class, [
                'required' => false,
                'constraints' => [new Assert\Length(max: $options['custom_details_max'])],
                'attr' => [
                    'rows' => 3,
                ],
            ]);
            $builder->add('referencePhotos', FileType::class, CommissionFields::files());
            CommissionFields::reportFailedUploads($builder->get('referencePhotos'));
        }
    }

    /**
     * @return array<string, mixed>
     */
    private function text(int $max, string $autocomplete, ?string $required = null): array
    {
        return [
            'required' => $required !== null,
            'constraints' => array_merge($required !== null ? [new Assert\NotBlank(message: $required)] : [], [
                new Assert\Length(max: $max, maxMessage: 'Please use {{ limit }} characters or fewer.'),
            ]),
            'attr' => [
                'maxlength' => $max,
                'autocomplete' => $autocomplete,
            ],
        ];
    }

    /**
     * The country a postcode belongs to: Isle of Man (IM), Guernsey (GY),
     * Jersey (JE), otherwise the United Kingdom.
     */
    public static function countryForPostcode(string $postcode): string
    {
        return match (substr(strtoupper(trim($postcode)), 0, 2)) {
            'IM' => 'IM',
            'GY' => 'GG',
            'JE' => 'JE',
            default => 'GB',
        };
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('earliest');
        $resolver->setAllowedTypes('earliest', \DateTimeInterface::class);
        $resolver->setDefaults([
            'custom_choices' => false,
            'custom_details_max' => self::CUSTOM_DETAILS_MAX,
            'upload_max_size_message' => CommissionFields::tooLargeMessage(...),
            'constraints' => [
                // A gift needs its date, where to send it and, sent directly, the recipient's name.
                new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (! \is_array($data) || ! ($data['gift'] ?? false)) {
                        return;
                    }

                    $neededBy = $data['giftNeededBy'] ?? null;
                    /** @var \DateTimeInterface $earliest */
                    $earliest = $context->getRoot()->getConfig()->getOption('earliest');
                    if (! $neededBy instanceof \DateTimeInterface) {
                        $context->buildViolation(BasketProceedForm::NO_DATE)->atPath('[giftNeededBy]')->addViolation();
                    } elseif ($neededBy->format('Y-m-d') < $earliest->format('Y-m-d')) {
                        $context->buildViolation(BasketProceedForm::TOO_EARLY)->atPath('[giftNeededBy]')->addViolation();
                    }

                    $sendTo = $data['giftSendTo'] ?? null;
                    if (! $sendTo instanceof GiftSendTo) {
                        $context->buildViolation(BasketProceedForm::NO_SEND_TO)->atPath('[giftSendTo]')->addViolation();
                    } elseif ($sendTo->isDirect() && trim((string) ($data['giftRecipientName'] ?? '')) === '') {
                        $context->buildViolation("Please enter the recipient's full name.")->atPath('[giftRecipientName]')->addViolation();
                    }
                }),
                new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    $postcode = \is_array($data) ? DeliveryArea::normalise((string) ($data['postcode'] ?? '')) : null;
                    if ($postcode === null || ! isset(DesignRequest::COUNTRIES[(string) ($data['country'] ?? '')])) {
                        return;
                    }

                    $belongsTo = self::countryForPostcode($postcode);
                    if ($belongsTo !== $data['country']) {
                        $where = [
                            'GB' => 'in the United Kingdom',
                            'IM' => 'on the Isle of Man',
                            'GG' => 'in Guernsey',
                            'JE' => 'in Jersey',
                        ][$belongsTo];
                        $context->buildViolation(\sprintf('That postcode is %s: please choose it as the country.', $where))
                            ->atPath('[country]')
                            ->addViolation();
                    }
                }),
            ],
        ]);
        $resolver->setAllowedTypes('custom_choices', 'bool');
        $resolver->setAllowedTypes('custom_details_max', 'int');
    }
}
