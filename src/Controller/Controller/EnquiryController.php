<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Form\Form\CommissionFields;
use App\Form\Form\EnquiryForm;
use App\Service\DesignRequestLamps;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Profiler\Profiler;
use Symfony\Component\Routing\Attribute\Route;

/**
 * "Begin the Conversation" (Step 7c): the enquiry for a larger piece
 * (Atelier, Estate, Monument), reached from the larger-piece step's button
 * (ConfiguratorController::add() holds the size and quantity in
 * DesignRequestLamps). Reserve Your Design stays as it is for Studio and
 * Gallery requests.
 *
 * 7c.1: the page and its checks only. A complete enquiry is accepted but not
 * yet saved or emailed (7c.2, 7c.3).
 */
final class EnquiryController extends AbstractController
{
    public function __construct(
        private readonly DesignRequestLamps $lamps,
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

        $complete = $form->isSubmitted() && $form->isValid();

        return $this->render('enquiry/index.html.twig', [
            'form' => $form,
            'item' => $item,
            // 7c.1 only: a complete enquiry isn't saved yet.
            'complete' => $complete,
        ], new Response(status: $form->isSubmitted() && ! $complete ? Response::HTTP_UNPROCESSABLE_ENTITY : Response::HTTP_OK));
    }
}
