<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\DesignRequest;
use App\Entity\DesignRequestFile;
use App\Entity\DesignRequestItem;
use App\Enum\EnquiryTalk;
use App\Form\Form\CommissionFields;
use App\Form\Form\EnquiryForm;
use App\Form\Form\ReserveForm;
use App\Service\CommissionFileStorage;
use App\Service\DesignRequestLamps;
use App\Shipping\DeliveryArea;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Form\FormError;
use Symfony\Component\Form\FormInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Begin the Conversation" (Step 7c): the enquiry for a larger piece
 * (Atelier, Estate, Monument), reached from the larger-piece step's button
 * (ConfiguratorController::add() holds the size and quantity in
 * DesignRequestLamps). Reserve Your Design stays as it is for Studio and
 * Gallery requests.
 *
 * 7c.2: a complete enquiry is saved as a request marked as an enquiry (an
 * LR- number, in admin's Requests with the reservations), its files kept
 * privately as Reserve's reference photos; spam protection as Bespoke
 * Commissions. The basket is left as it is. The thank-you page and the
 * emails come in 7c.3.
 */
final class EnquiryController extends AbstractController
{
    private const RECEIVED_KEY = 'enquiry_received';

    public function __construct(
        private readonly DesignRequestLamps $lamps,
        private readonly EntityManagerInterface $entityManager,
        private readonly CommissionFileStorage $storage,
        #[Autowire(service: 'limiter.larger_enquiry')]
        private readonly RateLimiterFactoryInterface $limiter,
        // The studio's public email (STUDIO_EMAIL in .env).
        #[Autowire('%env(STUDIO_EMAIL)%')]
        private readonly string $studioEmail = '',
        private readonly ?Profiler $profiler = null,
    ) {
    }

    #[Route(path: '/begin-the-conversation', name: 'larger_enquiry', methods: ['GET', 'POST'])]
    public function enquiry(Request $request): Response
    {
        $item = $this->lamps->largerItem();
        if (null === $item) {
            return $this->redirectToRoute('lighting_designs');
        }

        $form = $this->createForm(EnquiryForm::class);

        // More was sent than PHP accepts at once (files over post_max_size), so it
        // arrives empty: as Bespoke Commissions.
        if ($request->isMethod('POST') && $request->request->count() === 0 && $request->files->count() === 0) {
            $this->profiler?->disable();
            $form->submit([]);
            $form->addError(new FormError(CommissionFields::tooLargeMessage()));
        } else {
            $form->handleRequest($request);
        }

        if ($form->isSubmitted() && $form->isValid()) {
            // Spam bots fill in the hidden field: thank them, save nothing.
            if (trim((string) $form->get(CommissionFields::HONEYPOT)->getData()) !== '') {
                $request->getSession()->set(self::RECEIVED_KEY, true);

                return $this->redirectToRoute('larger_enquiry_received');
            }

            if ($this->limiter->create($request->getClientIp() ?? 'unknown')->consume()->isAccepted()) {
                $this->save($form, $item);

                // The piece and what was typed are used; a Studio or Gallery design in progress is kept.
                $this->lamps->forgetLarger();
                $request->getSession()->set(ConfiguratorController::FORGET_CHOICES_KEY, ['enquiry', 'larger']);
                $request->getSession()->set(self::RECEIVED_KEY, true);

                return $this->redirectToRoute('larger_enquiry_received');
            }

            // DRAFT wording until the wording pass (11.5).
            $form->addError(new FormError("You've sent several enquiries in a short time. Please try again in an hour, or email me at " . $this->studioEmail . '.'));
        }

        return $this->render('enquiry/index.html.twig', [
            'form' => $form,
            'item' => $item,
        ], new Response(status: $form->isSubmitted() ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }

    /**
     * 7c.2 only: a placeholder until the thank-you page (7c.3).
     */
    #[Route(path: '/begin-the-conversation/received', name: 'larger_enquiry_received', methods: ['GET'])]
    public function received(Request $request): Response
    {
        if ($request->getSession()->get(self::RECEIVED_KEY) !== true) {
            return $this->redirectToRoute('lighting_designs');
        }

        return $this->render('enquiry/received.html.twig');
    }

    /**
     * Save the enquiry with its piece and files. If saving fails, files
     * already moved into storage are removed again.
     */
    private function save(FormInterface $form, DesignRequestItem $item): void
    {
        $text = static fn (string $field): ?string => ($value = trim((string) $form->get($field)->getData())) === '' ? null : $value;
        $talk = $form->get('talk')->getData();
        \assert($talk instanceof EnquiryTalk);
        $postcode = (string) DeliveryArea::normalise((string) $text('postcode'));
        $enquiry = DesignRequest::enquiry(
            fullName: (string) $text('fullName'),
            email: (string) $text('email'),
            phone: $text('phone'),
            talk: $talk,
            bestTime: $text('bestTime'),
            description: (string) $text('description'),
            boardLink: $text('boardLink'),
            postcode: $postcode,
            country: ReserveForm::countryForPostcode($postcode),
        );
        $enquiry->addItem($item);

        /** @var list<DesignRequestFile> $stored */
        $stored = [];
        try {
            foreach ((array) $form->get('files')->getData() as $upload) {
                if ($upload instanceof UploadedFile) {
                    $file = $this->storage->storeForRequest($upload);
                    $stored[] = $file;
                    $enquiry->addFile($file);
                }
            }

            $this->entityManager->persist($enquiry);
            $this->entityManager->flush();
        } catch (\Throwable $exception) {
            foreach ($stored as $file) {
                $this->storage->remove($file);
            }

            throw $exception;
        }
    }
}
