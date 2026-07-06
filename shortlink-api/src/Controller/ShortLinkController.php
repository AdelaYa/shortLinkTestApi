<?php declare(strict_types=1);

namespace App\Controller;

use App\Exception\ApiValidationException;
use App\Dto\ShortLinkRequestDto;
use App\Entity\ShortLink;
use App\Enum\ShortLinkStatus;
use App\Service\ShortLinkService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

final class ShortLinkController extends AbstractController {
    #[Route('/api/shortlink', name: 'api_shortlink', methods: ['GET'])]
    public function __invoke(Request $request,
        ValidatorInterface $validator,
        ShortLinkService $shortLinkService): JsonResponse {
        $url = $request->query->get('url');

        $dto = new ShortLinkRequestDto(
            is_string($url) ? $url : null
        );

        $violations = $validator->validate($dto);

        if (count($violations) > 0) {
            throw new ApiValidationException($violations);
        }

        $shortLink = $shortLinkService->getOrCreate($dto->url);

        if ($shortLink->getShortCode() !== null) {
            return $this->json([
                'success' => true,
                'data'    => [
                    'status'       => 'ready',
                    'original_url' => $shortLink->getOriginalUrl(),
                    'short_code'   => $shortLink->getShortCode(),
                    'short_url'    => $this->buildShortUrl($request, $shortLink),
                ],
            ], 200);
        }

        if ($shortLink->getStatus() === ShortLinkStatus::FAILED) {
            return $this->json([
                'success' => false,
                'data'    => [
                    'status'       => 'failed',
                    'original_url' => $shortLink->getOriginalUrl(),
                ],
            ], 500);
        }

        return $this->json([
            'success' => true,
            'data'    => [
                'status'       => 'generating',
                'original_url' => $shortLink->getOriginalUrl(),
            ],
        ], 202);
    }

    private function buildShortUrl(Request $request, ShortLink $shortLink): string {
        return $request->getSchemeAndHttpHost() . '/s/' . $shortLink->getShortCode();
    }
}
