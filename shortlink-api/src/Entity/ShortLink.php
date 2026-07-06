<?php declare(strict_types=1);

namespace App\Entity;

use App\Repository\ShortLinkRepository;
use App\Enum\ShortLinkStatus;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: ShortLinkRepository::class)]
#[ORM\Table(name: 'short_link')]
class ShortLink {
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(type: 'text')]
    private string $originalUrl;

    #[ORM\Column(length: 64, unique: true)]
    private string $originalUrlHash;

    #[ORM\Column(length: 8, unique: true, nullable: true)]
    private ?string $shortCode = null;

    #[ORM\Column(length: 20)]
    private string $status;

    #[ORM\Column(options: ['default' => 0])]
    private int $generationAttempts = 0;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $createdAt;

    #[ORM\Column(type: Types::DATETIME_IMMUTABLE)]
    private \DateTimeImmutable $updatedAt;

    public function __construct(string $originalUrl, string $originalUrlHash) {
        $now = new \DateTimeImmutable();

        $this->originalUrl     = $originalUrl;
        $this->originalUrlHash = $originalUrlHash;
        $this->createdAt       = $now;
        $this->updatedAt       = $now;
        $this->status          = ShortLinkStatus::PENDING;
    }

    public function getId(): ?int {
        return $this->id;
    }

    public function getOriginalUrl(): ?string {
        return $this->originalUrl;
    }

    public function getOriginalUrlHash(): ?string {
        return $this->originalUrlHash;
    }

    public function getShortCode(): ?string {
        return $this->shortCode;
    }

    public function getUpdatedAt(): \DateTimeImmutable {
        return $this->updatedAt;
    }

    public function setShortCode(?string $shortCode): static {
        $this->shortCode = $shortCode;
        $this->touch();

        return $this;
    }

    public function getStatus(): ?string {
        return $this->status;
    }

    public function getGenerationAttempts(): int {
        return $this->generationAttempts;
    }

    public function setStatus(string $status): static {
        $this->status = $status;
        $this->touch();

        return $this;
    }

    public function incrementGenerationAttempts(): static {
        $this->generationAttempts++;
        $this->touch();

        return $this;
    }

    private function touch(): void {
        $this->updatedAt = new \DateTimeImmutable();
    }
}
