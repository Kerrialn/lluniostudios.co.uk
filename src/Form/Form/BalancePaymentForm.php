<?php

declare(strict_types=1);

namespace App\Form\Form;

use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Paying a quote's balance (Step 10b.4) on the quote page: only the name on
 * the card (the delivery address was confirmed with the deposit); the card
 * itself is in Stripe's boxes. Created with the Card Payment form's name so
 * the page script shows its problems the same way.
 */
final class BalancePaymentForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder->add('cardName', TextType::class, [
            'constraints' => [
                new Assert\NotBlank(message: CardPaymentForm::REQUIRED_MESSAGE),
                new Assert\Length(max: 100),
            ],
            'attr' => [
                'maxlength' => 100,
                'autocomplete' => 'cc-name',
            ],
        ]);
    }
}
