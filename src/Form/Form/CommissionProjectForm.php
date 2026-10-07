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
use Symfony\Component\Validator\Constraints as Assert;

/**
 * Bespoke Commissions, "For Your Project" (trade; Site v4 prototype).
 * Project Type starts empty ("Please choose") and must be chosen (decided
 * 7 October 2026; the prototype starts on its first type).
 */
final class CommissionProjectForm extends AbstractType
{
    public const PROJECT_TYPES = ['High-End Residential', 'Commercial', 'Hospitality', 'Retail'];

    public function buildForm(FormBuilderInterface $builder, array $options): void
    {
        $builder
            ->add('company', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter your company name.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => [
                    'autocomplete' => 'organization',
                    'maxlength' => 255,
                ],
            ])
            ->add('contactName', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter a contact name.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => [
                    'autocomplete' => 'name',
                    'maxlength' => 255,
                ],
            ])
            ->add('email', EmailType::class, CommissionFields::email())
            ->add('phone', TelType::class, CommissionFields::phone())
            ->add('projectType', ChoiceType::class, [
                'choices' => array_combine(self::PROJECT_TYPES, self::PROJECT_TYPES),
                'placeholder' => 'Please choose',
                'constraints' => [new Assert\NotBlank(message: 'Please choose a project type.')],
            ])
            ->add('location', TextType::class, [
                'constraints' => [
                    new Assert\NotBlank(message: 'Please enter the project location and installation schedule.'),
                    new Assert\Length(max: 255),
                ],
                'attr' => [
                    'maxlength' => 255,
                ],
            ])
            ->add('project', TextareaType::class, [
                'required' => false,
                'constraints' => [new Assert\Length(max: 5000)],
                'attr' => [
                    'rows' => 7,
                ],
            ])
            ->add('integration', TextareaType::class, [
                'required' => false,
                'constraints' => [new Assert\Length(max: 5000)],
                'attr' => [
                    'rows' => 3,
                ],
            ])
            ->add('files', FileType::class, CommissionFields::files());
    }
}
