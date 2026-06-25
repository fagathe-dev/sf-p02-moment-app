<?php

namespace App\Entity;

use App\Repository\EntryMediaRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryMediaRepository::class)]
class EntryMedia extends AbstractFile
{
    #[ORM\ManyToOne(inversedBy: 'entryMedia')]
    private ?User $owner = null;

    #[ORM\ManyToOne(inversedBy: 'entryMedia')]
    private ?Entry $entry = null;

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }

    public function getEntry(): ?Entry
    {
        return $this->entry;
    }

    public function setEntry(?Entry $entry): static
    {
        $this->entry = $entry;

        return $this;
    }
}
