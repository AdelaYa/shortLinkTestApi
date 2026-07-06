<?php declare(strict_types=1);

namespace App\Message;

final readonly class GenerateShortCodeMessage {
    public function __construct(
        private int $shortLinkId,
    ) {
    }

    public function getShortLinkId(): int {
        return $this->shortLinkId;
    }
}
