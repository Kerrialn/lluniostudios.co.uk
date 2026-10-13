<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\ProductOption;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * @extends AbstractCrudController<ProductOption>
 */
class ProductOptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductOption::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('name'),
            AssociationField::new('product')->onlyOnIndex(),
            ChoiceField::new('stage', 'Configurator stage')->setChoices(ProductOption::STAGES),
            IntegerField::new('position', 'Order')->setHelp('Lowest first.'),
            CollectionField::new('productOptionValues')
                ->useEntryCrudForm(ProductOptionValueCrudController::class)
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms(),
        ];
    }
}
