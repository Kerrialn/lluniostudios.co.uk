<?php

declare(strict_types=1);

namespace App\Command;

use App\Catalogue\LumenFormaCatalogue;
use App\Entity\Product;
use App\Entity\ProductCollection;
use App\Entity\ProductOption;
use App\Entity\ProductOptionValue;
use App\Repository\ProductCollectionRepository;
use App\Repository\ProductRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Creates the Lumen Forma collection, its five size products and their
 * configurator options (see LumenFormaCatalogue). Safe to run again: it only
 * adds what is missing and never changes or removes existing data, so edits
 * made in the admin are kept. Products are created unpublished.
 */
#[AsCommand(name: 'app:lumen-forma:setup', description: 'Create the Lumen Forma collection, sizes and options (only what is missing).')]
final class SetupLumenFormaCommand extends Command
{
    public function __construct(
        private readonly ProductRepository $productRepository,
        private readonly ProductCollectionRepository $productCollectionRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $rows = [];

        $collection = $this->productCollectionRepository->findOneBy([
            'slug' => LumenFormaCatalogue::COLLECTION_SLUG,
        ]);
        if (! $collection instanceof ProductCollection) {
            $collection = (new ProductCollection())
                ->setName(LumenFormaCatalogue::COLLECTION_NAME)
                ->setSlug(LumenFormaCatalogue::COLLECTION_SLUG)
                ->setDescription(LumenFormaCatalogue::DESCRIPTION);
            $this->entityManager->persist($collection);
            $io->writeln('Created collection "' . LumenFormaCatalogue::COLLECTION_NAME . '".');
        }

        foreach (LumenFormaCatalogue::SIZES as $size) {
            $product = $this->productRepository->findOneBy([
                'slug' => $size['slug'],
            ]);
            $created = false;
            if (! $product instanceof Product) {
                $product = (new Product())
                    ->setTitle($size['title'])
                    ->setDescription(LumenFormaCatalogue::DESCRIPTION)
                    ->setDirectOrder($size['direct'])
                    ->setIsPublished(false);
                $product->setSlug($size['slug']);
                $product->setPrice((string) $size['price']);
                $product->setLengthMm($size['lengthMm']);
                $product->setWidthMm($size['widthMm']);
                $product->setHeightMm($size['heightMm']);
                $product->setWeightGrams($size['weightGrams']);
                $this->entityManager->persist($product);
                $created = true;
            }

            $addedOptions = $this->addMissingOptions($product, $size['direct']);
            $collection->addProduct($product);

            $rows[] = [
                $size['title'],
                ($size['direct'] ? '' : 'from ') . '£' . number_format($size['price'] / 100),
                $size['direct'] ? 'Checkout' : 'Request',
                $created ? 'created' : 'already existed',
                $addedOptions === [] ? '-' : implode(', ', $addedOptions),
            ];
        }

        $this->entityManager->flush();

        $io->table(['Size', 'Price', 'Route', 'Product', 'Options added'], $rows);
        $io->note('Weights and dimensions are placeholders (lamp size, not packed box). Products are unpublished.');
        $io->success('Lumen Forma data is in place.');

        return Command::SUCCESS;
    }

    /**
     * @return list<string> names of the options that were added
     */
    private function addMissingOptions(Product $product, bool $directOrder): array
    {
        $existing = [];
        foreach ($product->getProductOptions() as $option) {
            $existing[$option->getName()] = $option;
        }

        $added = [];
        foreach (LumenFormaCatalogue::OPTIONS as $position => $definition) {
            if ($definition['requestSizesOnly'] && $directOrder) {
                continue;
            }

            if (isset($existing[$definition['name']])) {
                continue;
            }

            $option = new ProductOption();
            $option->setName($definition['name']);
            $option->setStage($definition['stage']);
            $option->setPosition($position);
            foreach ($definition['values'] as $valuePosition => $label) {
                $value = new ProductOptionValue();
                $value->setValue($label);
                $value->setPosition($valuePosition);
                $value->setGoesToRequest(LumenFormaCatalogue::goesToRequest($label));
                $option->addProductOptionValue($value);
            }

            $product->addProductOption($option);
            $added[] = $definition['name'];
        }

        return $added;
    }
}
