<?php

namespace App\Entity;

use App\Enum\EntryColorEnum;
use App\Enum\MoodEnum;
use App\Repository\EntryRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: EntryRepository::class)]
class Entry
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $content = null;

    #[ORM\Column(length: 200, nullable: true)]
    private ?string $title = null;

    #[ORM\Column(length: 30, nullable: true, enumType: EntryColorEnum::class)]
    private EntryColorEnum|string|null $color = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\ManyToMany(targetEntity: Category::class, inversedBy: 'entries')]
    private Collection $categories;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $slug = null;

    /**
     * @var Collection<int, EntryMedia>
     */
    #[ORM\OneToMany(targetEntity: EntryMedia::class, mappedBy: 'entry')]
    private Collection $entryMedia;

    #[ORM\Column]
    private ?bool $is_private = null;

    #[ORM\Column(length: 50, nullable: true, enumType: MoodEnum::class)]
    private MoodEnum|string|null $mood = null;

    #[ORM\ManyToOne(inversedBy: 'entries')]
    private ?User $owner = null;

    public function __construct()
    {
        $this->categories = new ArrayCollection();
        $this->entryMedia = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): static
    {
        $this->content = $content;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): static
    {
        $this->title = $title;

        return $this;
    }

    /**
     * @return Collection<int, Category>
     */
    public function getCategories(): Collection
    {
        return $this->categories;
    }

    public function addCategory(Category $category): static
    {
        if (!$this->categories->contains($category)) {
            $this->categories->add($category);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        $this->categories->removeElement($category);

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): static
    {
        $this->slug = $slug;

        return $this;
    }

    /**
     * @return Collection<int, EntryMedia>
     */
    public function getEntryMedia(): Collection
    {
        return $this->entryMedia;
    }

    public function addEntryMedium(EntryMedia $entryMedium): static
    {
        if (!$this->entryMedia->contains($entryMedium)) {
            $this->entryMedia->add($entryMedium);
            $entryMedium->setEntry($this);
        }

        return $this;
    }

    public function removeEntryMedium(EntryMedia $entryMedium): static
    {
        if ($this->entryMedia->removeElement($entryMedium)) {
            // set the owning side to null (unless already changed)
            if ($entryMedium->getEntry() === $this) {
                $entryMedium->setEntry(null);
            }
        }

        return $this;
    }

    public function isPrivate(): ?bool
    {
        return $this->is_private;
    }

    public function setIsPrivate(bool $is_private): static
    {
        $this->is_private = $is_private;

        return $this;
    }


    public function getMood(): MoodEnum|string|null
    {
        return $this->mood;
    }

    public function setMood(MoodEnum|string|null $mood): static
    {
        if (is_string($mood)) {
            $mood = MoodEnum::tryFrom($mood);
        }

        $this->mood = $mood;

        return $this;
    }


    public function getColor(): ?EntryColorEnum
    {
        return $this->color;
    }

    public function setColor(EntryColorEnum|string|null $color): static
    {
        if (is_string($color)) {
            $color = EntryColorEnum::tryFrom($color);
        }

        $this->color = $color;

        return $this;
    }

    public function getOwner(): ?User
    {
        return $this->owner;
    }

    public function setOwner(?User $owner): static
    {
        $this->owner = $owner;

        return $this;
    }
}
