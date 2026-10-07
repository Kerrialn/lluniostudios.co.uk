<?php

declare(strict_types=1);

namespace App\Controller\Admin;

use App\Entity\CommissionEnquiry;
use App\Enum\CommissionClient;
use App\Enum\CommissionStatus;
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
 * Bespoke Commissions enquiries, newest first, with their status (New,
 * Contacted, Closed) and type (Home or Project). Enquiries come from the
 * website only; the customer's details are read-only here and only the
 * status can be changed. Attached files are downloaded from the enquiry's
 * page (CommissionFileController). Deleting an enquiry deletes its files.
 *
 * @extends AbstractCrudController<CommissionEnquiry>
 */
class CommissionEnquiryCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return CommissionEnquiry::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commission')
            ->setEntityLabelInPlural('Commissions')
            ->setPageTitle(Crud::PAGE_DETAIL, static fn (CommissionEnquiry $enquiry): string => 'Commission ' . $enquiry->getEnquiryNumber())
            ->setSearchFields(['enquiryNumber', 'name', 'company', 'email', 'phone', 'location'])
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
        return $filters
            ->add(ChoiceFilter::new('status')->setChoices(self::statusChoices()))
            ->add(ChoiceFilter::new('client', 'Type')->setChoices(self::clientChoices()));
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            TextField::new('enquiryNumber', 'Enquiry')->setDisabled(),
            DateTimeField::new('createdAt', 'Received')->setFormat('d MMM y, HH:mm')->setTimezone('Europe/London')->setDisabled(),
            ChoiceField::new('status')
                ->setChoices(self::statusChoices())
                ->renderAsBadges([
                    CommissionStatus::NEW->value => 'warning',
                    CommissionStatus::CONTACTED->value => 'info',
                    CommissionStatus::CLOSED->value => 'success',
                ]),
            ChoiceField::new('client', 'Type')->setChoices(self::clientChoices())->setDisabled(),
            TextField::new('name', 'Name')->setDisabled(),
            TextField::new('company')->setDisabled(),
            EmailField::new('email')->setDisabled(),
            TextField::new('phone')->setDisabled()->hideOnIndex(),
            TextField::new('contactMethodLabel', 'Preferred contact method')->setDisabled()->onlyOnDetail(),
            TextareaField::new('idea', 'Their idea')->setDisabled()->onlyOnDetail(),
            TextField::new('projectType')->setDisabled()->onlyOnDetail(),
            TextField::new('location', 'Location & installation schedule')->setDisabled()->onlyOnDetail(),
            TextareaField::new('project', 'The project')->setDisabled()->onlyOnDetail(),
            TextareaField::new('integration', 'Integration requirements')->setDisabled()->onlyOnDetail(),
            IntegerField::new('fileCount', 'Files')->setDisabled()->onlyOnIndex(),
            Field::new('files', 'Files')
                ->setTemplatePath('admin/field/commission_files.html.twig')
                ->onlyOnDetail(),
        ];
    }

    /**
     * @return array<string, CommissionStatus>
     */
    private static function statusChoices(): array
    {
        return array_combine(
            array_map(static fn (CommissionStatus $status): string => $status->label(), CommissionStatus::cases()),
            CommissionStatus::cases(),
        );
    }

    /**
     * @return array<string, CommissionClient>
     */
    private static function clientChoices(): array
    {
        return [
            'Home' => CommissionClient::HOME,
            'Project' => CommissionClient::PROJECT,
        ];
    }
}
