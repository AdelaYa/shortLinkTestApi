<?php declare(strict_types=1);

namespace App\Tests\MessageHandler;

use App\Entity\ShortLink;
use App\Enum\ShortLinkStatus;
use App\Message\GenerateShortCodeMessage;
use App\MessageHandler\GenerateShortCodeMessageHandler;
use App\Repository\ShortLinkRepository;
use App\Service\ShortCodeGenerator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\TestCase;

class GenerateShortCodeMessageHandlerTest extends TestCase {
    private ShortLinkRepository             $repository;
    private EntityManagerInterface          $entityManager;
    private ManagerRegistry                 $managerRegistry;
    private GenerateShortCodeMessageHandler $handler;

    protected function setUp(): void {
        $this->repository      = $this->createMock(ShortLinkRepository::class);
        $this->entityManager   = $this->createMock(EntityManagerInterface::class);
        $this->managerRegistry = $this->createMock(ManagerRegistry::class);

        $shortCodeGenerator = new ShortCodeGenerator();
        $this->handler      = new GenerateShortCodeMessageHandler(
            $shortCodeGenerator,
            $this->managerRegistry,
        );
    }

    public function testInvokeGeneratesShortCodeAndMarksReady(): void {
        $shortLink = new ShortLink('https://example.com/page', 'hash');

        $ref = new \ReflectionProperty(ShortLink::class, 'id');
        $ref->setValue($shortLink, 123);

        $this->managerRegistry->expects($this->once())
            ->method('getRepository')
            ->with(ShortLink::class)
            ->willReturn($this->repository);

        $this->repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($shortLink);

        $this->managerRegistry->expects($this->once())
            ->method('getManager')
            ->willReturn($this->entityManager);

        $this->entityManager->expects($this->once())
            ->method('flush');

        $this->handler->__invoke(new GenerateShortCodeMessage(123));

        self::assertSame(ShortLinkStatus::READY, $shortLink->getStatus());
        self::assertNotNull($shortLink->getShortCode());
        self::assertSame(6, strlen($shortLink->getShortCode()));
    }

    public function testInvokeDoesNothingWhenShortCodeAlreadyExists(): void {
        $shortLink = new ShortLink('https://example.com/page', 'hash');
        $shortLink->setShortCode('abc123');

        $ref = new \ReflectionProperty(ShortLink::class, 'id');
        $ref->setValue($shortLink, 123);

        $this->managerRegistry->expects($this->once())
            ->method('getRepository')
            ->with(ShortLink::class)
            ->willReturn($this->repository);

        $this->repository->expects($this->once())
            ->method('find')
            ->with(123)
            ->willReturn($shortLink);

        $this->managerRegistry->expects($this->never())
            ->method('getManager');

        $this->handler->__invoke(new GenerateShortCodeMessage(123));

        self::assertSame(ShortLinkStatus::PENDING, $shortLink->getStatus());
        self::assertSame('abc123', $shortLink->getShortCode());
    }
}
