<?php

namespace App\Controller\Admin;

use App\Entity\Order;
use App\Enum\OrderStatus;
use App\Enum\ShippingMethod;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\MoneyField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class OrderCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Order::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Order')
            ->setEntityLabelInPlural('Orders')
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnDetail(),
            TextField::new('orderNumber')->setDisabled(),
            TextField::new('email'),
            ChoiceField::new('status')
                ->setChoices(array_combine(
                    array_map(static fn (OrderStatus $s) => $s->label(), OrderStatus::cases()),
                    OrderStatus::cases(),
                )),
            MoneyField::new('subtotal')->setCurrency('GBP')->setStoredAsCents(true),
            MoneyField::new('shippingCost')->setCurrency('GBP')->setStoredAsCents(true),
            MoneyField::new('total')->setCurrency('GBP')->setStoredAsCents(true),
            ChoiceField::new('shippingMethod')
                ->setChoices(array_combine(
                    array_map(static fn (ShippingMethod $m) => $m->label(), ShippingMethod::cases()),
                    ShippingMethod::cases(),
                ))
                ->hideOnIndex(),
            TextField::new('shippingCarrier')->hideOnIndex(),
            TextField::new('shippingServiceName')->hideOnIndex(),
            AssociationField::new('shippingAddress')->hideOnIndex(),
            TextField::new('revolutOrderId')->hideOnIndex()->setDisabled(),
            TextField::new('revolutState')->hideOnIndex()->setDisabled(),
            DateTimeField::new('paidAt')->hideOnIndex()->setDisabled(),
            DateTimeField::new('createdAt')->setDisabled(),
            CollectionField::new('items')->onlyOnDetail(),
        ];
    }
}
