<?php

declare(strict_types=1);

namespace App\Form\Form;

use Symfony\Component\Validator\Constraints as Assert;

/**
 * Fields shared by the two Bespoke Commissions forms.
 */
final class CommissionFields
{
    /**
     * Uploads (decided 7 October 2026): several files, up to 50MB each and
     * 100MB in total per enquiry.
     */
    public const MAX_FILE_BYTES = 50 * 1024 * 1024;

    public const MAX_TOTAL_BYTES = 100 * 1024 * 1024;

    public const FILE_EXTENSIONS = ['pdf', 'dwg', 'jpg', 'jpeg', 'png', 'heic'];

    /**
     * @return array<string, mixed>
     */
    public static function email(): array
    {
        return [
            'constraints' => [
                new Assert\NotBlank(message: 'Please enter your email address.'),
                new Assert\Email(message: 'Please enter a valid email address.'),
                new Assert\Length(max: 255),
            ],
            'attr' => [
                'autocomplete' => 'email',
                'maxlength' => 255,
            ],
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public static function phone(): array
    {
        return [
            'required' => false,
            'constraints' => [
                new Assert\Regex(pattern: '/^\+?[\d\s()-]{7,20}$/', message: 'Please enter a valid phone number.'),
            ],
            'attr' => [
                'autocomplete' => 'tel',
                'inputmode' => 'tel',
                'maxlength' => 20,
            ],
        ];
    }

    /**
     * The file chooser. Checked in the page for now; saving and the server's
     * checks come with Step 9.2.
     *
     * @return array<string, mixed>
     */
    public static function files(): array
    {
        return [
            'required' => false,
            'multiple' => true,
            'mapped' => false,
            'attr' => [
                'accept' => implode(',', array_map(static fn (string $extension): string => '.' . $extension, self::FILE_EXTENSIONS)),
            ],
        ];
    }
}
