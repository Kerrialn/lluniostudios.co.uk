<?php

declare(strict_types=1);

namespace App\Form\Form;

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
 * Bespoke Commissions, "For Your Home" (Site v4 prototype). The phone number
 * is optional unless the customer would like us to call.
 */
final class CommissionHomeForm extends AbstractType
{
    public const CONTACT_EMAIL = 'email';

    public const CONTACT_PHONE = 'phone';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('name', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter your name.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => [
                    'autocomplete' => 'name',
                    'maxlength' => 255,
                ],
            ])
            ->add('email', EmailType::class, CommissionFields::email())
            ->add('phone', TelType::class, CommissionFields::phone())
            ->add('contactMethod', ChoiceType::class, [
                'choices' => [
                    'Email' => self::CONTACT_EMAIL,
                    'Phone' => self::CONTACT_PHONE,
                ],
                'expanded' => true,
                'placeholder' => false,
                'constraints' => [new Assert\NotBlank(message: "Please choose how you'd like us to contact you.")],
            ])
            ->add('idea', TextareaType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please tell us about your idea.'),
                    new Assert\Length(max: 5000),
                ],
                'attr' => [
                    'rows' => 7,
                ],
            ])
            ->add('files', FileType::class, CommissionFields::files());
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setDefaults([
            'constraints' => [
                new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (! \is_array($data) || ($data['contactMethod'] ?? null) !== self::CONTACT_PHONE || trim((string) ($data['phone'] ?? '')) !== '') {
                        return;
                    }

                    $context->buildViolation('Please enter your phone number so we can call you.')
                        ->atPath('[phone]')
                        ->addViolation();
                }),
            ],
        ]);
    }
}
