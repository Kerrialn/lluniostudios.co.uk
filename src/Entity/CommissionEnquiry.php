<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\CommissionClient;
use App\Enum\CommissionStatus;
use App\Repository\CommissionEnquiryRepository;
use Carbon\CarbonImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * A Bespoke Commissions enquiry from the "For Your Home" or "For Your
 * Project" (trade) form, with any files the customer attached (kept private,
 * downloaded from admin). The studio follows it up by hand.
 */
#[ORM\Entity(repositoryClass: CommissionEnquiryRepository::class)]
#[ORM\Table(name: 'commission_enquiry')]
class CommissionEnquiry
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(length: 32, unique: true)]
    private string $enquiryNumber;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: CommissionClient::class)]
    private CommissionClient $client;

    #[ORM\Column(type: Types::STRING, length: 16, enumType: CommissionStatus::class)]
    private CommissionStatus $status = CommissionStatus::NEW;

    /**
     * The customer's name (Home) or the contact's name (Project).
     */
    #[ORM\Column(length: 255)]
    private string $name;

    #[ORM\Column(length: 255)]
    private string $email;

    #[ORM\Column(length: 32, nullable: true)]
    private ?string $phone = null;

    /**
     * Home only: "email" or "phone".
     */
    #[ORM\Column(length: 16, nullable: true)]
    private ?string $contactMethod = null;

    /**
     * Home only: "Tell us about your idea".
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $idea = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $company = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $projectType = null;

    /**
     * Project only: "Project Location & Installation Schedule".
     */
    #[ORM\Column(length: 255, nullable: true)]
    private ?string $location = null;

    /**
     * Project only: "The Project".
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $project = null;

    /**
     * Project only: "Integration Requirements".
     */
    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $integration = null;

    #[ORM\Column(type: 'datetime_immutable')]
    private \DateTimeImmutable $createdAt;

    /**
     * @var Collection<int, CommissionEnquiryFile>
     */
    #[ORM\OneToMany(targetEntity: CommissionEnquiryFile::class, mappedBy: 'enquiry', cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy([
        'position' => 'ASC',
    ])]
    private Collection $files;

    private function __construct(CommissionClient $client, string $name, string $email, ?string $phone)
    {
        $this->client = $client;
        $this->name = $name;
        $this->email = $email;
        $this->phone = $phone;
        $this->createdAt = new CarbonImmutable();
        $this->files = new ArrayCollection();
        $this->enquiryNumber = 'LC-' . (new CarbonImmutable())->format('Ymd') . '-' . strtoupper(substr(bin2hex(random_bytes(3)), 0, 6));
    }

    public static function forHome(string $name, string $email, ?string $phone, string $contactMethod, string $idea): self
    {
        $enquiry = new self(CommissionClient::HOME, $name, $email, $phone);
        $enquiry->contactMethod = $contactMethod;
        $enquiry->idea = $idea;

        return $enquiry;
    }

    public static function forProject(string $company, string $contactName, string $email, ?string $phone, string $projectType, string $location, ?string $project, ?string $integration): self
    {
        $enquiry = new self(CommissionClient::PROJECT, $contactName, $email, $phone);
        $enquiry->company = $company;
        $enquiry->projectType = $projectType;
        $enquiry->location = $location;
        $enquiry->project = $project;
        $enquiry->integration = $integration;

        return $enquiry;
    }

    public function getId(): ?Uuid
    {
        return $this->id ?? null;
    }

    public function getEnquiryNumber(): string
    {
        return $this->enquiryNumber;
    }

    public function getClient(): CommissionClient
    {
        return $this->client;
    }

    public function getStatus(): CommissionStatus
    {
        return $this->status;
    }

    public function setStatus(CommissionStatus $status): void
    {
        $this->status = $status;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function getPhone(): ?string
    {
        return $this->phone;
    }

    public function getContactMethod(): ?string
    {
        return $this->contactMethod;
    }

    /**
     * "Email" or "Phone", as the customer chose (Home only).
     */
    public function getContactMethodLabel(): ?string
    {
        return $this->contactMethod === null ? null : ucfirst($this->contactMethod);
    }

    public function getIdea(): ?string
    {
        return $this->idea;
    }

    public function getCompany(): ?string
    {
        return $this->company;
    }

    public function getProjectType(): ?string
    {
        return $this->projectType;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function getProject(): ?string
    {
        return $this->project;
    }

    public function getIntegration(): ?string
    {
        return $this->integration;
    }

    public function getCreatedAt(): \DateTimeImmutable
    {
        return $this->createdAt;
    }

    /**
     * @return Collection<int, CommissionEnquiryFile>
     */
    public function getFiles(): Collection
    {
        return $this->files;
    }

    public function addFile(CommissionEnquiryFile $file): void
    {
        $file->setEnquiry($this);
        $file->setPosition($this->files->count());
        $this->files->add($file);
    }

    public function getFileCount(): int
    {
        return $this->files->count();
    }

    public function __toString(): string
    {
        return $this->enquiryNumber;
    }
}
