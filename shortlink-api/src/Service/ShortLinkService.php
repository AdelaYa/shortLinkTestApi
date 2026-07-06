<?php declare(strict_types=1);

namespace App\Service;

use App\Exception\ShortLinkConflictException;
use App\Entity\ShortLink;
use App\Enum\ShortLinkStatus;
use App\Message\GenerateShortCodeMessage;
use App\Repository\ShortLinkRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\MessageBusInterface;

readonly class ShortLinkService {
    private const RETRY_AFTER_SECONDS     = 30;
    private const MAX_GENERATION_ATTEMPTS = 3;

    public function __construct(
        private ShortLinkRepository $shortLinkRepository,
        private EntityManagerInterface $entityManager,
        private MessageBusInterface $messageBus,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function getOrCreate(string $originalUrl): ShortLink {
        $normalizedUrl   = $this->normalizeUrl($originalUrl);
        $originalUrlHash = $this->makeHash($normalizedUrl);

        $shortLink = $this->shortLinkRepository->findOneByOriginalUrlHash($originalUrlHash);

        if ($shortLink instanceof ShortLink) {
            $this->retryGenerationIfNeeded($shortLink);

            return $shortLink;
        }

        $shortLink = new ShortLink($normalizedUrl, $originalUrlHash);
        $shortLink->incrementGenerationAttempts();

        $this->entityManager->persist($shortLink);

        try {
            $this->entityManager->flush();
        } catch (UniqueConstraintViolationException $e) {
            return $this->recoverAfterConflict($originalUrlHash, $e);
        }

        $this->messageBus->dispatch(
            new GenerateShortCodeMessage($shortLink->getId())
        );

        return $shortLink;
    }

    private function retryGenerationIfNeeded(ShortLink $shortLink): void {
        if ($shortLink->getShortCode() !== null) {
            return;
        }

        if (!$this->canRetryGeneration($shortLink)) {
            if ($shortLink->getStatus() === ShortLinkStatus::PENDING
                && $shortLink->getGenerationAttempts() >= self::MAX_GENERATION_ATTEMPTS) {
                $shortLink->setStatus(ShortLinkStatus::FAILED);
                $this->entityManager->flush();
            }

            return;
        }

        if ($shortLink->getStatus() === ShortLinkStatus::FAILED) {
            $shortLink->setStatus(ShortLinkStatus::PENDING);
        }

        $shortLink->incrementGenerationAttempts();

        $this->entityManager->flush();

        $this->messageBus->dispatch(
            new GenerateShortCodeMessage($shortLink->getId())
        );
    }

    private function canRetryGeneration(ShortLink $shortLink): bool {
        if ($shortLink->getGenerationAttempts() >= self::MAX_GENERATION_ATTEMPTS) {
            return false;
        }

        $status = $shortLink->getStatus();

        if ($status === ShortLinkStatus::FAILED) {
            return true;
        }

        if ($status === ShortLinkStatus::PENDING) {
            $retryAfter = new \DateTimeImmutable(sprintf('-%d seconds', self::RETRY_AFTER_SECONDS));

            return $shortLink->getUpdatedAt() <= $retryAfter;
        }

        return false;
    }

    private function recoverAfterConflict(string $originalUrlHash,
        UniqueConstraintViolationException $previous): ShortLink {
        $this->managerRegistry->resetManager();

        $shortLink = $this->reloadShortLinkByHash($originalUrlHash);

        if ($shortLink instanceof ShortLink) {
            return $shortLink;
        }

        throw new ShortLinkConflictException($previous);
    }

    private function reloadShortLinkByHash(string $originalUrlHash): ?ShortLink {
        /** @var ShortLinkRepository $repository */
        $repository = $this->managerRegistry->getRepository(ShortLink::class);

        return $repository->findOneByOriginalUrlHash($originalUrlHash);
    }

    private function normalizeUrl(string $url): string {
        return trim($url);
    }

    private function makeHash(string $url): string {
        return hash('sha256', $url);
    }
}
