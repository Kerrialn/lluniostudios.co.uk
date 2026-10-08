<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommissionEnquiry;
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

    public const SIGN_IN_SUBJECT = 'Sign in to Llunio Studios';

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
     * Email 03 (Site v4 design): the customer's "Your idea has arrived".
     */
    public function sendCommissionReceivedEmail(CommissionEnquiry $enquiry): void
    {
        $this->send(
            subject: 'Your commission enquiry',
            template: '/email/commission/received.html.twig',
            email: $enquiry->getEmail(),
            name: $enquiry->getName(),
            context: [
                'enquiry' => $enquiry,
            ],
        );
    }

    /**
     * Tells the studio a commission enquiry has arrived, listing any files
     * (not attached; downloaded from admin). Replies go to the customer.
     */
    public function sendCommissionNotificationEmail(CommissionEnquiry $enquiry): void
    {
        $email = $this->compose(
            subject: 'New commission enquiry ' . $enquiry->getEnquiryNumber() . ' from ' . ($enquiry->getCompany() ?? $enquiry->getName()),
            template: '/email/commission/notification.html.twig',
            email: $this->studioNotificationEmail,
            name: 'Llunio Studios',
            context: [
                'enquiry' => $enquiry,
            ],
        );
        $email->replyTo(new Address($enquiry->getEmail(), $enquiry->getName()));

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

    /**
     * The sign-in email (Step 10.6): a one-click "Sign in" button, with the
     * 6-digit code underneath as a backup.
     */
    public function sendLoginCodeEmail(User $user, string $code, string $link): void
    {
        $this->send(
            subject: self::SIGN_IN_SUBJECT,
            template: '/email/security/login_code.html.twig',
            email: $user->getEmail(),
            name: $user->getFullName() ?? $user->getEmail(),
            context: [
                'code' => $code,
                'link' => $link,
                'name' => $user->getFirstName(),
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
