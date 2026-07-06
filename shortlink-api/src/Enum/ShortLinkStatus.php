<?php declare(strict_types=1);

namespace App\Enum;

final class ShortLinkStatus {
    public const PENDING = 'pending';
    public const READY   = 'ready';
    public const FAILED  = 'failed';
}
