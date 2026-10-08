<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Catalogue\LumenFormaPhotos;
use App\Entity\DesignRequest;
use App\Form\Form\ReserveForm;
use App\Repository\CartRepository;
use App\Service\CartResolver;
use App\Service\DesignRequestLamps;
use App\Service\EmailService;
use App\Shipping\DeliveryArea;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
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
        $prefill = $request->isMethod('GET') ? $request->getSession()->remove(self::PREFILL_KEY) : null;
        $start = [
            'country' => 'GB',
        ];
        if (\is_array($prefill)) {
            $start = array_merge($start, array_filter(array_map(static fn (mixed $value): ?string => \is_string($value) && $value !== '' ? $value : null, $prefill), static fn (?string $value): bool => $value !== null));
        }

        $form = $this->createForm(ReserveForm::class, $start, [
            'custom_choices' => $customChoices,
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            /** @var array{fullName: string, email: string, phone: string, addressLine1: string, addressLine2: ?string, town: string, county: ?string, postcode: string, country: string, accessNotes: ?string, customDetails?: ?string} $data */
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
            foreach ($items as $item) {
                $designRequest->addItem($item);
            }

            $this->entityManager->persist($designRequest);

            // The basket's lamps are now in the request.
            if (null !== $cart) {
                foreach ($cart->getCartItems()->toArray() as $line) {
                    $cart->removeCartItem($line);
                }
                $this->cartRepository->save(cart: $cart);
            }

            $this->entityManager->flush();
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
