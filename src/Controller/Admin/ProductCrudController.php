<?php

namespace App\Controller\Admin;

use App\Entity\Product;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class ProductCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Product::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),
            TextField::new('title'),
            BooleanField::new('isPublished', 'Published'),
            MoneyField::new('price')->setCurrency('GBP')->setStoredAsCents(true),
            IntegerField::new('weightGrams', 'Weight (g)')->hideOnIndex(),
            IntegerField::new('lengthMm', 'Length (mm)')->hideOnIndex(),
            IntegerField::new('widthMm', 'Width (mm)')->hideOnIndex(),
            IntegerField::new('heightMm', 'Height (mm)')->hideOnIndex(),
            TextField::new('slug'),
            TextEditorField::new('description'),
            CollectionField::new('images')
                ->useEntryCrudForm(ImageCrudController::class)
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms(),
            CollectionField::new('productOptions')
                ->useEntryCrudForm(ProductOptionCrudController::class)
                ->setEntryIsComplex(true)
                ->setFormTypeOption('by_reference', false)
                ->onlyOnForms(),
        ];
    }
}
