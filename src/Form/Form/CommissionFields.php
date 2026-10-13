<?php

declare(strict_types=1);

namespace App\Form\Form;

use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Context\ExecutionContextInterface;

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
     * Shown when more is sent than the server accepts at once (anything over
     * 110MB; the page stops more than 100MB being chosen).
     */
    public static function tooLargeMessage(): string
    {
        return 'These files come to more than ' . round(self::MAX_TOTAL_BYTES / (1024 * 1024)) . 'MB in total. Please choose fewer or smaller files.';
    }

    /**
     * The hidden "website" field: people never see it, spam bots fill it in.
     * An enquiry with it filled is thanked but not saved.
     */
    public const HONEYPOT = 'website';

    /**
     * The file chooser. The page checks the files as they are chosen
     * (commission_controller.js); the same checks run here, as anything can
     * be sent directly.
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
            'constraints' => [
                new Assert\Callback(static function (mixed $files, ExecutionContextInterface $context): void {
                    $files = \is_array($files) ? array_values(array_filter($files, static fn (mixed $file): bool => $file instanceof UploadedFile)) : [];
                    $problem = self::uploadProblem(array_map(static fn (UploadedFile $file): array => [
                        'name' => $file->getClientOriginalName(),
                        'size' => $file->isValid() ? (int) $file->getSize() : 0,
                        'error' => $file->getError(),
                    ], $files));
                    if ($problem !== null) {
                        $context->addViolation($problem);
                    }
                }),
            ],
        ];
    }

    /**
     * A file the server refused (over 50MB) or that didn't arrive whole gets
     * one message in our words, instead of Symfony's own. Runs before the
     * file field's own check, which then never sees the file.
     */
    public static function reportFailedUploads(FormBuilderInterface $files): void
    {
        $files->addEventListener(FormEvents::PRE_SUBMIT, static function (FormEvent $event): void {
            $kept = [];
            $problem = null;
            foreach ((array) $event->getData() as $file) {
                if ($file instanceof UploadedFile && ! $file->isValid()) {
                    $problem ??= self::uploadProblem([[
                        'name' => $file->getClientOriginalName(),
                        'size' => 0,
                        'error' => $file->getError(),
                    ]]);

                    continue;
                }

                $kept[] = $file;
            }

            if ($problem !== null) {
                $event->getForm()->addError(new FormError($problem));
            }

            $event->setData($kept);
        }, 10);
    }

    /**
     * What is wrong with these files, in the words the page uses, or null if
     * they can be sent: a file that didn't arrive whole, another type, over
     * 50MB, or more than 100MB together.
     *
     * @param list<array{name: string, size: int, error: int}> $files
     */
    public static function uploadProblem(array $files): ?string
    {
        $megabytes = static fn (int $bytes): string => round($bytes / (1024 * 1024)) . 'MB';
        $total = 0;
        foreach ($files as $file) {
            if (\in_array($file['error'], [\UPLOAD_ERR_INI_SIZE, \UPLOAD_ERR_FORM_SIZE], true)) {
                return \sprintf('"%s" is over %s: each file can be up to %s.', $file['name'], $megabytes(self::MAX_FILE_BYTES), $megabytes(self::MAX_FILE_BYTES));
            }

            if ($file['error'] !== \UPLOAD_ERR_OK) {
                return \sprintf("\"%s\" didn't arrive. Please choose it again.", $file['name']);
            }

            if (! \in_array(strtolower(pathinfo($file['name'], \PATHINFO_EXTENSION)), self::FILE_EXTENSIONS, true)) {
                return \sprintf('"%s" can\'t be sent: please choose PDF, DWG, JPG, PNG or HEIC files.', $file['name']);
            }

            if ($file['size'] > self::MAX_FILE_BYTES) {
                return \sprintf('"%s" is over %s: each file can be up to %s.', $file['name'], $megabytes(self::MAX_FILE_BYTES), $megabytes(self::MAX_FILE_BYTES));
            }

            $total += $file['size'];
        }

        if ($total > self::MAX_TOTAL_BYTES) {
            return \sprintf('These files come to more than %s in total. Please choose fewer or smaller files.', $megabytes(self::MAX_TOTAL_BYTES));
        }

        return null;
    }

    /**
     * @return array<string, mixed>
     */
    public static function honeypot(): array
    {
        return [
            'required' => false,
            'mapped' => false,
            'attr' => [
                'tabindex' => -1,
                'autocomplete' => 'off',
                // Never remembered (remember_form_controller.js): a filled trap
                // would be filled again on every later visit in the tab.
                'data-remember-form-skip' => '',
            ],
        ];
    }
}
