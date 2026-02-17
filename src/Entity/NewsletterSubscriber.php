<?php

declare(strict_types=1);

namespace App\Entity;

use App\Enum\NewsletterSubscriberStatus;
use App\Repository\NewsletterSubscriberRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: NewsletterSubscriberRepository::class)]
#[ORM\Table(name: 'newsletter_subscribers')]
#[ORM\Index(columns: ['status'], name: 'idx_newsletter_status')]
#[ORM\Index(columns: ['confirmation_token'], name: 'idx_newsletter_confirmation_token')]
#[ORM\Index(columns: ['unsubscribe_token'], name: 'idx_newsletter_unsubscribe_token')]
#[ORM\HasLifecycleCallbacks]
class NewsletterSubscriber
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\CustomIdGenerator(class: UuidGenerator::class)]
    private Uuid $id;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Email]
    private string $email;

    #[ORM\Column(length: 32, enumType: NewsletterSubscriberStatus::class)]
    private NewsletterSubscriberStatus $status = NewsletterSubscriberStatus::Pending;

    /**
     * Token used for double opt-in confirmation.
     * Null once confirmed (optional, but keeps DB clean).
     */
    #[ORM\Column(length: 64, nullable: true)]
    private ?string $confirmationToken = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $confirmedAt = null;

    /**
     * Token for one-click unsubscribe link (no login needed).
     */
    #[ORM\Column(length: 64)]
    private string $unsubscribeToken;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $subscribedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $unsubscribedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?DateTimeImmutable $bouncedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $source = null; // e.g. footer_form, checkout, admin_import

    #[ORM\Column(length: 45, nullable: true)]
    private ?string $ipAddress = null; // supports IPv6

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $userAgent = null;

    #[ORM\Column(length: 10, nullable: true)]
    private ?string $locale = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private DateTimeImmutable $updatedAt;

    public function __construct()
    {
        $this->confirmationToken = $this->randomToken(32);
        $this->unsubscribeToken = $this->randomToken(32);
    }

    #[ORM\PrePersist]
    public function onPrePersist(): void
    {
        $now = new DateTimeImmutable();
        $this->createdAt = $now;
        $this->updatedAt = $now;
    }

    #[ORM\PreUpdate]
    public function onPreUpdate(): void
    {
        $this->updatedAt = new DateTimeImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getEmail(): string
    {
        return $this->email;
    }

    public function setEmail(string $email): void
    {
        $this->email = $email;
    }

    public function getStatus(): NewsletterSubscriberStatus
    {
        return $this->status;
    }

    public function getConfirmationToken(): ?string
    {
        return $this->confirmationToken;
    }

    public function getUnsubscribeToken(): string
    {
        return $this->unsubscribeToken;
    }

    public function confirm(): void
    {
        $now = new DateTimeImmutable();

        $this->status = NewsletterSubscriberStatus::Subscribed;
        $this->confirmedAt = $now;
        $this->subscribedAt ??= $now;

        // optional: remove token after confirmation
        $this->confirmationToken = null;
    }

    public function unsubscribe(): void
    {
        $this->status = NewsletterSubscriberStatus::Unsubscribed;
        $this->unsubscribedAt = new DateTimeImmutable();
    }

    public function markBounced(): void
    {
        $this->status = NewsletterSubscriberStatus::Bounced;
        $this->bouncedAt = new DateTimeImmutable();
    }

    public function markComplained(): void
    {
        $this->status = NewsletterSubscriberStatus::Complained;
    }

    public function setContext(
        ?string $source = null,
        ?string $ipAddress = null,
        ?string $userAgent = null,
        ?string $locale = null
    ): void
    {
        $this->source = $source;
        $this->ipAddress = $ipAddress;
        $this->userAgent = $userAgent;
        $this->locale = $locale;
    }

    private function randomToken(int $bytes): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public function getConfirmedAt(): ?DateTimeImmutable
    {
        return $this->confirmedAt;
    }

    public function setConfirmedAt(?DateTimeImmutable $confirmedAt): void
    {
        $this->confirmedAt = $confirmedAt;
    }

    public function getSubscribedAt(): ?DateTimeImmutable
    {
        return $this->subscribedAt;
    }

    public function setSubscribedAt(?DateTimeImmutable $subscribedAt): void
    {
        $this->subscribedAt = $subscribedAt;
    }

    public function getUnsubscribedAt(): ?DateTimeImmutable
    {
        return $this->unsubscribedAt;
    }

    public function setUnsubscribedAt(?DateTimeImmutable $unsubscribedAt): void
    {
        $this->unsubscribedAt = $unsubscribedAt;
    }

    public function getBouncedAt(): ?DateTimeImmutable
    {
        return $this->bouncedAt;
    }

    public function setBouncedAt(?DateTimeImmutable $bouncedAt): void
    {
        $this->bouncedAt = $bouncedAt;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(?string $source): void
    {
        $this->source = $source;
    }

    public function getIpAddress(): ?string
    {
        return $this->ipAddress;
    }

    public function setIpAddress(?string $ipAddress): void
    {
        $this->ipAddress = $ipAddress;
    }

    public function getUserAgent(): ?string
    {
        return $this->userAgent;
    }

    public function setUserAgent(?string $userAgent): void
    {
        $this->userAgent = $userAgent;
    }

    public function getLocale(): ?string
    {
        return $this->locale;
    }

    public function setLocale(?string $locale): void
    {
        $this->locale = $locale;
    }

    public function getCreatedAt(): DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(DateTimeImmutable $createdAt): void
    {
        $this->createdAt = $createdAt;
    }

    public function getUpdatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    public function setUpdatedAt(DateTimeImmutable $updatedAt): void
    {
        $this->updatedAt = $updatedAt;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): void
    {
        $this->name = $name;
    }
}

