<?php

namespace App\Controller\Admin;

use App\Entity\CartItemOption;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;

class CartItemOptionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CartItemOption::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            AssociationField::new('cartItem'),
            AssociationField::new('productOption'),
            AssociationField::new('productOptionValue'),
        ];
    }
}
