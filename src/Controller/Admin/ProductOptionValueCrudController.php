<?php

namespace App\Controller\Admin;

use App\Entity\ProductOptionValue;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

/**
 * @extends AbstractCrudController<ProductOptionValue>
 */
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
            AssociationField::new('productOption', 'Option')->onlyOnIndex(),
            BooleanField::new('goesToRequest', 'Goes to Request')
                ->setHelp('Choosing this value sends the order to Request instead of checkout (e.g. "Custom" options).'),
            IntegerField::new('position', 'Order')->setHelp('Lowest first.'),
            MoneyField::new('priceDifference', 'Price difference')->setCurrency('GBP')->setStoredAsCents(true)
                ->setHelp('Not used yet: the price is set by size only.')
                ->hideOnIndex(),
            Field::new('imageFile', 'Photo')
                ->setFormType(VichImageType::class)
                ->setRequired(false)
                ->setHelp('Optional real photograph of this option. Never a generated or stand-in image.')
                ->onlyOnForms(),
            ImageField::new('imageFilename', 'Photo')
                ->setBasePath('/uploads/images')
                ->onlyOnIndex(),
        ];
    }
}
