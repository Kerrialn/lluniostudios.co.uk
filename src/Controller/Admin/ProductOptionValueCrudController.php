<?php

namespace App\Controller\Admin;

use App\Entity\ProductOptionValue;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductOptionValueCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductOptionValue::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('value'),
        ];
    }
}
