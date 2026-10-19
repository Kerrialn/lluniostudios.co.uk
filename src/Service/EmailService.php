<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\CommissionEnquiry;
use App\Entity\DesignRequest;
use App\Entity\NewsletterSubscriber;
use App\Entity\Order;
use App\Entity\Quote;
use App\Entity\User;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Address;

final readonly class EmailService
{
    private const SENDER_EMAIL_ADDRESS = 'notifications@lluniostudios.co.uk';

    public const SIGN_IN_SUBJECT = 'Sign in to Llunio Studios';

    public const QUOTE_SUBJECT = 'Your Lumen Forma quote';

    public const BALANCE_SUBJECT = 'Your Lumen Forma balance';

    public const BALANCE_RECEIVED_SUBJECT = 'Your Lumen Forma order is paid in full';

    public const ORDER_SUBJECT = 'Your Llunio Studios order';

    public const IN_PRODUCTION_SUBJECT = 'Your Lumen Forma is being cast';

    public const DISPATCHED_SUBJECT = 'Your Lumen Forma is on its way';

    public const DELIVERED_SUBJECT = 'Your Lumen Forma has arrived';

    /**
     * "Begin the Conversation" (Step 7c.3; DRAFT wording until 11.5).
     */
    public const ENQUIRY_SUBJECT = 'Your Lumen Forma enquiry';

    /**
     * How the enquiry emails to the customer are signed, exactly as given
     * (13 October 2026), line breaks and spelling included.
     */
    public const ENQUIRY_SIGNATURE = "All the best,\n\nTheo\n@LlunioStuidos";

    private const STUDIO_EMAIL_ADDRESS = 'info@lluniostudios.co.uk';

    public function __construct(
        private MailerInterface $mailer,
        #[Autowire('%env(STUDIO_NOTIFICATION_EMAIL)%')]
        private string $studioNotificationEmail,
        // The studio's public email (replies to customer emails): STUDIO_EMAIL in .env.
        #[Autowire('%env(STUDIO_EMAIL)%')]
        private string $studioEmail,
        private OrderDates $orderDates,
    )
    {
    }

    /**
     * Email 02 (Site v4 design): the customer's "Request received".
     */
    public function sendDesignRequestReceivedEmail(DesignRequest $designRequest): void
    {
        $this->send(
            subject: 'Your Lumen Forma request, ' . ($designRequest->getFirstName() ?? $designRequest->getFullName()),
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
     * The studio's quote for a request (Step 10b.2): the breakdown, what to
     * pay now, the casting date and note, and "Pay now" (the private quote
     * page). Replies go to the studio.
     *
     * @param array<string, mixed> $view as Quotes::view()
     */
    public function sendQuoteEmail(Quote $quote, array $view, string $token, \DateTimeImmutable $validUntil): void
    {
        $request = $quote->getDesignRequest();
        $email = $this->compose(
            subject: self::QUOTE_SUBJECT . ', ' . ($request->getFirstName() ?? $request->getFullName()),
            template: '/email/request/quote.html.twig',
            email: $request->getEmail(),
            name: $request->getFullName(),
            context: [
                'designRequest' => $request,
                'quote' => $view,
                'token' => $token,
                'validUntil' => $validUntil,
            ],
        );
        $email->replyTo(new Address($this->studioEmail, 'Llunio Studios'));

        $this->mailer->send($email);
    }

    /**
     * Asking for a quote's balance (Step 10b.4): the order total, what was
     * paid and "Pay balance" (the quote page). Replies go to the studio.
     */
    public function sendBalanceRequestEmail(Order $order, string $token, ?string $note = null): void
    {
        $request = $order->getDesignRequest();
        $name = $request?->getFullName() ?? (string) $order->getEmail();
        $email = $this->compose(
            subject: self::BALANCE_SUBJECT . ', ' . ($this->firstName($order) ?? $name),
            template: '/email/order/balance_request.html.twig',
            email: (string) $order->getEmail(),
            name: $name,
            context: [
                'order' => $order,
                'token' => $token,
                'note' => $note,
                'firstName' => $this->firstName($order),
            ],
        );
        $email->replyTo(new Address($this->studioEmail, 'Llunio Studios'));

        $this->mailer->send($email);
    }

    /**
     * The receipt once a quote's balance is paid (Step 10b.4).
     */
    public function sendBalanceReceivedEmail(Order $order, int $balance): void
    {
        $request = $order->getDesignRequest();
        $name = $request?->getFullName() ?? (string) $order->getEmail();
        $email = $this->compose(
            subject: self::BALANCE_RECEIVED_SUBJECT . ' (' . $order->getOrderNumber() . ')',
            template: '/email/order/balance_received.html.twig',
            email: (string) $order->getEmail(),
            name: $name,
            context: [
                'order' => $order,
                'balance' => $balance,
                'firstName' => $this->firstName($order),
            ],
        );
        $email->replyTo(new Address($this->studioEmail, 'Llunio Studios'));

        $this->mailer->send($email);
    }

    /**
     * Tells the studio a request has arrived (replies go to the customer).
     */
    public function sendDesignRequestNotificationEmail(DesignRequest $designRequest): void
    {
        $email = $this->compose(
            subject: $subject = StudioSummary::requestSubject($designRequest),
            template: '/email/request/notification.html.twig',
            email: $this->studioNotificationEmail,
            name: 'Llunio Studios',
            context: [
                'designRequest' => $designRequest,
                'subject' => $subject,
                'lamps' => StudioSummary::lamps($designRequest),
                'customNames' => StudioSummary::customNames($designRequest),
                'giftDate' => StudioSummary::giftDate($designRequest),
            ],
        );
        $email->replyTo(new Address($designRequest->getEmail(), $designRequest->getFullName()));

        $this->mailer->send($email);
    }

    /**
     * "Begin the Conversation" (Step 7c.3): the customer's confirmation, in
     * the "me" voice and signed (ENQUIRY_SIGNATURE). Replies go to the studio.
     */
    public function sendEnquiryReceivedEmail(DesignRequest $enquiry): void
    {
        $email = $this->compose(
            // The first name, as in the greeting (13 October 2026); the whole name when none is usable.
            subject: self::ENQUIRY_SUBJECT . ', ' . ($enquiry->getFirstName() ?? $enquiry->getFullName()),
            template: '/email/enquiry/received.html.twig',
            email: $enquiry->getEmail(),
            name: $enquiry->getFullName(),
            context: [
                'designRequest' => $enquiry,
            ],
        );
        $email->replyTo(new Address($this->studioEmail, 'Llunio Studios'));

        $this->mailer->send($email);
    }

    /**
     * Tells the studio an enquiry has arrived (Step 7c.3): the summary first,
     * then everything sent; files listed, not attached. Replies go to the
     * customer.
     */
    public function sendEnquiryNotificationEmail(DesignRequest $enquiry): void
    {
        $email = $this->compose(
            subject: $subject = StudioSummary::enquirySubject($enquiry),
            template: '/email/enquiry/notification.html.twig',
            email: $this->studioNotificationEmail,
            name: 'Llunio Studios',
            context: [
                'designRequest' => $enquiry,
                'subject' => $subject,
                'lamps' => StudioSummary::lamps($enquiry),
            ],
        );
        $email->replyTo(new Address($enquiry->getEmail(), $enquiry->getFullName()));

        $this->mailer->send($email);
    }

    /**
     * Email 03 (Site v4 design): the customer's "Your idea has arrived".
     */
    public function sendCommissionReceivedEmail(CommissionEnquiry $enquiry): void
    {
        $this->send(
            // By first name, as the other enquiry subjects (wording pass 11.5, 16 October 2026).
            subject: 'Your commission enquiry' . (($first = GreetingName::firstName($enquiry->getName())) !== null ? ', ' . $first : ''),
            template: '/email/commission/received.html.twig',
            email: $enquiry->getEmail(),
            name: $enquiry->getName(),
            context: [
                'enquiry' => $enquiry,
                'firstName' => GreetingName::firstName($enquiry->getName()),
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
            subject: $subject = StudioSummary::commissionSubject($enquiry),
            template: '/email/commission/notification.html.twig',
            email: $this->studioNotificationEmail,
            name: 'Llunio Studios',
            context: [
                'enquiry' => $enquiry,
                'subject' => $subject,
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
                'firstName' => GreetingName::firstName($newsletterSubscriber->getName()),
            ]),
        );
    }

    /**
     * Tells the studio someone has signed up on /signup. Replying goes to them.
     */
    public function sendSignupNotificationEmail(NewsletterSubscriber $newsletterSubscriber): void
    {
        $templatedEmail = $this->compose(
            subject: 'New signup: ' . $newsletterSubscriber->getName(),
            template: '/email/newsletter/signup_notification.html.twig',
            email: self::STUDIO_EMAIL_ADDRESS,
            name: 'Llunio Studios',
            context: [
                'subscriber' => $newsletterSubscriber,
            ],
        );
        $templatedEmail->replyTo(new Address($newsletterSubscriber->getEmail(), $newsletterSubscriber->getName()));
        $templatedEmail->textTemplate('/email/newsletter/signup_notification.txt.twig');

        $this->mailer->send($templatedEmail);
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
                'name' => GreetingName::firstName($user->getFullName() ?? $user->getFirstName()),
            ],
        );
    }

    /**
     * Email 01 (Site v4 design): "Thank you for your order", once an order
     * is paid (in full, or a quote's deposit), with "View your order" (the
     * order's private page, Step 11.1). Replies go to the studio.
     */
    public function sendOrderConfirmationEmail(Order $order): void
    {
        if ($order->getEmail() === null) {
            return;
        }

        $email = $this->compose(
            subject: self::ORDER_SUBJECT . ' ' . $order->getOrderNumber(),
            template: '/email/order/confirmation.html.twig',
            email: $order->getEmail(),
            name: $order->getCustomerName() ?? $order->getEmail(),
            context: [
                'order' => $order,
                'firstName' => $this->firstName($order),
                'castingDate' => $this->orderDates->castingDate($order),
            ],
        );
        $email->replyTo(new Address($this->studioEmail, 'Llunio Studios'));

        $this->mailer->send($email);
    }

    /**
     * Email 04 (Site v4 design): "Your Lumen Forma is being cast", when the
     * studio marks the order In Production (Step 11.2). Replies go to the
     * studio.
     */
    public function sendInProductionEmail(Order $order): void
    {
        $this->sendStageEmail($order, self::IN_PRODUCTION_SUBJECT, '/email/order/in_production.html.twig');
    }

    /**
     * A stage's email (04–06): "{subject} (LS-…)" to the buyer, replies to
     * the studio.
     */
    private function sendStageEmail(Order $order, string $subject, string $template): void
    {
        if ($order->getEmail() === null) {
            throw new \LogicException('Order ' . $order->getOrderNumber() . ' has no email address.');
        }

        $email = $this->compose(
            subject: $subject . ' (' . $order->getOrderNumber() . ')',
            template: $template,
            email: $order->getEmail(),
            name: $order->getCustomerName() ?? $order->getEmail(),
            context: [
                'order' => $order,
                'firstName' => $this->firstName($order),
            ],
        );
        $email->replyTo(new Address($this->studioEmail, 'Llunio Studios'));

        $this->mailer->send($email);
    }

    /**
     * Email 05 (Site v4 design): "Your Lumen Forma is on its way", when the
     * studio marks the order Dispatched (Step 11.3). Replies go to the
     * studio.
     */
    public function sendDispatchedEmail(Order $order): void
    {
        $this->sendStageEmail($order, self::DISPATCHED_SUBJECT, '/email/order/dispatched.html.twig');
    }

    /**
     * Email 06 (Site v4 design): "Your Lumen Forma has arrived", when the
     * studio marks the order Delivered (Step 11.4). Replies go to the studio.
     */
    public function sendDeliveredEmail(Order $order): void
    {
        $this->sendStageEmail($order, self::DELIVERED_SUBJECT, '/email/order/delivered.html.twig');
    }

    /**
     * Tells the studio an order has been paid (Step 11.1): a new order
     * (checkout, or a quote paid in full), a quote's deposit or its
     * balance. Replies go to the customer.
     *
     * @param 'order'|'deposit'|'balance' $kind
     * @param int $amount the payment just taken, in pence
     */
    public function sendOrderPaidNotificationEmail(Order $order, string $kind, int $amount): void
    {
        $email = $this->compose(
            subject: $subject = StudioSummary::orderSubject($order, $kind, $amount),
            template: '/email/order/notification.html.twig',
            email: $this->studioNotificationEmail,
            name: 'Llunio Studios',
            context: [
                'order' => $order,
                'kind' => $kind,
                'kindLabel' => StudioSummary::orderKind($kind),
                'amount' => $amount,
                'subject' => $subject,
                'lamps' => StudioSummary::orderLamps($order),
                'castingDate' => $this->orderDates->castingDate($order),
            ],
        );
        if ($order->getEmail() !== null) {
            $email->replyTo(new Address($order->getEmail(), $order->getCustomerName() ?? $order->getEmail()));
        }

        $this->mailer->send($email);
    }

    /**
     * The buyer's first name (Greeting name, 9 October 2026), never a gift
     * recipient's: from the order's buyer name (billing name, name on card or
     * the wallet's payer), the request's name for a quote's order, otherwise
     * the account's; tidied by GreetingName. Null: "Dear Customer,".
     */
    private function firstName(Order $order): ?string
    {
        return GreetingName::firstName($order->getCustomerName() ?? $order->getDesignRequest()?->getFullName() ?? $order->getUser()?->getFullName());
    }

    /**
     * @param array<string, mixed> $context
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
     * @param array<string, mixed> $context
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
