<?php

namespace App\Entity;

use App\Repository\LoginCodeRepository;
use Carbon\CarbonImmutable;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\IdGenerator\UuidGenerator;
use Symfony\Component\Uid\Uuid;

/**
 * A one-time 6-digit code emailed to a user to authenticate them (passwordless).
 * Codes are stored hashed, expire quickly and allow a limited number of attempts.
 */
#[ORM\Entity(repositoryClass: LoginCodeRepository::class)]
#[ORM\Table(name: 'login_code')]
class LoginCode
{
    #[ORM\Id]
    #[ORM\Column(type: 'uuid', unique: true)]
    #[ORM\GeneratedValue(strategy: 'CUSTOM')]
    #[ORM\CustomIdGenerator(UuidGenerator::class)]
    private Uuid $id;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(name: 'user_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $codeHash;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private CarbonImmutable $expiresAt;

    #[ORM\Column(type: Types::INTEGER)]
    private int $attempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?CarbonImmutable $consumedAt = null;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private CarbonImmutable $createdAt;

    public function __construct(User $user, string $codeHash, CarbonImmutable $expiresAt)
    {
        $this->user = $user;
        $this->codeHash = $codeHash;
        $this->expiresAt = $expiresAt;
        $this->createdAt = new CarbonImmutable();
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getCodeHash(): string
    {
        return $this->codeHash;
    }

    public function getExpiresAt(): CarbonImmutable
    {
        return $this->expiresAt;
    }

    public function isExpired(): bool
    {
        return $this->expiresAt->isPast();
    }

    public function getAttempts(): int
    {
        return $this->attempts;
    }

    public function incrementAttempts(): void
    {
        ++$this->attempts;
    }

    public function isConsumed(): bool
    {
        return $this->consumedAt !== null;
    }

    public function consume(): void
    {
        $this->consumedAt = new CarbonImmutable();
    }

    public function getCreatedAt(): CarbonImmutable
    {
        return $this->createdAt;
    }
}
