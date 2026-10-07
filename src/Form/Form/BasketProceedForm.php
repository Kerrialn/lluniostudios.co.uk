<?php

declare(strict_types=1);

namespace App\Form\Form;

use App\Catalogue\GiftDate;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The basket's "This is a gift" and "When does it need to arrive by?",
 * sent with "Proceed to Payment" (Site v4 prototype, Step 10.1). A gift
 * needs a date no earlier than GiftDate::earliest().
 */
final class BasketProceedForm extends AbstractType
{
    public const NO_DATE = 'Please choose a date to continue.';

    public const TOO_EARLY = 'Please choose a date on or after the earliest delivery date.';

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('gift', CheckboxType::class, [
                'required' => false,
            ])
            ->add('arriveBy', DateType::class, [
                'required' => false,
                'widget' => 'single_text',
                'input' => 'datetime_immutable',
                'invalid_message' => self::NO_DATE,
                'attr' => [
                    'min' => $options['earliest']->format('Y-m-d'),
                ],
            ]);
    }

    public function configureOptions(OptionsResolver $resolver): void
    {
        $resolver->setRequired('earliest');
        $resolver->setAllowedTypes('earliest', \DateTimeInterface::class);
        $resolver->setDefaults([
            'constraints' => [
                new Assert\Callback(static function (mixed $data, ExecutionContextInterface $context): void {
                    if (! \is_array($data) || ! ($data['gift'] ?? false)) {
                        return;
                    }

                    $arriveBy = $data['arriveBy'] ?? null;
                    /** @var \DateTimeInterface $earliest */
                    $earliest = $context->getRoot()->getConfig()->getOption('earliest');
                    if (! $arriveBy instanceof \DateTimeInterface) {
                        $context->buildViolation(self::NO_DATE)->atPath('[arriveBy]')->addViolation();
                    } elseif ($arriveBy->format('Y-m-d') < $earliest->format('Y-m-d')) {
                        $context->buildViolation(self::TOO_EARLY)->atPath('[arriveBy]')->addViolation();
                    }
                }),
            ],
        ]);
    }
}
