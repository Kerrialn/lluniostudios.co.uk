<?php

namespace App\Controller\Admin;

use App\Entity\ProductCollection;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\ImageField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextEditorField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Vich\UploaderBundle\Form\Type\VichImageType;

class ProductCollectionCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return ProductCollection::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('name'),
            TextField::new('slug')->setRequired(false)->hideOnIndex(),
            TextEditorField::new('description')->setRequired(false)->hideOnIndex(),
            Field::new('imageFile')
                ->setFormType(VichImageType::class)
                ->setRequired(false)
                ->onlyOnForms(),
            ImageField::new('imageFilename', 'Image')
                ->setBasePath('/uploads/images')
                ->onlyOnIndex(),
            AssociationField::new('products')
                ->setFormTypeOption('by_reference', false)
                ->autocomplete(),
        ];
    }
}
