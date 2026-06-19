<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Fagathe\CorePhp\File\FileTypeEnum;

#[ORM\MappedSuperclass]
abstract class AbstractFile
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    protected ?int $id = null;

    #[ORM\Column(length: 255)]
    protected ?string $originalName = null;

    #[ORM\Column(length: 255, nullable: true)]
    protected ?string $niceName = null;

    #[ORM\Column(length: 255)]
    protected ?string $filePath = null;

    #[ORM\Column(length: 100)]
    protected ?string $mimeType = null;

    #[ORM\Column(length: 30, nullable: true, enumType: FileTypeEnum::class)]
    protected FileTypeEnum|string|null $type = null;

    #[ORM\Column(length: 10)]
    protected ?string $extension = null;

    #[ORM\Column]
    protected ?int $size = null;

    #[ORM\Column]
    protected ?\DateTimeImmutable $created_at = null;

    #[ORM\Column(nullable: true)]
    protected ?\DateTimeImmutable $updated_at = null;

    #[ORM\Column(options: ['default' => false])]
    protected bool $isPinned = false;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOriginalName(): ?string
    {
        return $this->originalName;
    }

    public function setOriginalName(string $originalName): static
    {
        $this->originalName = $originalName;

        return $this;
    }

    public function getNiceName(): ?string
    {
        return $this->niceName;
    }

    public function setNiceName(?string $niceName): static
    {
        $this->niceName = $niceName;

        return $this;
    }

    public function getFilePath(): ?string
    {
        return $this->filePath;
    }

    public function setFilePath(string $filePath): static
    {
        $this->filePath = $filePath;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(string $mimeType): static
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getExtension(): ?string
    {
        return $this->extension;
    }

    public function setExtension(string $extension): static
    {
        $this->extension = $extension;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(int $size): static
    {
        $this->size = $size;

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

    public function isPinned(): bool
    {
        return $this->isPinned;
    }

    public function setIsPinned(bool $isPinned): static
    {
        $this->isPinned = $isPinned;

        return $this;
    }

    public function getType(): ?FileTypeEnum
    {
        return $this->type;
    }

    public function setType(FileTypeEnum|string|null $type): static
    {
        if (is_string($type)) {
            $type = FileTypeEnum::tryFrom($type);
        }

        $this->type = $type;

        return $this;
    }
}
