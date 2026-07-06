<?php declare(strict_types=1);

namespace App\Tests\Service;

use App\Entity\ShortLink;
use App\Enum\ShortLinkStatus;
use App\Message\GenerateShortCodeMessage;
use App\Repository\ShortLinkRepository;
use App\Service\ShortLinkService;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Envelope;

class ShortLinkServiceTest extends TestCase {
    private ShortLinkRepository    $repository;
    private EntityManagerInterface $entityManager;
    private MessageBusInterface    $messageBus;
    private ManagerRegistry        $managerRegistry;
    private ShortLinkService       $service;

    protected function setUp(): void {
        $this->repository      = $this->createMock(ShortLinkRepository::class);
        $this->entityManager   = $this->createMock(EntityManagerInterface::class);
        $this->messageBus      = $this->createMock(MessageBusInterface::class);
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);

        $this->service = new ShortLinkService(
            $this->repository,
            $this->entityManager,
            $this->messageBus,
            $this->managerRegistry,
        );
    }

    public function testGetOrCreateReturnsExistingShortLink(): void {
        $url           = ' https://example.com/page ';
        $normalizedUrl = 'https://example.com/page';
        $hash          = hash('sha256', $normalizedUrl);

        $existing = new ShortLink($normalizedUrl, $hash);

        $this->repository->expects($this->once())
            ->method('findOneByOriginalUrlHash')
            ->with($hash)
            ->willReturn($existing);

        $this->entityManager->expects($this->never())->method('persist');
        $this->entityManager->expects($this->never())->method('flush');
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->service->getOrCreate($url);

        self::assertSame($existing, $result);
    }

    public function testGetOrCreateCreatesNewShortLink(): void {
        $url           = ' https://example.com/page ';
        $normalizedUrl = 'https://example.com/page';
        $hash          = hash('sha256', $normalizedUrl);

        $this->repository->expects($this->once())
            ->method('findOneByOriginalUrlHash')
            ->with($hash)
            ->willReturn(null);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ShortLink::class))
            ->willReturnCallback(function (ShortLink $shortLink): void {
                $this->setShortLinkId($shortLink, 123);
            });

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GenerateShortCodeMessage $message) {
                self::assertSame(123, $message->getShortLinkId());

                return true;
            }))
            ->willReturnCallback(function (GenerateShortCodeMessage $message): Envelope {
                return new Envelope($message);
            });

        $result = $this->service->getOrCreate($url);

        self::assertSame($normalizedUrl, $result->getOriginalUrl());
        self::assertSame($hash, $result->getOriginalUrlHash());
        self::assertSame('pending', $result->getStatus());
        self::assertNull($result->getShortCode());
    }

    public function testGetOrCreateRecoversAfterUniqueConstraintViolation(): void {
        $url           = ' https://example.com/page ';
        $normalizedUrl = 'https://example.com/page';
        $hash          = hash('sha256', $normalizedUrl);

        $existing = new ShortLink($normalizedUrl, $hash);

        $this->repository->expects($this->exactly(2))
            ->method('findOneByOriginalUrlHash')
            ->with($hash)
            ->willReturnOnConsecutiveCalls(null, $existing);

        $this->managerRegistry->expects($this->once())
            ->method('resetManager');

        $this->managerRegistry->expects($this->once())
            ->method('getRepository')
            ->with(ShortLink::class)
            ->willReturn($this->repository);

        $this->entityManager->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(ShortLink::class))
            ->willReturnCallback(function (ShortLink $shortLink): void {
                $this->setShortLinkId($shortLink, 123);
            });

        $violation = $this->createMock(UniqueConstraintViolationException::class);

        $this->entityManager->expects($this->once())
            ->method('flush')
            ->willThrowException($violation);

        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->service->getOrCreate($url);

        self::assertSame($existing, $result);
    }

    public function testGetOrCreateRetriesFailedShortLink(): void {
        $url           = ' https://example.com/page ';
        $normalizedUrl = 'https://example.com/page';
        $hash          = hash('sha256', $normalizedUrl);

        $failed = new ShortLink($normalizedUrl, $hash);
        $failed->setStatus(ShortLinkStatus::FAILED);

        $this->setShortLinkId($failed, 123);

        $this->repository->expects($this->once())
            ->method('findOneByOriginalUrlHash')
            ->with($hash)
            ->willReturn($failed);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->messageBus->expects($this->once())
            ->method('dispatch')
            ->with($this->callback(function (GenerateShortCodeMessage $message) {
                self::assertSame(123, $message->getShortLinkId());

                return true;
            }))
            ->willReturnCallback(function (GenerateShortCodeMessage $message): Envelope {
                return new Envelope($message);
            });

        $result = $this->service->getOrCreate($url);

        self::assertSame($failed, $result);
        self::assertSame(ShortLinkStatus::PENDING, $result->getStatus());
        self::assertSame(1, $result->getGenerationAttempts());
    }

    public function testGetOrCreateStopsRetryingWhenAttemptsAreExhausted(): void {
        $url           = ' https://example.com/page ';
        $normalizedUrl = 'https://example.com/page';
        $hash          = hash('sha256', $normalizedUrl);

        $failed = new ShortLink($normalizedUrl, $hash);
        $failed->setStatus(ShortLinkStatus::FAILED);
        $this->setShortLinkId($failed, 123);
        $this->setShortLinkGenerationAttempts($failed, 3);

        $this->repository->expects($this->once())
            ->method('findOneByOriginalUrlHash')
            ->with($hash)
            ->willReturn($failed);

        $this->entityManager->expects($this->never())->method('flush');
        $this->messageBus->expects($this->never())->method('dispatch');

        $result = $this->service->getOrCreate($url);

        self::assertSame($failed, $result);
        self::assertSame(ShortLinkStatus::FAILED, $result->getStatus());
        self::assertSame(3, $result->getGenerationAttempts());
    }

    private function setShortLinkId(ShortLink $shortLink, int $id): void {
        $ref = new \ReflectionProperty(ShortLink::class, 'id');
        $ref->setValue($shortLink, $id);
    }

    private function setShortLinkGenerationAttempts(ShortLink $shortLink, int $attempts): void {
        $ref = new \ReflectionProperty(ShortLink::class, 'generationAttempts');
        $ref->setValue($shortLink, $attempts);
    }
}
