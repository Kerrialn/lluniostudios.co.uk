<?php

declare(strict_types=1);

namespace App\Form\Form;

use App\Catalogue\GiftDate;
use App\Enum\GiftSendTo;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CheckboxType;
use Symfony\Component\Form\Extension\Core\Type\DateType;
use Symfony\Component\Form\Extension\Core\Type\EnumType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

/**
 * The basket's "This is a gift" and "When does it need to arrive by?",
 * sent with "Proceed to Payment" (Site v4 prototype, Step 10.1). A gift
 * needs a date from tomorrow (the `earliest` option: GiftDate::tomorrow(),
 * since 16 October 2026; a date sooner than GiftDate::earliest() sends the
 * order to Reserve Your Design instead), and "Send to me" or "Send directly
 * to the recipient" (Gift options, 9 October 2026).
 */
final class BasketProceedForm extends AbstractType
{
    public const NO_DATE = 'Please choose a date to continue.';

    public const TOO_EARLY = 'Please choose a date from tomorrow onwards.';

    public const NO_SEND_TO = 'Please choose where to send it.';

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
            ])
            ->add('sendTo', EnumType::class, [
                'class' => GiftSendTo::class,
                'expanded' => true,
                'required' => false,
                'placeholder' => false,
                'choice_label' => static fn (GiftSendTo $choice): string => $choice->label(),
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

                    if (! ($data['sendTo'] ?? null) instanceof GiftSendTo) {
                        $context->buildViolation(self::NO_SEND_TO)->atPath('[sendTo]')->addViolation();
                    }
                }),
            ],
        ]);
    }
}
