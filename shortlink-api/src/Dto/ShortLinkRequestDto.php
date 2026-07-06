<?php declare(strict_types=1);

namespace App\Dto;

use Symfony\Component\Validator\Constraints as Assert;

final readonly class ShortLinkRequestDto {
    public function __construct(
        #[Assert\NotBlank(message: 'Parameter "url" is required')]
        #[Assert\Url(message: 'Parameter "url" must be a valid URL')]
        public ?string $url = null,
    ) {
    }
}
