<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DesignRequest;
use App\Enum\DesignRequestStatus;
use App\Enum\QuotePayment;
use App\Form\Form\QuoteForm;
use App\Service\QuoteDelivery;
use App\Service\QuotePayments;
use App\Service\Quotes;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * "Reserve Your Design" requests, newest first, with their status (New,
 * Quoted, Deposit Paid, Paid). Requests come from the website only; the customer's details
 * and lamps are read-only here. The status can be changed by hand until the
 * quotes step (Step 10b) sets it. "Quote" opens the request's quote (10b.1:
 * prepared and saved as a draft; 10b.2: "Send quote" emails it and marks the
 * request Quoted).
 *
 * @extends AbstractCrudController<DesignRequest>
 */
class DesignRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DesignRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Request')
            ->setEntityLabelInPlural('Requests')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (DesignRequest $request): string => 'Request ' . $request->getRequestNumber())
            ->setSearchFields(['requestNumber', 'fullName', 'email', 'phone', 'addressLine1', 'addressLine2', 'town', 'county', 'postcode', 'deliveryAddress'])
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        $quote = Action::new('quote', 'Quote', 'fa fa-file-invoice')->linkToCrudAction('quote');
        // A request whose order is Deposit Paid: "Request balance" (the order's
        // confirm page, returning here), as on the order (Step 10b.4).
        $requestBalance = static fn (): Action => Action::new('requestBalance', 'Request balance', 'fa fa-envelope')
            ->linkToCrudAction('requestBalance')
            ->displayIf(static fn (DesignRequest $request): bool => $request->getStatus() === DesignRequestStatus::DEPOSIT_PAID);

        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $quote)
            ->add(Crud::PAGE_INDEX, $requestBalance())
            ->add(Crud::PAGE_DETAIL, $quote)
            ->add(Crud::PAGE_DETAIL, $requestBalance()->addCssClass('btn btn-primary'));
    }

    /**
     * "Request balance" from a request: its order's confirm page, which
     * returns to the request afterwards.
     *
     * @param AdminContext<DesignRequest> $context
     */
    #[AdminRoute(path: '/{entityId}/request-balance', name: 'request_balance')]
    public function requestBalance(AdminContext $context, QuotePayments $payments, AdminUrlGenerator $urls): Response
    {
        $designRequest = $context->getEntity()->getInstance();
        if (! $designRequest instanceof DesignRequest) {
            throw $this->createNotFoundException();
        }

        $order = $payments->paidOrder($designRequest);
        if ($order === null || ! $order->canRequestBalance()) {
            $this->addFlash('warning', 'There is no balance to request for this request.');

            return $this->redirect($urls->setController(self::class)->setAction(Action::DETAIL)->setEntityId($designRequest->getId())->generateUrl());
        }

        return $this->redirect($urls->setController(OrderCrudController::class)->setAction('requestBalance')->setEntityId($order->getId())->set('from', 'request')->generateUrl());
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('status')->setChoices(self::statusLabels()));
    }

    public function configureFields(string $pageName): iterable
    {
        // A request received before the address was split into fields shows
        // its single address box instead.
        $request = $this->getContext()?->getEntity()->getInstance();
        if ($request instanceof DesignRequest && $request->getAddressLine1() === null) {
            $address = [
                TextareaField::new('legacyDeliveryAddress', 'Delivery address')->setDisabled()->hideOnIndex(),
            ];
        } else {
            $address = [
                TextField::new('addressLine1', 'Address line 1')->setDisabled()->hideOnIndex(),
                TextField::new('addressLine2', 'Address line 2')->setDisabled()->hideOnIndex(),
                TextField::new('town', 'Town/City')->setDisabled()->hideOnIndex(),
                TextField::new('county')->setDisabled()->hideOnIndex(),
                TextField::new('postcode')->setDisabled(),
                TextField::new('countryName', 'Country')->setDisabled()->hideOnIndex(),
            ];
        }

        return [
            TextField::new('requestNumber', 'Request')->setDisabled(),
            DateTimeField::new('createdAt', 'Received')->setFormat('d MMM y, HH:mm')->setTimezone('Europe/London')->setDisabled(),
            // Shown by its label ("Deposit Paid"; with the cases themselves
            // EasyAdmin shows their names, e.g. DEPOSIT_PAID); edited as the case.
            ChoiceField::new('status')
                ->setChoices(self::statusLabels())
                ->renderAsBadges([
                    DesignRequestStatus::NEW->value => 'warning',
                    DesignRequestStatus::QUOTED->value => 'info',
                    DesignRequestStatus::DEPOSIT_PAID->value => 'primary',
                    DesignRequestStatus::PAID->value => 'success',
                ])
                ->hideOnForm(),
            ChoiceField::new('status')->setChoices(self::statusChoices())->onlyOnForms(),
            TextField::new('fullName', 'Name')->setDisabled(),
            EmailField::new('email')->setDisabled(),
            TextField::new('phone')->setDisabled()->hideOnIndex(),
            IntegerField::new('lampCount', 'Lamps')->setDisabled()->onlyOnIndex(),
            ...$address,
            TextareaField::new('accessNotes', 'Site access & delivery notes')->setDisabled()->hideOnIndex(),
            TextareaField::new('customDetails')->setDisabled()->hideOnIndex(),
            // Gift options (9 October 2026).
            TextField::new('giftLabel', 'Gift')->hideOnForm(),
            TextField::new('giftPackingNote', 'Packing')->onlyOnDetail()->renderAsHtml(),
            TextField::new('giftRecipientName', "Recipient's name")->onlyOnDetail(),
            TextField::new('giftMessage', 'Gift message')->onlyOnDetail(),
            TextField::new('giftRecipientPhone', "Recipient's mobile")->onlyOnDetail(),
            Field::new('items', 'Lamps')
                ->setTemplatePath('admin/field/design_request_items.html.twig')
                ->onlyOnDetail(),
        ];
    }

    /**
     * The request's quote: a draft until it is sent (10b.2). Prices start
     * from the request's sizes and the delivery estimate; saving keeps it.
     * "Send quote" saves, then emails it (only when it has a casting date and
     * a total); sent again after changes, it is valid for a new 14 days.
     *
     * @param AdminContext<DesignRequest> $context
     */
    #[AdminRoute(path: '/{entityId}/quote', name: 'quote', options: [
        'methods' => ['GET', 'POST'],
    ])]
    public function quote(AdminContext $context, Request $request, Quotes $quotes, QuoteDelivery $delivery, AdminUrlGenerator $urls, LoggerInterface $logger, QuotePayments $payments): Response
    {
        $designRequest = $context->getEntity()->getInstance();
        if (! $designRequest instanceof DesignRequest) {
            throw $this->createNotFoundException();
        }

        $quote = $quotes->draftFor($designRequest);
        $form = $this->createForm(QuoteForm::class, QuoteForm::dataFrom($quote));
        $form->handleRequest($request);
        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{lamps: array<int, int>, extras: array<int, array{label: string, price: int}>, delivery: int, deliveryLabel: string, payment: QuotePayment, depositPercent: int, castingDate: ?\DateTimeImmutable, note: ?string} $data */
            $data = $form->getData();
            $quotes->save($quote, $data);
            if (! $request->request->has('send')) {
                $this->addFlash('success', $quote->isSent()
                    ? 'Quote saved. Not sent: the customer still sees the quote sent on ' . $this->ukDate($quote->getSentAt()) . '.'
                    : 'Quote saved as a draft. Nothing has been sent to the customer.');
            } elseif ($payments->paidOrder($designRequest) !== null) {
                // Paid: what the customer paid for stays as sent (10b.4).
                $this->addFlash('danger', 'Quote saved but not sent: it has been paid, so it can\'t be sent again.');
            } elseif (($problem = $quotes->sendingProblem($quote)) !== null) {
                $this->addFlash('danger', 'Quote saved but not sent. ' . $problem);
            } else {
                try {
                    $quotes->send($quote);
                    $this->addFlash('success', 'Quote sent to ' . $designRequest->getEmail() . '. It is valid until ' . $this->ukDate($quote->getExpiresAt()) . '.');
                } catch (\Throwable $exception) {
                    $logger->error('Sending a quote failed', [
                        'request' => $designRequest->getRequestNumber(),
                        'exception' => $exception,
                    ]);
                    $this->addFlash('danger', 'Quote saved but not sent: the email could not be sent. Please try again.');
                }
            }

            return $this->redirect($urls->setController(self::class)->setAction('quote')->setEntityId($designRequest->getId())->generateUrl());
        }

        return $this->render('admin/quote.html.twig', [
            'designRequest' => $designRequest,
            'quote' => $quote,
            'saved' => $quote->getId() !== null,
            'paidOrder' => ($paidOrder = $payments->paidOrder($designRequest)),
            'orderUrl' => $paidOrder === null ? null : $urls->setController(OrderCrudController::class)->setAction(Action::DETAIL)->setEntityId($paidOrder->getId())->generateUrl(),
            'requestBalanceUrl' => $paidOrder?->canRequestBalance() ? $urls->setController(OrderCrudController::class)->setAction('requestBalance')->setEntityId($paidOrder->getId())->generateUrl() : null,
            'customerUrl' => $quote->getToken() === null ? null : $this->generateUrl('quote_show', [
                'token' => $quote->getToken(),
            ]),
            'form' => $form,
            'estimate' => $delivery->estimate($designRequest),
            'backUrl' => $urls->setController(self::class)->setAction(Action::DETAIL)->setEntityId($designRequest->getId())->generateUrl(),
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    private function ukDate(?\DateTimeImmutable $date): string
    {
        return $date?->setTimezone(new \DateTimeZone('Europe/London'))->format('j F Y') ?? '';
    }

    /**
     * Label => stored value, for showing and filtering.
     *
     * @return array<string, string>
     */
    private static function statusLabels(): array
    {
        return array_combine(
            array_map(static fn (DesignRequestStatus $status): string => $status->label(), DesignRequestStatus::cases()),
            array_map(static fn (DesignRequestStatus $status): string => $status->value, DesignRequestStatus::cases()),
        );
    }

    /**
     * @return array<string, DesignRequestStatus>
     */
    private static function statusChoices(): array
    {
        return array_combine(
            array_map(static fn (DesignRequestStatus $status): string => $status->label(), DesignRequestStatus::cases()),
            DesignRequestStatus::cases(),
        );
    }
}
