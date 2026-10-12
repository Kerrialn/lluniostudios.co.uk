<?php

declare(strict_types=1);

namespace App\Service;

use App\Catalogue\LumenFormaCatalogue;
use App\Catalogue\LumenFormaConfiguration;
use App\Catalogue\LumenFormaConfigurator;
use App\Entity\Cart;
use App\Entity\CartItem;
use App\Entity\DesignRequestItem;
use App\Entity\Product;
use Symfony\Component\HttpFoundation\RequestStack;

/**
 * The lamps a "Reserve Your Design" request is made of: every line in the
 * basket, plus the lamp being configured when the customer pressed "Reserve
 * Your Design" in the configurator (kept in the session until the request is
 * sent). When that lamp was an edit of a basket line, it replaces the line.
 * Each becomes a snapshot (DesignRequestItem), as an order line would.
 */
final readonly class DesignRequestLamps
{
    private const SESSION_KEY = 'reserve_pending';

    public function __construct(
        private RequestStack $requestStack,
        private LumenFormaConfigurator $configurator,
    ) {
    }

    /**
     * Remember the lamp being configured (a design as sent by the
     * configurator, see LumenFormaConfigurator::read()) for the request form.
     *
     * @param array<string, mixed> $design
     */
    public function holdPending(array $design, ?string $replacesLine = null): void
    {
        $this->requestStack->getSession()->set(self::SESSION_KEY, [
            'design' => $design,
            'replaces' => $replacesLine,
        ]);
    }

    /**
     * Whether the request carries a new lamp from the configurator (not an
     * edit of a basket line): its remembered choices are then forgotten once
     * the request is sent.
     */
    public function hasNewPending(): bool
    {
        $pending = $this->requestStack->getSession()->get(self::SESSION_KEY);

        return \is_array($pending) && ($pending['replaces'] ?? null) === null;
    }

    /**
     * Whether the lamp being reserved came from the larger-piece step (7b.4):
     * then that step's remembered choices are forgotten once the request is
     * sent, rather than the configurator's.
     */
    public function pendingIsLarger(): bool
    {
        $pending = $this->requestStack->getSession()->get(self::SESSION_KEY);

        return \is_array($pending) && $this->configurator->readLarger($pending['design'] ?? null) instanceof LumenFormaConfiguration;
    }

    public function forgetPending(): void
    {
        $this->requestStack->getSession()->remove(self::SESSION_KEY);
    }

    /**
     * The request's lamps, in order: the basket's lines, then the lamp being
     * configured (in place of the line it edits, if any).
     *
     * @return list<DesignRequestItem>
     */
    public function items(?Cart $cart): array
    {
        $pending = $this->requestStack->getSession()->get(self::SESSION_KEY);
        $design = \is_array($pending) ? ($pending['design'] ?? null) : null;
        $configuration = $this->configurator->read($design) ?? $this->configurator->readLarger($design);
        $replaces = \is_array($pending) && \is_string($pending['replaces'] ?? null) ? $pending['replaces'] : null;

        $items = [];
        foreach ($cart?->getCartItems() ?? [] as $line) {
            if (null !== $replaces && $configuration instanceof LumenFormaConfiguration && (string) $line->getId() === $replaces) {
                $items[] = $this->fromConfiguration($configuration);
                $configuration = null;
                continue;
            }

            if ($line->getProduct() instanceof Product) {
                $items[] = $this->fromCartItem($line);
            }
        }

        if ($configuration instanceof LumenFormaConfiguration) {
            $items[] = $this->fromConfiguration($configuration);
        }

        return $items;
    }

    /**
     * What the customer wrote under the configured lamp's Custom choices
     * (10 October 2026), as the start of Reserve Your Design's "Custom
     * Details": one line per description, "The Pigment: …" (with "Lamp 2 · "
     * first when customised individually), see
     * LumenFormaConfigurator::customDescriptions(). Empty when there are none.
     */
    public function pendingCustomDetails(): string
    {
        $pending = $this->requestStack->getSession()->get(self::SESSION_KEY);
        $design = \is_array($pending) ? ($pending['design'] ?? null) : null;
        $configuration = $this->configurator->read($design);
        if (! $configuration instanceof LumenFormaConfiguration) {
            return '';
        }

        $lines = [];
        foreach (LumenFormaConfigurator::customDescriptions($configuration, $design) as $description) {
            if ('' !== $description['text']) {
                $lines[] = ($configuration->individually ? 'Lamp ' . ($description['lamp'] + 1) . ' · ' : '') . $description['option'] . ': ' . $description['text'];
            }
        }

        return implode("\n", $lines);
    }

    /**
     * Whether any lamp has any Custom choice (pigment, cable, footing or
     * bulb): the form then asks for "Custom Details".
     *
     * @param list<DesignRequestItem> $items
     */
    public static function hasCustomChoice(array $items): bool
    {
        foreach ($items as $item) {
            foreach ($item->getOptionsSnapshot() as $entry) {
                if (LumenFormaCatalogue::goesToRequest($entry['value'])) {
                    return true;
                }
            }
        }

        return false;
    }

    public function fromCartItem(CartItem $line): DesignRequestItem
    {
        /** @var Product $product */
        $product = $line->getProduct();
        $options = [];
        foreach ($line->getLampConfigurations() as $index => $group) {
            foreach ($group['options'] as $option) {
                $options[] = [
                    'option' => $option->getProductOption()->getName(),
                    'value' => $option->getProductOptionValue()->getValue(),
                    'lamp' => $line->isCustomisedIndividually() ? $index + 1 : 0,
                ];
            }
        }

        return new DesignRequestItem(
            product: $product,
            productTitle: (string) $product->getTitle(),
            sizeName: LumenFormaConfigurator::sizeName($product),
            unitPrice: (int) $product->getPrice(),
            quantity: $line->getQuantity() ?? 1,
            customisedIndividually: $line->isCustomisedIndividually(),
            optionsSnapshot: $options,
        );
    }

    public function fromConfiguration(LumenFormaConfiguration $configuration): DesignRequestItem
    {
        $options = [];
        foreach ($configuration->lamps as $index => $choices) {
            foreach ($choices as [$option, $value]) {
                $options[] = [
                    'option' => $option->getName(),
                    'value' => $value->getValue(),
                    'lamp' => $configuration->individually ? $index + 1 : 0,
                ];
            }
        }

        return new DesignRequestItem(
            product: $configuration->product,
            productTitle: (string) $configuration->product->getTitle(),
            sizeName: LumenFormaConfigurator::sizeName($configuration->product),
            unitPrice: (int) $configuration->product->getPrice(),
            quantity: $configuration->quantity,
            customisedIndividually: $configuration->individually,
            optionsSnapshot: $options,
        );
    }
}
