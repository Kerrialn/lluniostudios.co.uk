<?php

declare(strict_types=1);

namespace App\Form\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TelType;
use Symfony\Component\Form\Extension\Core\Type\TextareaType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * "Reserve Your Design" (Site v4 prototype): the customer's details. "Custom
 * Details" is asked for only when a lamp has any Custom choice.
 */
final class ReserveForm extends AbstractType
{
    /**
     * UK postcode anywhere in the address (e.g. "BH21 3EG").
     */
    public const POSTCODE_PATTERN = '/\b[A-Z]{1,2}\d[A-Z\d]?\s*\d[A-Z]{2}\b/i';

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
            ->add('deliveryAddress', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter your delivery address and postcode.'),
                    new Assert\Regex(pattern: self::POSTCODE_PATTERN, message: 'Please include your postcode.'),
                    new Assert\Length(max: 1000),
                ],
                'attr' => [
                    'autocomplete' => 'street-address',
                    'rows' => 3,
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
                'constraints' => [new Assert\Length(max: 2000)],
                'attr' => [
                    'rows' => 3,
                ],
            ]);
        }
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'custom_choices' => false,
        ]);
        $resolver->setAllowedTypes('custom_choices', 'bool');
    }
}
