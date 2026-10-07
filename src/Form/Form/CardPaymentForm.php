<?php

declare(strict_types=1);

namespace App\Form\Form;

use App\Shipping\DeliveryArea;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The Card Payment page (Site v4 prototype, Step 10.2): contact, delivery
 * address, billing address. Field lengths are DPD's label limits (to confirm
 * with DPD); a gift asks for the same details (decided 7 October 2026). The
 * billing address is asked for only when "Same as delivery address" is
 * unticked.
 */
final class CardPaymentForm extends AbstractType
{
    /**
     * DPD's limits (the prototype's FIELD_MAX).
     */
    public const MAX = [
        'name' => 35,
        'company' => 35,
        'line' => 35,
        'town' => 35,
        'county' => 35,
        'postcode' => 8,
        'notes' => 50,
        'phone' => 15,
    ];

    public const REQUIRED_MESSAGE = 'Please complete the required fields to continue.';

    private const PHONE_PATTERN = '/^\+?[\d\s()-]{7,15}$/';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $text = static fn (int $max, string $autocomplete, bool $required = true, array $extra = []): array => [
            'required' => $required,
            'constraints' => array_merge($required ? [new Assert\NotBlank(message: self::REQUIRED_MESSAGE)] : [], [new Assert\Length(max: $max, maxMessage: 'Please use {{ limit }} characters or fewer.')], $extra),
            'attr' => [
                'maxlength' => $max,
                'autocomplete' => $autocomplete,
            ],
        ];
        $phone = static fn (string $autocomplete): array => [
            'constraints' => [
                new Assert\NotBlank(message: self::REQUIRED_MESSAGE),
                new Assert\Regex(pattern: self::PHONE_PATTERN, message: 'Please enter a valid phone number.'),
            ],
            'attr' => [
                'maxlength' => self::MAX['phone'],
                'autocomplete' => $autocomplete,
                'inputmode' => 'tel',
            ],
        ];
        $postcode = static fn (string $autocomplete): array => [
            'constraints' => [
                new Assert\NotBlank(message: self::REQUIRED_MESSAGE),
                new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                    if (\is_string($value) && trim($value) !== '' && DeliveryArea::normalise($value) === null) {
                        $context->addViolation('Please enter a full UK postcode, e.g. BH21 3EG.');
                    }
                }),
            ],
            'attr' => [
                'maxlength' => self::MAX['postcode'],
                'autocomplete' => $autocomplete,
                'autocapitalize' => 'characters',
            ],
        ];

        $builder
            ->add('email', EmailType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: self::REQUIRED_MESSAGE),
                    new Assert\Email(message: 'Please enter a valid email address.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => [
                    'autocomplete' => 'email',
                    'inputmode' => 'email',
                    'maxlength' => 255,
                ],
            ])
            ->add('phone', TelType::class, $phone('tel'))
            ->add('name', TextType::class, $text(self::MAX['name'], 'shipping name'))
            ->add('company', TextType::class, $text(self::MAX['company'], 'shipping organization', false))
            ->add('line1', TextType::class, $text(self::MAX['line'], 'shipping address-line1'))
            ->add('line2', TextType::class, $text(self::MAX['line'], 'shipping address-line2', false))
            ->add('town', TextType::class, $text(self::MAX['town'], 'shipping address-level2'))
            ->add('county', TextType::class, $text(self::MAX['county'], 'shipping address-level1', false))
            ->add('postcode', TextType::class, $postcode('shipping postal-code'))
            ->add('notes', TextType::class, $text(self::MAX['notes'], 'off', false));

        // Card details: the name here; number, expiry and security code are
        // Stripe's own secure boxes (never sent to this site).
        $builder->add('cardName', TextType::class, $text(100, 'cc-name'));

        // The billing fields are checked only when "Same as delivery address" is unticked.
        $builder
            ->add('billingSame', CheckboxType::class, [
                'required' => false,
            ])
            ->add('billingName', TextType::class, $text(self::MAX['name'], 'billing name', false))
            ->add('billingLine1', TextType::class, $text(self::MAX['line'], 'billing address-line1', false))
            ->add('billingLine2', TextType::class, $text(self::MAX['line'], 'billing address-line2', false))
            ->add('billingTown', TextType::class, $text(self::MAX['town'], 'billing address-level2', false))
            ->add('billingCounty', TextType::class, $text(self::MAX['county'], 'billing address-level1', false))
            ->add('billingPostcode', TextType::class, array_replace($postcode('billing postal-code'), [
                'required' => false,
                'constraints' => [
                    new Assert\Callback(static function (mixed $value, ExecutionContextInterface $context): void {
                        if (\is_string($value) && trim($value) !== '' && DeliveryArea::normalise($value) === null) {
                            $context->addViolation('Please enter a full UK postcode, e.g. BH21 3EG.');
                        }
                    }),
                ],
            ]));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (! \is_array($data) || ($data['billingSame'] ?? true)) {
                        return;
                    }

                    foreach (['billingName', 'billingLine1', 'billingTown', 'billingPostcode'] as $field) {
                        if (trim((string) ($data[$field] ?? '')) === '') {
                            $context->buildViolation(self::REQUIRED_MESSAGE)->atPath("[{$field}]")->addViolation();
                        }
                    }
                }),
            ],
        ]);
    }
}
