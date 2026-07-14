<?php

namespace App\Form\Form;

use App\Entity\Address;
use Symfony\Component\Form\AbstractType;
use Symfony\Component\Form\Extension\Core\Type\CountryType;
use Symfony\Component\Form\Extension\Core\Type\EmailType;
use Symfony\Component\Form\Extension\Core\Type\TextType;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\OptionsResolver\OptionsResolver;
use Symfony\Component\Validator\Constraints as Assert;

class AddressForm extends AbstractType
{
    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        if ($options['with_email'] === true) {
            $builder->add('email', EmailType::class, [
                'label' => 'Email',
                'mapped' => false,
                'data' => $options['email'],
                'constraints' => [new Assert\NotBlank(), new Assert\Email()],
            ]);
        }

        $builder
            ->add('recipientName', TextType::class, [
                'label' => 'Full name',
            ])
            ->add('line1', TextType::class, [
                'label' => 'Address line 1',
            ])
            ->add('line2', TextType::class, [
                'label' => 'Address line 2',
                'required' => false,
            ])
            ->add('city', TextType::class, [
                'label' => 'Town / city',
            ])
            ->add('county', TextType::class, [
                'label' => 'County',
                'required' => false,
            ])
            ->add('postcode', TextType::class, [
                'label' => 'Postcode',
            ])
            ->add('country', CountryType::class, [
                'label' => 'Country',
                'preferred_choices' => ['GB'],
                'data' => 'GB',
            ])
            ->add('phone', TextType::class, [
                'label' => 'Phone',
                'required' => false,
            ]);
    }

    public function configureOptions(OptionsResolver $optionsResolver): void
    {
        $optionsResolver->setDefaults([
            'data_class' => Address::class,
            'with_email' => false,
            'email' => null,
        ]);
        $optionsResolver->setAllowedTypes('with_email', 'bool');
        $optionsResolver->setAllowedTypes('email', ['null', 'string']);
    }
}
