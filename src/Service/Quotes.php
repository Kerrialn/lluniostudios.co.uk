<?php

declare(strict_types=1);

namespace App\Service;

use App\Entity\DesignRequest;
use App\Entity\DesignRequestItem;
use App\Entity\Quote;
use App\Entity\QuoteLine;
use App\Enum\DesignRequestStatus;
use App\Enum\QuotePayment;
use Carbon\CarbonImmutable;
use Doctrine\ORM\EntityManagerInterface;

/**
 * Preparing a request's quote in admin (Step 10b.1): a new draft starts
 * with each lamp design at its size's price (as snapshotted on the request)
 * and the delivery estimate; saving applies the form. Sending (10b.2) emails
 * the customer the quote with its private link and marks the request Quoted;
 * nothing is sent until "Send quote" is pressed.
 */
final readonly class Quotes
{
    public function __construct(
        private EntityManagerInterface $entityManager,
        private QuoteDelivery $delivery,
        private EmailService $emails,
    ) {
    }

    public function findByToken(string $token): ?Quote
    {
        return $token === '' ? null : $this->entityManager->getRepository(Quote::class)->findOneBy([
            'token' => $token,
        ]);
    }

    /**
     * Why the saved quote can't be sent yet, or null.
     */
    public function sendingProblem(Quote $quote): ?string
    {
        return match (true) {
            $quote->getCastingDate() === null => 'Please add the estimated casting date before sending.',
            $quote->getTotal() <= 0 => 'The total must be more than £0 before sending.',
            default => null,
        };
    }

    /**
     * Email the quote as it is saved now (valid for 14 days from now), then
     * keep what was sent and mark the request Quoted. If the email can't be
     * sent, nothing is marked.
     */
    public function send(Quote $quote): void
    {
        $sentAt = new CarbonImmutable();
        $token = $quote->getToken() ?? rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
        $this->emails->sendQuoteEmail($quote, $this->view($quote, $quote->snapshot()), $token, Quote::expiryFor($sentAt));

        $quote->markSent($token, $sentAt);
        $quote->getDesignRequest()->setStatus(DesignRequestStatus::QUOTED);
        $this->entityManager->flush();
    }

    /**
     * A quote as shown to the customer (the email, the quote page): what was
     * sent (or the given copy), each lamp line with its design from the
     * request.
     *
     * @param array<string, mixed>|null $sent
     *
     * @return array{lamps: list<array{item: ?DesignRequestItem, label: string, quantity: int, unitPrice: int, total: int}>, extras: list<array{label: string, total: int}>, deliveryPrice: int, deliveryLabel: string, total: int, deposit: bool, depositPercent: int, dueNow: int, balance: int, castingDate: ?\DateTimeImmutable, note: ?string}
     */
    public function view(Quote $quote, ?array $sent = null): array
    {
        /** @var array{lines: list<array{label: string, quantity: int, unitPrice: int, total: int, requestItem: ?string}>, deliveryPrice: int, deliveryLabel: string, total: int, payment: string, depositPercent: int, dueNow: int, balance: int, castingDate: ?string, note: ?string} $sent */
        $sent = $sent ?? $quote->getSent() ?? $quote->snapshot();
        $items = [];
        foreach ($quote->getDesignRequest()->getItems() as $item) {
            $items[(string) $item->getId()?->toRfc4122()] = $item;
        }

        $lamps = [];
        $extras = [];
        foreach ($sent['lines'] as $line) {
            if ($line['requestItem'] !== null) {
                $lamps[] = [
                    'item' => $items[$line['requestItem']] ?? null,
                    'label' => $line['label'],
                    'quantity' => $line['quantity'],
                    'unitPrice' => $line['unitPrice'],
                    'total' => $line['total'],
                ];
            } else {
                $extras[] = [
                    'label' => $line['label'],
                    'total' => $line['total'],
                ];
            }
        }

        return [
            'lamps' => $lamps,
            'extras' => $extras,
            'deliveryPrice' => $sent['deliveryPrice'],
            'deliveryLabel' => $sent['deliveryLabel'],
            'total' => $sent['total'],
            'deposit' => $sent['payment'] === QuotePayment::DEPOSIT->value,
            'depositPercent' => $sent['depositPercent'],
            'dueNow' => $sent['dueNow'],
            'balance' => $sent['balance'],
            'castingDate' => $sent['castingDate'] === null ? null : new \DateTimeImmutable($sent['castingDate']),
            'note' => $sent['note'],
        ];
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
