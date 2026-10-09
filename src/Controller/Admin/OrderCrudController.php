<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\Courier;
use App\Enum\OrderStage;
use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use App\Service\EmailService;
use App\Service\OrderDates;
use App\Service\QuotePayments;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\UrlField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Orders, newest first. The gift column shows "Arrive by [date]" for gifts
 * (Step 10.5); an order's page also shows the customer's mobile, delivery
 * instructions and billing address from the Card Payment page.
 *
 * A quote paid by deposit (Step 10b.4): "Request balance" (on the order's
 * page and in the list, while a balance is owed) asks to confirm, then emails
 * the customer to pay the rest on the quote page, with an optional personal
 * note; it can be sent again. A request whose order is Deposit Paid has the
 * same action, which comes here and returns to the request.
 *
 * @extends AbstractCrudController<Order>
 */
class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    /**
     * The balance email's personal note, as long as the quote's.
     */
    private const NOTE_MAX = 2000;

    public function configureActions(Actions $actions): Actions
    {
        $requestBalance = Action::new('requestBalance', 'Request balance', 'fa fa-envelope')
            ->linkToCrudAction('requestBalance')
            ->displayIf(static fn (Order $order): bool => $order->canRequestBalance() && $order->getDesignRequest() !== null);

        // Step 11.2: a placed order not yet in production.
        $inProduction = static fn (): Action => Action::new('markInProduction', 'Mark In Production', 'fa fa-industry')
            ->linkToCrudAction('markInProduction')
            ->displayIf(static fn (Order $order): bool => $order->canStartProduction());

        // Step 11.3: a placed order not yet dispatched (the page explains if the balance is still owed).
        $dispatched = static fn (): Action => Action::new('markDispatched', 'Mark Dispatched', 'fa fa-truck')
            ->linkToCrudAction('markDispatched')
            ->displayIf(static fn (Order $order): bool => $order->canDispatch());

        return $actions
            ->add(Crud::PAGE_DETAIL, $requestBalance)
            ->add(Crud::PAGE_INDEX, $requestBalance)
            ->add(Crud::PAGE_DETAIL, $inProduction()->addCssClass('btn btn-primary'))
            ->add(Crud::PAGE_INDEX, $inProduction())
            ->add(Crud::PAGE_DETAIL, $dispatched()->addCssClass('btn btn-primary'))
            ->add(Crud::PAGE_INDEX, $dispatched());
    }

    /**
     * "Mark Dispatched" (Step 11.3): only once paid in full (otherwise the
     * page points to "Request balance"); a page to confirm the courier (DPD
     * or Palletways) and the tracking number, then the stage is set and
     * email 05 sent. If the email can't be sent, nothing is marked.
     *
     * @param AdminContext<Order> $context
     */
    #[AdminRoute(path: '/{entityId}/dispatched', name: 'dispatched', options: [
        'methods' => ['GET', 'POST'],
    ])]
    public function markDispatched(AdminContext $context, Request $request, EmailService $emails, EntityManagerInterface $entityManager, AdminUrlGenerator $urls, LoggerInterface $logger): Response
    {
        $order = $context->getEntity()->getInstance();
        if (! $order instanceof Order) {
            throw $this->createNotFoundException();
        }

        $detail = $urls->unsetAll()->setController(self::class)->setAction(Action::DETAIL)->setEntityId($order->getId())->generateUrl();
        if (! $order->canDispatch()) {
            $this->addFlash('warning', 'This order can\'t be marked Dispatched.');

            return $this->redirect($detail);
        }

        $courier = $order->getLikelyCourier();
        $tracking = '';
        $problem = null;
        if ($request->isMethod('POST') && $order->canDispatchNow()) {
            if (! $this->isCsrfTokenValid('dispatched-' . $order->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Something went wrong; please try again.');

                return $this->redirect($detail);
            }

            $courier = Courier::tryFrom((string) $request->request->get('courier')) ?? $courier;
            $tracking = trim((string) $request->request->get('trackingNumber'));
            if ($tracking === '') {
                $problem = 'Please enter the tracking number.';
            } elseif (preg_match('/^[A-Za-z0-9 -]{4,64}$/', $tracking) !== 1) {
                $problem = 'Please enter a tracking number of letters and numbers only (4 to 64).';
            } else {
                $order->markDispatched($courier, $tracking, new CarbonImmutable());
                try {
                    $emails->sendDispatchedEmail($order);
                    $entityManager->flush();
                    $this->addFlash('success', 'Marked Dispatched: ' . $order->getEmail() . ' has been emailed (' . $courier->label() . ', tracking number ' . $tracking . ').');
                } catch (\Throwable $exception) {
                    $logger->error('Dispatched email failed', [
                        'order' => $order->getOrderNumber(),
                        'exception' => $exception,
                    ]);
                    $entityManager->refresh($order);
                    $this->addFlash('danger', 'The email could not be sent, so the order has not been marked Dispatched. Please try again.');
                }

                return $this->redirect($detail);
            }
        }

        return $this->render('admin/dispatched.html.twig', [
            'order' => $order,
            'backUrl' => $detail,
            'couriers' => Courier::cases(),
            'courier' => $courier,
            'tracking' => $tracking,
            'problem' => $problem,
            'requestBalanceUrl' => $order->canRequestBalance() && $order->getDesignRequest() !== null
                ? $urls->unsetAll()->setController(self::class)->setAction('requestBalance')->setEntityId($order->getId())->generateUrl()
                : null,
        ]);
    }

    /**
     * "Mark In Production" (Step 11.2): a page to confirm with the expected
     * dispatch date (suggested: a quote's casting date, otherwise the end of
     * the lead time from payment), then the stage is set and email 04 sent.
     * If the email can't be sent, nothing is marked.
     *
     * @param AdminContext<Order> $context
     */
    #[AdminRoute(path: '/{entityId}/in-production', name: 'in_production', options: [
        'methods' => ['GET', 'POST'],
    ])]
    public function markInProduction(AdminContext $context, Request $request, OrderDates $dates, EmailService $emails, EntityManagerInterface $entityManager, AdminUrlGenerator $urls, LoggerInterface $logger): Response
    {
        $order = $context->getEntity()->getInstance();
        if (! $order instanceof Order) {
            throw $this->createNotFoundException();
        }

        $detail = $urls->unsetAll()->setController(self::class)->setAction(Action::DETAIL)->setEntityId($order->getId())->generateUrl();
        if (! $order->canStartProduction()) {
            $this->addFlash('warning', 'This order can\'t be marked In Production.');

            return $this->redirect($detail);
        }

        $today = OrderDates::today();
        $date = $dates->suggestedDispatch($order);
        $problem = null;
        if ($request->isMethod('POST')) {
            if (! $this->isCsrfTokenValid('in-production-' . $order->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Something went wrong; please try again.');

                return $this->redirect($detail);
            }

            $typed = OrderDates::day((string) $request->request->get('expectedDispatch'));
            if ($typed === null) {
                $problem = 'Please enter the expected dispatch date.';
            } elseif ($typed < $today) {
                $problem = 'The expected dispatch date can\'t be in the past.';
                $date = $typed;
            } else {
                $order->markInProduction($typed, new CarbonImmutable());
                try {
                    $emails->sendInProductionEmail($order);
                    $entityManager->flush();
                    $this->addFlash('success', 'Marked In Production: ' . $order->getEmail() . ' has been emailed (expected dispatch ' . $typed->format('j F Y') . ').');
                } catch (\Throwable $exception) {
                    $logger->error('In Production email failed', [
                        'order' => $order->getOrderNumber(),
                        'exception' => $exception,
                    ]);
                    $entityManager->refresh($order);
                    $this->addFlash('danger', 'The email could not be sent, so the order has not been marked In Production. Please try again.');
                }

                return $this->redirect($detail);
            }
        }

        return $this->render('admin/in_production.html.twig', [
            'order' => $order,
            'backUrl' => $detail,
            'date' => $date,
            'today' => $today,
            'castingDate' => $dates->castingDate($order),
            'problem' => $problem,
        ]);
    }

    /**
     * "Request balance": a page to confirm (what is owed, when it was last
     * asked for), then the email.
     *
     * @param AdminContext<Order> $context
     */
    #[AdminRoute(path: '/{entityId}/request-balance', name: 'request_balance', options: [
        'methods' => ['GET', 'POST'],
    ])]
    public function requestBalance(AdminContext $context, Request $request, QuotePayments $payments, AdminUrlGenerator $urls, LoggerInterface $logger): Response
    {
        $order = $context->getEntity()->getInstance();
        if (! $order instanceof Order) {
            throw $this->createNotFoundException();
        }

        // Back to the request when asked from there, otherwise to the order.
        $designRequest = $request->query->get('from') === 'request' ? $order->getDesignRequest() : null;
        $detail = $designRequest !== null
            ? $urls->unsetAll()->setController(DesignRequestCrudController::class)->setAction(Action::DETAIL)->setEntityId($designRequest->getId())->generateUrl()
            : $urls->unsetAll()->setController(self::class)->setAction(Action::DETAIL)->setEntityId($order->getId())->generateUrl();
        if (! $order->canRequestBalance() || $payments->tokenFor($order) === null) {
            $this->addFlash('warning', 'There is no balance to request for this order.');

            return $this->redirect($detail);
        }

        if ($request->isMethod('POST')) {
            if (! $this->isCsrfTokenValid('request-balance-' . $order->getId(), (string) $request->request->get('_token'))) {
                $this->addFlash('danger', 'Something went wrong; please try again.');

                return $this->redirect($detail);
            }

            $note = trim((string) $request->request->get('note'));
            if (mb_strlen($note) > self::NOTE_MAX) {
                $this->addFlash('danger', 'The personal note can be at most ' . self::NOTE_MAX . ' characters. Nothing has been sent.');

                return $this->render('admin/request_balance.html.twig', [
                    'order' => $order,
                    'backUrl' => $detail,
                    'note' => $note,
                    'noteMax' => self::NOTE_MAX,
                ]);
            }

            try {
                $payments->requestBalance($order, $note === '' ? null : $note);
                $this->addFlash('success', 'Balance requested: ' . $order->getEmail() . ' has been emailed to pay ' . self::money($order->getAmountOwed()) . '.');
            } catch (\Throwable $exception) {
                $logger->error('Requesting a balance failed', [
                    'order' => $order->getOrderNumber(),
                    'exception' => $exception,
                ]);
                $this->addFlash('danger', 'The balance request could not be sent. Please try again.');
            }

            return $this->redirect($detail);
        }

        return $this->render('admin/request_balance.html.twig', [
            'order' => $order,
            'backUrl' => $detail,
            'note' => '',
            'noteMax' => self::NOTE_MAX,
        ]);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(self::statusLabels()))
            ->add(ChoiceFilter::new('stage')->setChoices(self::stageLabels()));
    }

    /**
     * The stages (Step 11.2): label => stored value.
     *
     * @return array<string, string>
     */
    private static function stageLabels(): array
    {
        return array_combine(
            array_map(static fn (OrderStage $s): string => $s->label(), OrderStage::cases()),
            array_map(static fn (OrderStage $s): string => $s->value, OrderStage::cases()),
        );
    }

    /**
     * Label => stored value, for showing and filtering.
     *
     * @return array<string, string>
     */
    private static function statusLabels(): array
    {
        return array_combine(
            array_map(static fn (OrderStatus $s): string => $s->label(), OrderStatus::cases()),
            array_map(static fn (OrderStatus $s): string => $s->value, OrderStatus::cases()),
        );
    }

        private static function money(int $pence): string
    {
        return '£' . number_format($pence / 100, 2);
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Order')
            ->setEntityLabelInPlural('Orders')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('orderNumber')->setDisabled(),
            TextField::new('email'),
            // Shown by its label ("Deposit Paid"; with the cases themselves
            // EasyAdmin shows their names, e.g. DEPOSIT_PAID); edited as the case.
            ChoiceField::new('status')
                ->setChoices(self::statusLabels())
                ->renderAsBadges([
                    OrderStatus::PENDING->value => 'secondary',
                    OrderStatus::AWAITING_PAYMENT->value => 'warning',
                    OrderStatus::DEPOSIT_PAID->value => 'primary',
                    OrderStatus::PAID->value => 'success',
                    OrderStatus::FAILED->value => 'danger',
                    OrderStatus::CANCELLED->value => 'secondary',
                    OrderStatus::FULFILLED->value => 'success',
                ])
                ->hideOnForm(),
            // The stage's label, or nothing before it is marked (EasyAdmin would show "Null").
            TextField::new('stageLabel', 'Stage')->hideOnForm(),
            ChoiceField::new('status')
                ->setChoices(array_combine(
                    array_map(static fn (OrderStatus $s) => $s->label(), OrderStatus::cases()),
                    OrderStatus::cases(),
                ))
                ->onlyOnForms(),
            MoneyField::new('subtotal')->setCurrency('GBP')->setStoredAsCents(true),
            MoneyField::new('shippingCost')->setCurrency('GBP')->setStoredAsCents(true),
            MoneyField::new('total')->setCurrency('GBP')->setStoredAsCents(true),
            // What has been paid and is still owed (a quote paid by deposit, Step 10b.3).
            MoneyField::new('amountPaid', 'Paid')->setCurrency('GBP')->setStoredAsCents(true)->setDisabled(),
            MoneyField::new('amountOwed', 'Owed')->setCurrency('GBP')->setStoredAsCents(true)->hideOnForm(),
            AssociationField::new('designRequest', 'Request')->hideOnIndex()->setDisabled(),
            TextField::new('giftLabel', 'Gift')->hideOnForm(),
            // Gift options (9 October 2026): a direct gift's packing note, its message (for the card) and the recipient's mobile.
            TextField::new('giftPackingNote', 'Packing')->onlyOnDetail()->renderAsHtml(),
            TextField::new('giftMessage', 'Gift message')->onlyOnDetail(),
            TextField::new('giftRecipientPhone', "Recipient's mobile")->onlyOnDetail(),
            TextField::new('customerName', 'Customer (buyer)')->onlyOnDetail(),
            TextField::new('contactPhone', 'Mobile')->hideOnIndex()->setDisabled(),
            TextField::new('deliveryNotes', 'Delivery instructions')->hideOnIndex()->setDisabled(),
            ChoiceField::new('shippingMethod')
                ->setChoices(array_combine(
                    array_map(static fn (ShippingMethod $m) => $m->label(), ShippingMethod::cases()),
                    ShippingMethod::cases(),
                ))
                ->hideOnIndex(),
            TextField::new('shippingCarrier')->hideOnIndex(),
            TextField::new('shippingServiceName')->hideOnIndex(),
            AssociationField::new('shippingAddress')->hideOnIndex(),
            TextField::new('billingAddressLabel', 'Billing address')->onlyOnDetail(),
            TextField::new('revolutOrderId')->hideOnIndex()->setDisabled(),
            TextField::new('revolutState')->hideOnIndex()->setDisabled(),
            DateTimeField::new('paidAt')->hideOnIndex()->setDisabled(),
            DateTimeField::new('balanceRequestedAt', 'Balance requested')->hideOnIndex()->hideOnForm(),
            DateTimeField::new('balancePaidAt', 'Balance paid')->hideOnIndex()->hideOnForm(),
            // Step 11.2: the studio's stage.
            DateTimeField::new('inProductionAt', 'In Production since')->hideOnIndex()->hideOnForm(),
            DateField::new('expectedDispatch', 'Expected dispatch')->hideOnIndex()->hideOnForm(),
            // Step 11.3.
            DateTimeField::new('dispatchedAt', 'Dispatched')->hideOnIndex()->hideOnForm(),
            TextField::new('courierLabel', 'Courier')->hideOnIndex()->hideOnForm(),
            TextField::new('trackingNumber', 'Tracking number')->hideOnIndex()->hideOnForm(),
            UrlField::new('trackingUrl', 'Tracking link')->hideOnIndex()->hideOnForm(),
            DateTimeField::new('createdAt')->setDisabled(),
            CollectionField::new('items')->onlyOnDetail(),
        ];
    }
}
