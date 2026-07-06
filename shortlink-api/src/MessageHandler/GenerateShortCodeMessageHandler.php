<?php declare(strict_types=1);

namespace App\MessageHandler;

use App\Entity\ShortLink;
use App\Enum\ShortLinkStatus;
use App\Message\GenerateShortCodeMessage;
use App\Repository\ShortLinkRepository;
use App\Service\ShortCodeGenerator;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
final readonly class GenerateShortCodeMessageHandler {
    public function __construct(
        private ShortCodeGenerator $shortCodeGenerator,
        private ManagerRegistry $managerRegistry,
    ) {
    }

    public function __invoke(GenerateShortCodeMessage $message): void {
        for ($attempt = 1; $attempt <= 5; $attempt++) {
            $shortLink = $this->reloadShortLinkById($message->getShortLinkId());

            if (!$shortLink instanceof ShortLink) {
                return;
            }

            if ($shortLink->getShortCode() !== null) {
                return;
            }

            $shortCode = $this->shortCodeGenerator->generate();

            $shortLink->setShortCode($shortCode);
            $shortLink->setStatus(ShortLinkStatus::READY);

            try {
                $this->managerRegistry->getManager()->flush();
                return;
            } catch (UniqueConstraintViolationException $e) {
                $this->managerRegistry->resetManager();

                if ($attempt === 5) {
                    $shortLink = $this->reloadShortLinkById($message->getShortLinkId());

                    if ($shortLink instanceof ShortLink) {
                        $shortLink->setStatus(ShortLinkStatus::FAILED);
                        $this->managerRegistry->getManager()->flush();
                    }

                    return;
                }

                continue;
            }
        }
    }

    private function reloadShortLinkById(int $shortLinkId): ?ShortLink {
        /** @var ShortLinkRepository $repository */
        $repository = $this->managerRegistry->getRepository(ShortLink::class);

        return $repository->find($shortLinkId);
    }
}
