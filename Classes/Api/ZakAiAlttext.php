<?php

namespace Wegewerk\Ai3Alttext\Api;

use Psr\Http\Client\ClientExceptionInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\Service\ImageProcessingService;
use TYPO3\CMS\Extbase\Service\ImageService;
use Wegewerk\Ai3Core\Api\ZakAiClient;
use Wegewerk\Ai3Core\Api\ZakAiEndpointInterface;

/**
 * Implemets the API Endpoint related to a alt text generation
 */
class ZakAiAlttext implements ZakAiEndpointInterface
{
    public function __construct(
        private ZakAiClient $client,
        private LoggerInterface $logger,
        private readonly ImageService $imageService,
        private readonly ImageProcessingService $imageProcessingService
    ) {}

    public function generate(string $imagePath, string $caption, string $language): string
    {

        if (!file_exists($imagePath)) {
            throw new \InvalidArgumentException('Bilddatei nicht gefunden: ' . $imagePath);
        }

        try {
            $resizedPath = $this->getResizedImagePath($imagePath, 512);
            if ($resizedPath !== $imagePath) {
                $this->logger->debug(
                    'Bild wurde für Alttext-Erzeugung skaliert.',
                    [
                        'original' => $imagePath,
                        'resized'  => $resizedPath,
                    ]
                );
            }
        } catch (\RuntimeException $e) {
            $this->logger->error('generateAlttext Fehler: ' . $e->getMessage());
            throw new \InvalidArgumentException('generateAlttext Fehler: ' . $e->getMessage());
        }

        $imageData = base64_encode(file_get_contents($resizedPath));

            $response = $this->client->postJson(
                'alttexts',
                [
                    'image'    => $imageData,
                    'language' => $language ?? 'de',
                    'caption'  => $caption ?? '',
                ]
            );

            $this->logger->debug('Zak_ai API Antwort: ', $response);

            if ($response['status'] === 'OK') {
                return $response['alttext'];
            }

            $this->logger->error('Zak_ai API Fehler', [
                'result' => $response,
            ]);

            return '';

    }

    private function getResizedImagePath(string $imagePath, int $maxDimension = 512): string
    {
        $image = $this->imageService->getImage($imagePath, null, false);
        $processed = $this->imageService->applyProcessingInstructions(
            $image,
            [
                'maxWidth'  => $maxDimension,
                'maxHeight' => $maxDimension,
            ]
        );
        // wenn der scheduler über das Backend aufgerufen wurde, werden die Bilder
        // nicht sofort prozessiert, es wird nur eine processingURL erzeugt (DeferredBackendImageProcessor).
        // diesen Zustand versuchen wir nicht zu erkennen, wir rufen imageProcessingService->process einfach immer auf
        $processed = $this->imageProcessingService->process($processed->getUid());

        $localPath = $processed->getForLocalProcessing(false);
        if ($localPath !== '' && !file_exists($localPath)) {
            throw new \RuntimeException('local file not found: ' . $localPath, 1764593834847);
        }
        if ($localPath !== '' && file_exists($localPath)) {
            return $localPath;
        }
        throw new \RuntimeException('local file not found: ' . $imagePath, 1764593834846);
    }

}
