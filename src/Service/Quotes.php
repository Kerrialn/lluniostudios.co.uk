<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DesignRequest;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\QuotePayment;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Preparing a request's quote in admin (Step 10b.1): a new draft starts
 * with each lamp design at its size's price (as snapshotted on the request)
 * and the delivery estimate; saving applies the form.
 */
final readonly class Quotes
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuoteDelivery $delivery,
    ) {
    }

    public function find(DesignRequest $request): ?Quote
    {
        return $this->entityManager->getRepository(Quote::class)->findOneBy([
            'designRequest' => $request,
        ]);
    }

    /**
     * The saved quote, or a new draft (not saved until the form is).
     */
    public function draftFor(DesignRequest $request): Quote
    {
        $quote = $this->find($request);
        if ($quote instanceof Quote) {
            return $quote;
        }

        $quote = new Quote($request);
        foreach ($request->getItems() as $item) {
            $quote->addLine(QuoteLine::forLamp($item, $item->getUnitPrice()));
        }

        $estimate = $this->delivery->estimate($request);
        $quote->setDelivery($estimate['price'] ?? 0, $estimate['label'], $estimate['estimated'] && $estimate['price'] !== null);

        return $quote;
    }

    /**
     * Save the form's values on the quote. The delivery stays marked as an
     * estimate only while it is the estimate's price and description.
     *
     * @param array{lamps: array<int, int>, extras: array<int, array{label: string, price: int}>, delivery: int, deliveryLabel: string, payment: QuotePayment, depositPercent: int, castingDate: ?\DateTimeImmutable, note: ?string} $data
     */
    public function save(Quote $quote, array $data): void
    {
        foreach ($quote->getLampLines() as $index => $line) {
            $line->setUnitPrice((int) ($data['lamps'][$index] ?? $line->getUnitPrice()));
        }

        foreach ($quote->getExtraLines() as $line) {
            $quote->removeLine($line);
        }

        foreach (array_values($data['extras']) as $extra) {
            $quote->addLine(QuoteLine::extra(trim($extra['label']), (int) $extra['price']));
        }

        $estimate = $this->delivery->estimate($quote->getDesignRequest());
        $label = trim($data['deliveryLabel']);
        $quote->setDelivery(
            (int) $data['delivery'],
            $label,
            $estimate['estimated'] && $estimate['price'] === (int) $data['delivery'] && $estimate['label'] === $label,
        );
        $quote->setPayment($data['payment']);
        $quote->setDepositPercent((int) $data['depositPercent']);
        $quote->setCastingDate($data['castingDate']);
        $quote->setNote(($note = trim((string) $data['note'])) === '' ? null : $note);

        $this->entityManager->persist($quote);
        $this->entityManager->flush();
    }
}
