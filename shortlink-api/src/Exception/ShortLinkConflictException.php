<?php declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

final class ShortLinkConflictException extends ConflictHttpException {
    public function __construct(?\Throwable $previous = null) {
        parent::__construct(
            'Concurrent short link creation conflict.',
            $previous
        );
    }
}
