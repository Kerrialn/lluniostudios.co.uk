<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DesignRequest;
use App\Entity\NewsletterSubscriber;
use App\Entity\Order;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class EmailService
{
    private const SENDER_EMAIL_ADDRESS = 'notifications@lluniostudios.co.uk';

    /**
     * Shown in the footer of the branded emails.
     */
    public const STUDIO_EMAIL = 'info@lluniostudios.co.uk';

    public const STUDIO_ADDRESS = 'Hogsmeade, Higher Merley Ln, Wimborne BH21 3EG';

    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(STUDIO_NOTIFICATION_EMAIL)%')]
        private string $studioNotificationEmail,
    )
    {
    }

    /**
     * Email 02 (Site v4 design): the customer's "Request received".
     */
    public function sendDesignRequestReceivedEmail(DesignRequest $designRequest): void
    {
        $this->send(
            subject: 'We have your request, ' . $designRequest->getFullName(),
            template: '/email/request/received.html.twig',
            email: $designRequest->getEmail(),
            name: $designRequest->getFullName(),
            context: [
                'designRequest' => $designRequest,
                'opening' => RequestReceivedCopy::opening($designRequest),
            ],
        );
    }

    /**
     * Tells the studio a request has arrived (replies go to the customer).
     */
    public function sendDesignRequestNotificationEmail(DesignRequest $designRequest): void
    {
        $email = $this->compose(
            subject: 'New request ' . $designRequest->getRequestNumber() . ' from ' . $designRequest->getFullName(),
            template: '/email/request/notification.html.twig',
            email: $this->studioNotificationEmail,
            name: 'Llunio Studios',
            context: [
                'designRequest' => $designRequest,
            ],
        );
        $email->replyTo(new Address($designRequest->getEmail(), $designRequest->getFullName()));

        $this->mailer->send($email);
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

    public function sendLoginCodeEmail(User $user, string $code): void
    {
        $this->send(
            subject: 'Your Llunio Studios sign-in code',
            template: '/email/security/login_code.html.twig',
            email: $user->getEmail(),
            name: $user->getFullName() ?? $user->getEmail(),
            context: [
                'code' => $code,
                'name' => $user->getFullName(),
            ],
        );
    }

    public function sendOrderConfirmationEmail(Order $order): void
    {
        if ($order->getEmail() === null) {
            return;
        }

        $this->send(
            subject: 'Your Llunio Studios order ' . $order->getOrderNumber(),
            template: '/email/order/confirmation.html.twig',
            email: $order->getEmail(),
            name: $order->getShippingAddress()?->getRecipientName() ?? $order->getEmail(),
            context: [
                'order' => $order,
            ],
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
        array $context,
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
     * @param array<string|int|object> $context
     * @throws TransportExceptionInterface
     */
    private function send(
        string $subject,
        string $template,
        string $email,
        string $name,
        array $context
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
