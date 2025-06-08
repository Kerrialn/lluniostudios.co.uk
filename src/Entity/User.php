<?php

namespace App\Entity;

use App\Repository\UserRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Uid\Uuid;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\Table(name: '`user`')]
class User extends Identity implements PasswordAuthenticatedUserInterface
{
    #[ORM\Column]
    private ?string $firstName = null;

    #[ORM\Column]
    private ?string $lastName = null;

    /**
     * @var string|null The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    public function __construct(?string $firstName, ?string $lastName)
    {
        Parent::__construct();
        $this->firstName = $firstName;
        $this->lastName = $lastName;
    }

    public function getId(): Uuid
    {
        return $this->id;
    }

    public function getFirstName(): ?string
    {
        return $this->firstName;
    }

    public function setFirstName(?string $firstName): void
    {
        $this->firstName = $firstName;
    }

    public function getLastName(): ?string
    {
        return $this->lastName;
    }

    public function setLastName(?string $lastName): void
    {
        $this->lastName = $lastName;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): string
    {
        return $this->password;
    }

    public function setPassword(string $password): self
    {
        $this->password = $password;

        return $this;
    }
}
