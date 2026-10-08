<?php

declare(strict_types=1);

namespace App\Controller\Controller;

use App\Entity\Quote;
use App\Service\Quotes;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

/**
 * The customer's private quote page (Step 10b.2), from the quote email's
 * "Pay now": what was last sent (later changes in admin show only once sent
 * again), valid for 14 days. Paying here comes with 10b.3. Anyone with the
 * link can see it, so it is never indexed.
 */
final class QuoteController extends AbstractController
{
    public function __construct(
        private readonly Quotes $quotes,
    ) {
    }

    #[Route(path: '/quote/{token}', name: 'quote_show', methods: ['GET'], requirements: [
        'token' => '[A-Za-z0-9_-]{16,64}',
    ])]
    public function show(string $token): Response
    {
        $quote = $this->quotes->findByToken($token);
        if (! $quote instanceof Quote || ! $quote->isSent()) {
            throw $this->createNotFoundException();
        }

        $response = $this->render('quote/show.html.twig', [
            'designRequest' => $quote->getDesignRequest(),
            'quote' => $this->quotes->view($quote),
            'validUntil' => $quote->getExpiresAt(),
            'expired' => $quote->isExpired(),
        ]);
        $response->headers->set('X-Robots-Tag', 'noindex, nofollow');

        return $response;
    }
}
