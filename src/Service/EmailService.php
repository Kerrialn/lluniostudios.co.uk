<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\NewsletterSubscriber;
use App\Entity\User;
use Carbon\CarbonImmutable;
use Exception;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class EmailService
{
    private const SENDER_EMAIL_ADDRESS = 'notifications@traderpoint.cz';

    public function __construct(
        private MailerInterface          $mailer,
        private TranslatorInterface      $translator,
        private EventDispatcherInterface $dispatcher,
    )
    {
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendNewsLetterSubscriptionEmail(NewsletterSubscriber $newsletterSubscriber, array $context = []): void
    {
        $this->send(
            subject: 'Newsletter subscription confirmation',
            template: '/email/newsletter/confirmation.html.twig',
            email: $newsletterSubscriber->getEmail(),
            name: $newsletterSubscriber->getName(),
            context: array_merge($context, [
                'name' => $newsletterSubscriber->getName(),
            ]),
        );
    }

    /**
     * @param array<string|int|object> $context
     */
    private function compose(
        string $subject,
        string $template,
        string $email,
        string $name,
        array  $context,
        string $locale = 'en'
    ): TemplatedEmail
    {
        $templatedEmail = new TemplatedEmail();
        $templatedEmail->locale($locale);
        $templatedEmail->from(addresses: self::SENDER_EMAIL_ADDRESS);
        $templatedEmail->to(address: new Address($email, $name));
        $templatedEmail->subject(subject: $subject);
        $templatedEmail->htmlTemplate(template: $template);
        $templatedEmail->context(context: $context);

        return $templatedEmail;
    }

    /**
     * @param string $subject
     * @param string $template
     * @param string $email
     * @param string $name
     * @param array<string|int|object> $context
     * @throws TransportExceptionInterface
     */
    private function send(
        string $subject,
        string $template,
        string $email,
        string $name,
        array  $context
    ): void
    {
        $envelope = $this->compose(
            subject: $subject,
            template: $template,
            email: $email,
            name: $name,
            context: $context,
        );

        $this->mailer->send($envelope);

    }
}
