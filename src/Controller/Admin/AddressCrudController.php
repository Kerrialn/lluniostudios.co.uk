<?php

namespace App\Controller\Admin;

use App\Entity\Address;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\CountryField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AddressCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Address::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('recipientName'),
            TextField::new('line1'),
            TextField::new('line2')->hideOnIndex(),
            TextField::new('city'),
            TextField::new('county')->hideOnIndex(),
            TextField::new('postcode'),
            CountryField::new('country'),
            TextField::new('phone')->hideOnIndex(),
        ];
    }
}
