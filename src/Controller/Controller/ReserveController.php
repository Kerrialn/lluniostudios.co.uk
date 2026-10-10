<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Catalogue\GiftDate;
use App\Catalogue\LumenFormaPhotos;
use App\Entity\DesignRequest;
use App\Entity\DesignRequestFile;
use App\Enum\GiftSendTo;
use App\Form\Form\CommissionFields;
use App\Form\Form\ReserveForm;
use App\Repository\CartRepository;
use App\Service\CartResolver;
use App\Service\CommissionFileStorage;
use App\Service\DesignRequestLamps;
use App\Service\EmailService;
use App\Shipping\DeliveryArea;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Reserve Your Design" (design merge, Step 8): larger sizes, Custom choices
 * and orders over the checkout lamp limit are sent as a request, with no
 * payment taken. The request carries every lamp in the basket plus the lamp
 * being configured (held by DesignRequestLamps), is saved for the studio to
 * quote (Step 10b), empties the basket, emails the customer (email 02) and
 * the studio, and ends on "Reservation received".
 */
final class ReserveController extends AbstractController
{
    private const RECEIVED_KEY = 'reserve_received';

    /**
     * Details typed on the Card Payment page before it found the postcode is
     * outside the UK mainland (CheckoutController), filled in here once.
     */
    public const PREFILL_KEY = 'reserve_prefill';

    public function __construct(
        private readonly CartResolver $cartResolver,
        private readonly CartRepository $cartRepository,
        private readonly DesignRequestLamps $lamps,
        private readonly EntityManagerInterface $entityManager,
        private readonly EmailService $emailService,
        private readonly LoggerInterface $logger,
        private readonly GiftDate $giftDate,
        private readonly CommissionFileStorage $storage,
        private readonly ?Profiler $profiler = null,
    ) {
    }

    /**
     * The request form. ?basket=1 (the basket's own "Reserve Your Design")
     * sends just the basket, forgetting any lamp held from the configurator.
     */
    #[Route(path: '/reserve', name: 'basket_request', methods: ['GET', 'POST'])]
    public function reserve(Request $request): Response
    {
        if ($request->query->getBoolean('basket')) {
            $this->lamps->forgetPending();

            return $this->redirectToRoute('basket_request');
        }

        $cart = $this->cartResolver->peekCart();
        $items = $this->lamps->items($cart);
        if ([] === $items) {
            return $this->redirectToRoute('lighting_designs');
        }

        $customChoices = DesignRequestLamps::hasCustomChoice($items);
        // What was written under the configurator's Custom choices starts the
        // "Custom Details" box (still editable).
        $carriedDetails = $customChoices ? $this->lamps->pendingCustomDetails() : '';
        $prefill = $request->isMethod('GET') ? $request->getSession()->remove(self::PREFILL_KEY) : null;
        $start = [
            'country' => 'GB',
        ];
        if (\is_array($prefill)) {
            $start = array_merge($start, array_filter(array_map(static fn (mixed $value): ?string => \is_string($value) && $value !== '' ? $value : null, $prefill), static fn (?string $value): bool => $value !== null));
            // A gift carried from checkout: its tick, date and choice as the form's own values.
            $start['gift'] = isset($start['gift']);
            $start['giftNeededBy'] = isset($start['giftNeededBy']) ? (\DateTimeImmutable::createFromFormat('!Y-m-d', $start['giftNeededBy']) ?: null) : null;
            $start['giftSendTo'] = GiftSendTo::tryFrom($start['giftSendTo'] ?? '');
        }

        if ($request->isMethod('GET') && '' !== $carriedDetails) {
            $start['customDetails'] = $carriedDetails;
        }

        $form = $this->createForm(ReserveForm::class, $start, [
            'custom_choices' => $customChoices,
            // Room for every carried description, and more besides.
            'custom_details_max' => max(ReserveForm::CUSTOM_DETAILS_MAX, mb_strlen($carriedDetails) + ReserveForm::CUSTOM_DETAILS_MAX),
            'earliest' => $this->giftDate->earliest(),
        ]);
        $tooLarge = $request->isMethod('POST') && $request->request->count() === 0 && $request->files->count() === 0;
        if ($tooLarge) {
            // More was sent than PHP accepts at once (reference photos over
            // post_max_size), so it arrives empty: as Bespoke Commissions.
            $this->profiler?->disable();
        }

        $form->handleRequest($request);
        if ($tooLarge && ! $form->isSubmitted()) {
            $form->submit(null, false);
            $form->addError(new FormError(CommissionFields::tooLargeMessage()));
        }

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{fullName: string, email: string, phone: string, addressLine1: string, addressLine2: ?string, town: string, county: ?string, postcode: string, country: string, accessNotes: ?string, customDetails?: ?string, gift?: bool, giftNeededBy?: ?\DateTimeImmutable, giftSendTo?: ?GiftSendTo, giftRecipientName?: ?string, giftMessage?: ?string, giftRecipientPhone?: ?string} $data */
            $data = $form->getData();
            $designRequest = new DesignRequest(
                fullName: trim($data['fullName']),
                email: trim($data['email']),
                phone: trim($data['phone']),
                accessNotes: null === $data['accessNotes'] ? null : trim($data['accessNotes']),
                customDetails: $customChoices ? trim((string) ($data['customDetails'] ?? '')) : null,
            );
            $optional = static fn (?string $value): ?string => ($value = trim((string) $value)) === '' ? null : $value;
            $designRequest->setAddress(
                trim($data['addressLine1']),
                $optional($data['addressLine2']),
                trim($data['town']),
                $optional($data['county']),
                (string) DeliveryArea::normalise($data['postcode']),
                $data['country'],
            );
            if ($data['gift'] ?? false) {
                $designRequest->setGift($data['giftNeededBy'], $data['giftSendTo'], $optional($data['giftRecipientName'] ?? null), $optional($data['giftMessage'] ?? null), $optional($data['giftRecipientPhone'] ?? null));
            }

            foreach ($items as $item) {
                $designRequest->addItem($item);
            }

            // Reference photos, kept privately; removed again if saving fails.
            /** @var list<DesignRequestFile> $stored */
            $stored = [];
            if ($customChoices) {
                foreach ((array) $form->get('referencePhotos')->getData() as $upload) {
                    if ($upload instanceof UploadedFile) {
                        $file = $this->storage->storeForRequest($upload);
                        $stored[] = $file;
                        $designRequest->addFile($file);
                    }
                }
            }

            $this->entityManager->persist($designRequest);

            // The basket's lamps are now in the request.
            if (null !== $cart) {
                foreach ($cart->getCartItems()->toArray() as $line) {
                    $cart->removeCartItem($line);
                }
                $this->cartRepository->save(cart: $cart);
            }

            try {
                $this->entityManager->flush();
            } catch (\Throwable $exception) {
                foreach ($stored as $file) {
                    $this->storage->remove($file);
                }

                throw $exception;
            }

            // What was typed is used, and the configurator's choices if its lamp was in the request.
            $request->getSession()->set(ConfiguratorController::FORGET_CHOICES_KEY, $this->lamps->hasNewPending() ? ['reserve', 'configurator'] : ['reserve']);
            $this->lamps->forgetPending();

            // The request is saved either way; a mail problem is logged, not shown.
            foreach (['sendDesignRequestReceivedEmail', 'sendDesignRequestNotificationEmail'] as $send) {
                try {
                    $this->emailService->{$send}($designRequest);
                } catch (\Throwable $exception) {
                    $this->logger->error('Request email failed', [
                        'request' => $designRequest->getRequestNumber(),
                        'email' => $send,
                        'exception' => $exception,
                    ]);
                }
            }

            $request->getSession()->set(self::RECEIVED_KEY, $designRequest->getRequestNumber());

            return $this->redirectToRoute('reserve_received');
        }

        $sizes = array_unique(array_map(static fn ($item): string => (string) $item->getProduct()?->getSlug(), $items));

        return $this->render('reserve/index.html.twig', [
            'form' => $form,
            'items' => $items,
            'customChoices' => $customChoices,
            'carriedDetails' => $carriedDetails,
            // The size's own photo only when every lamp is that size (never another size's).
            'photo' => 1 === \count($sizes) ? (LumenFormaPhotos::forSize((string) reset($sizes))[3] ?? null) : null,
        ]);
    }

    #[Route(path: '/reserve/received', name: 'reserve_received', methods: ['GET'])]
    public function received(Request $request): Response
    {
        $requestNumber = $request->getSession()->get(self::RECEIVED_KEY);
        if (! \is_string($requestNumber)) {
            return $this->redirectToRoute('lighting_designs');
        }

        return $this->render('reserve/received.html.twig');
    }
}
