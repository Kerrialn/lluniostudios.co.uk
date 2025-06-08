<?php

namespace App\Entity;

use App\Repository\UnregisteredUserRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: UnregisteredUserRepository::class)]
class UnregisteredUser extends Identity
{
    public function __toString(): string
    {
       return "unregistered: {$this->getIdentifier()}";
    }
}