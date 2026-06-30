<?php

namespace App\Entity;

use App\Enum\UserPreference\UserPreferenceKeyEnum;
use App\Repository\UserRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: UserRepository::class)]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_EMAIL', fields: ['email'])]
#[ORM\UniqueConstraint(name: 'UNIQ_IDENTIFIER_USERNAME', fields: ['username'])]
#[UniqueEntity(fields: ['email'], message: 'Cette adresse email est déjà utilisée.')]
#[UniqueEntity(fields: ['username'], message: 'Ce nom d\'utilisateur est déjà pris.')]
class User implements UserInterface, PasswordAuthenticatedUserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 180)]
    #[Assert\NotBlank(message: 'L\'adresse email ne peut pas être vide.')]
    #[Assert\Email(message: 'L\'adresse email n\'est pas valide.')]
    private ?string $email = null;

    /**
     * @var list<string> The user roles
     */
    #[ORM\Column]
    private array $roles = [];

    /**
     * @var string The hashed password
     */
    #[ORM\Column]
    private ?string $password = null;

    #[ORM\Column(length: 80)]
    #[Assert\NotBlank(message: 'Le nom d\'utilisateur ne peut pas être vide.')]
    private ?string $username = null;

    #[ORM\Column]
    private ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $avatar = null;

    #[ORM\Column(nullable: true)]
    private ?bool $is_verified = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $verified_at = null;

    /**
     * @var Collection<int, UserRequest>
     */
    #[ORM\OneToMany(targetEntity: UserRequest::class, mappedBy: 'user', cascade: ['persist', 'remove'], orphanRemoval: true)]
    private Collection $userRequests;

    #[ORM\Column(nullable: true)]
    private ?array $preferences = null;

    /**
     * @var Collection<int, Category>
     */
    #[ORM\OneToMany(targetEntity: Category::class, mappedBy: 'owner')]
    private Collection $categories;

    /**
     * @var Collection<int, EntryMedia>
     */
    #[ORM\OneToMany(targetEntity: EntryMedia::class, mappedBy: 'owner')]
    private Collection $entryMedia;

    /**
     * @var Collection<int, Entry>
     */
    #[ORM\OneToMany(targetEntity: Entry::class, mappedBy: 'owner')]
    private Collection $entries;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $private_secret = null;

    #[ORM\Column(length: 255)]
    private ?string $vault_token_session = null;

    public function __construct()
    {
        $this->userRequests = new ArrayCollection();
        $this->categories = new ArrayCollection();
        $this->entryMedia = new ArrayCollection();
        $this->entries = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    /**
     * A visual identifier that represents this user.
     *
     * @see UserInterface
     */
    public function getUserIdentifier(): string
    {
        return (string) $this->username ?? $this->email;
    }
    /**
     * @see UserInterface
     */
    public function getRoles(): array
    {
        $roles = $this->roles;

        return array_unique($roles);
    }

    /**
     * @param list<string> $roles
     */
    public function setRoles(array $roles): static
    {
        $this->roles = $roles;

        return $this;
    }

    /**
     * @see PasswordAuthenticatedUserInterface
     */
    public function getPassword(): ?string
    {
        return $this->password;
    }

    public function setPassword(string $password): static
    {
        $this->password = $password;

        return $this;
    }

    /**
     * Ensure the session doesn't contain actual password hashes by CRC32C-hashing them, as supported since Symfony 7.3.
     */
    public function __serialize(): array
    {
        $data = (array) $this;
        $data["\0" . self::class . "\0password"] = hash('crc32c', $this->password);

        return $data;
    }

    public function getUsername(): ?string
    {
        return $this->username;
    }

    public function setUsername(string $username): static
    {
        $this->username = $username;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->created_at;
    }

    public function setCreatedAt(\DateTimeImmutable $created_at): static
    {
        $this->created_at = $created_at;

        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updated_at;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updated_at): static
    {
        $this->updated_at = $updated_at;

        return $this;
    }

    public function getAvatar(): ?string
    {
        return $this->avatar;
    }

    public function setAvatar(?string $avatar): static
    {
        $this->avatar = $avatar;

        return $this;
    }

    public function isVerified(): ?bool
    {
        return $this->is_verified;
    }

    public function setIsVerified(?bool $is_verified): static
    {
        $this->is_verified = $is_verified;

        return $this;
    }

    public function getVerifiedAt(): ?\DateTimeImmutable
    {
        return $this->verified_at;
    }

    public function setVerifiedAt(?\DateTimeImmutable $verified_at): static
    {
        $this->verified_at = $verified_at;

        return $this;
    }

    /**
     * @return Collection<int, UserRequest>
     */
    public function getUserRequests(): Collection
    {
        return $this->userRequests;
    }

    public function addUserRequest(UserRequest $userRequest): static
    {
        if (!$this->userRequests->contains($userRequest)) {
            $this->userRequests->add($userRequest);
            $userRequest->setUser($this);
        }

        return $this;
    }

    public function removeUserRequest(UserRequest $userRequest): static
    {
        if ($this->userRequests->removeElement($userRequest)) {
            // set the owning side to null (unless already changed)
            if ($userRequest->getUser() === $this) {
                $userRequest->setUser(null);
            }
        }

        return $this;
    }

    /**
     * @param string $key
     * @param mixed|null $default
     * 
     * @return mixed
     */
    public function getPreference(string $key, mixed $default = null): mixed
    {
        if (UserPreferenceKeyEnum::tryFrom($key) === null) {
            return $default;
        }

        return $this->preferences[$key] ?? $default;
    }

    /**
     * @return array|null
     */
    public function getPreferences(): ?array
    {
        return $this->preferences;
    }

    /**
     * @param array|null $preferences
     * 
     * @return static
     */
    public function setPreferences(?array $preferences): static
    {
        $this->preferences = $preferences;

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
            $category->setOwner($this);
        }

        return $this;
    }

    public function removeCategory(Category $category): static
    {
        if ($this->categories->removeElement($category)) {
            // set the owning side to null (unless already changed)
            if ($category->getOwner() === $this) {
                $category->setOwner(null);
            }
        }

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
            $entryMedium->setOwner($this);
        }

        return $this;
    }

    public function removeEntryMedium(EntryMedia $entryMedium): static
    {
        if ($this->entryMedia->removeElement($entryMedium)) {
            // set the owning side to null (unless already changed)
            if ($entryMedium->getOwner() === $this) {
                $entryMedium->setOwner(null);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, Entry>
     */
    public function getEntries(): Collection
    {
        return $this->entries;
    }

    public function addEntry(Entry $entry): static
    {
        if (!$this->entries->contains($entry)) {
            $this->entries->add($entry);
            $entry->setOwner($this);
        }

        return $this;
    }

    public function removeEntry(Entry $entry): static
    {
        if ($this->entries->removeElement($entry)) {
            // set the owning side to null (unless already changed)
            if ($entry->getOwner() === $this) {
                $entry->setOwner(null);
            }
        }

        return $this;
    }

    public function getPrivateSecret(): ?string
    {
        return $this->private_secret;
    }

    public function setPrivateSecret(?string $private_secret): static
    {
        $this->private_secret = $private_secret;

        return $this;
    }

    public function getVaultTokenSession(): ?string
    {
        return $this->vault_token_session;
    }

    public function setVaultTokenSession(string $vault_token_session): static
    {
        $this->vault_token_session = $vault_token_session;

        return $this;
    }
}
