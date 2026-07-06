<?php declare(strict_types=1);

namespace App\Exception;

use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Validator\ConstraintViolationListInterface;

final class ApiValidationException extends BadRequestHttpException {
    public function __construct(private readonly ConstraintViolationListInterface $violations,
        ?\Throwable $previous = null) {
        parent::__construct('Validation failed.', $previous);
    }

    public function getViolations(): ConstraintViolationListInterface {
        return $this->violations;
    }
}
