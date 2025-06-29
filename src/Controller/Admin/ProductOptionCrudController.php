<?php

namespace App\Controller\Admin;

use App\Entity\ProductOption;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

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
            CollectionField::new('productOptionValues')
                ->useEntryCrudForm(ProductOptionValueCrudController::class)
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms(),
        ];
    }
}
