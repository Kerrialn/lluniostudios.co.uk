<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\DesignRequest;
use App\Enum\DesignRequestStatus;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;

/**
 * "Reserve Your Design" requests, newest first, with their status (New,
 * Quoted, Paid). Requests come from the website only; the customer's details
 * and lamps are read-only here. The status can be changed by hand until the
 * quotes step (Step 10b) sets it.
 *
 * @extends AbstractCrudController<DesignRequest>
 */
class DesignRequestCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return DesignRequest::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Request')
            ->setEntityLabelInPlural('Requests')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (DesignRequest $request): string => 'Request ' . $request->getRequestNumber())
            ->setSearchFields(['requestNumber', 'fullName', 'email', 'phone', 'deliveryAddress'])
            ->setDefaultSort([
                'createdAt' => 'DESC',
            ]);
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters->add(ChoiceFilter::new('status')->setChoices(self::statusChoices()));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('requestNumber', 'Request')->setDisabled(),
            DateTimeField::new('createdAt', 'Received')->setFormat('d MMM y, HH:mm')->setTimezone('Europe/London')->setDisabled(),
            ChoiceField::new('status')
                ->setChoices(self::statusChoices())
                ->renderAsBadges([
                    DesignRequestStatus::NEW->value => 'warning',
                    DesignRequestStatus::QUOTED->value => 'info',
                    DesignRequestStatus::PAID->value => 'success',
                ]),
            TextField::new('fullName', 'Name')->setDisabled(),
            EmailField::new('email')->setDisabled(),
            TextField::new('phone')->setDisabled()->hideOnIndex(),
            IntegerField::new('lampCount', 'Lamps')->setDisabled()->onlyOnIndex(),
            TextareaField::new('deliveryAddress')->setDisabled()->hideOnIndex(),
            TextareaField::new('accessNotes', 'Site access & delivery notes')->setDisabled()->hideOnIndex(),
            TextareaField::new('customDetails')->setDisabled()->hideOnIndex(),
            Field::new('items', 'Lamps')
                ->setTemplatePath('admin/field/design_request_items.html.twig')
                ->onlyOnDetail(),
        ];
    }

    /**
     * @return array<string, DesignRequestStatus>
     */
    private static function statusChoices(): array
    {
        return array_combine(
            array_map(static fn (DesignRequestStatus $status): string => $status->label(), DesignRequestStatus::cases()),
            DesignRequestStatus::cases(),
        );
    }
}
