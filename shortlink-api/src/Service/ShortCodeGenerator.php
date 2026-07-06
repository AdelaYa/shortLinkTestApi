<?php declare(strict_types=1);

namespace App\Service;

final class ShortCodeGenerator {
    private const CHARACTERS = 'abcdefghijklmnopqrstuvwxyzABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';

    public function generate(int $length = 6): string {
        $code = '';

        for ($i = 0; $i < $length; $i++) {
            $code .= self::CHARACTERS[random_int(0, strlen(self::CHARACTERS) - 1)];
        }

        return $code;
    }
}
