<?php

declare(strict_types=1);

namespace App\Form\Form;

use App\Enum\EnquiryTalk;
use App\Shipping\DeliveryArea;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\ChoiceType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\FileType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * "Begin the Conversation" (Step 7c, decided 12 October 2026): the enquiry for
 * a larger piece (Atelier, Estate, Monument). Contact details; how they'd
 * like to talk (email, phone or video call; the phone required for the last
 * two) and when; their space and what they have in mind (required); files
 * with the Bespoke Commissions rules; a board link (any web address, kept as
 * typed); the postcode only (any full UK postcode, the country worked out
 * from it; the full address is asked on the quote page). No "Needed by" or
 * gift questions (removed 13 October 2026: talked about in the conversation).
 *
 * The messages are DRAFT wording until the wording pass (11.5).
 */
final class EnquiryForm extends AbstractType
{
    public const DESCRIPTION_MAX = 5000;

    public const LINK_MAX = 500;

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
            ->add('email', EmailType::class, CommissionFields::email())
            ->add('phone', TelType::class, CommissionFields::phone())
            ->add('talk', ChoiceType::class, [
                'choices' => EnquiryTalk::cases(),
                'choice_value' => static fn (?EnquiryTalk $talk): ?string => $talk?->value,
                'choice_label' => static fn (EnquiryTalk $talk): string => $talk->label(),
                'expanded' => true,
                'placeholder' => false,
                'constraints' => [new Assert\NotBlank(message: "Please choose how you'd like to talk.")],
            ])
            ->add('bestTime', TextType::class, [
                'required' => false,
                'constraints' => [new Assert\Length(max: 255)],
                'attr' => [
                    'maxlength' => 255,
                ],
            ])
            ->add('description', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please tell me a little about your space.'),
                    new Assert\Length(max: self::DESCRIPTION_MAX),
                ],
                'attr' => [
                    'rows' => 7,
                    'maxlength' => self::DESCRIPTION_MAX,
                ],
            ])
            ->add('files', FileType::class, CommissionFields::files())
            ->add('boardLink', TextType::class, [
                'required' => false,
                'constraints' => [
                    new Assert\Length(max: self::LINK_MAX),
                    new Assert\Regex(pattern: '/^\S+\.\S+$/', message: 'Please enter a web address, e.g. pinterest.com/your-board.'),
                ],
                'attr' => [
                    'maxlength' => self::LINK_MAX,
                    'inputmode' => 'url',
                    'autocomplete' => 'url',
                ],
            ])
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
                    'autocomplete' => 'postal-code',
                    'autocapitalize' => 'characters',
                ],
            ])
            ->add(CommissionFields::HONEYPOT, TextType::class, CommissionFields::honeypot());
        CommissionFields::reportFailedUploads($builder->get('files'));
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'upload_max_size_message' => CommissionFields::tooLargeMessage(...),
            'constraints' => [
                // A phone or video call needs the phone number.
                new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    $talk = \is_array($data) ? ($data['talk'] ?? null) : null;
                    if (! $talk instanceof EnquiryTalk || ! $talk->needsPhone() || trim((string) ($data['phone'] ?? '')) !== '') {
                        return;
                    }

                    $context->buildViolation('Please enter your phone number for a phone or video call.')->atPath('[phone]')->addViolation();
                }),
            ],
        ]);
    }
}
