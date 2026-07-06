<?php declare(strict_types=1);

namespace App\EventSubscriber;

use App\Exception\ApiValidationException;
use App\Exception\ShortLinkConflictException;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Symfony\Component\HttpKernel\KernelEvents;

final class ApiExceptionSubscriber implements EventSubscriberInterface {
    public static function getSubscribedEvents(): array {
        return [
            KernelEvents::EXCEPTION => 'onKernelException',
        ];
    }

    public function onKernelException(ExceptionEvent $event): void {
        $throwable = $event->getThrowable();

        if ($throwable instanceof ShortLinkConflictException) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'data'    => [
                    'status'  => 'conflict',
                    'message' => 'Ссылка уже создается или не может быть получена прямо сейчас. Повторите запрос позже.',
                ],
            ], Response::HTTP_CONFLICT));

            return;
        }

        if ($throwable instanceof ApiValidationException) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'data'    => [
                    'status' => 'validation_error',
                    'errors' => $this->formatViolations($throwable->getViolations()),
                ],
            ], Response::HTTP_BAD_REQUEST));

            return;
        }

        if ($throwable instanceof HttpExceptionInterface) {
            $event->setResponse(new JsonResponse([
                'success' => false,
                'data'    => [
                    'status'  => 'error',
                    'message' => $throwable->getMessage(),
                ],
            ], $throwable->getStatusCode(), $throwable->getHeaders()));

            return;
        }

        $event->setResponse(new JsonResponse([
            'success' => false,
            'data'    => [
                'status'  => 'error',
                'message' => 'Internal server error.',
            ],
        ], Response::HTTP_INTERNAL_SERVER_ERROR));
    }

    private function formatViolations(iterable $violations): array {
        $errors = [];

        foreach ($violations as $violation) {
            $errors[] = [
                'field'   => $violation->getPropertyPath(),
                'message' => $violation->getMessage(),
            ];
        }

        return $errors;
    }
}
