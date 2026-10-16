<?php

namespace Wegewerk\Ai3Alttext\Api;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Core\Resource\Service\ImageProcessingService;
use TYPO3\CMS\Extbase\Service\ImageService;
use Wegewerk\Ai3Core\Api\AbstractZakAiEndpoint;
use Wegewerk\Ai3Core\Api\ZakAiClient;

/**
 * Implements the API Endpoint related to alt text generation
 */
class ZakAiAlttext extends AbstractZakAiEndpoint
{
    protected string $endpoint = 'alttexts';
    protected string $responseKey = 'alttext';
    protected array $requiredOptions = [ 'image' ];
    protected array $optionalOptions = [ 'caption', 'language', 'previous' ];

    public function __construct(
        ZakAiClient                             $client,
        private readonly ImageService           $imageService,
        private readonly ImageProcessingService $imageProcessingService,
        ?LoggerInterface                        $logger = null
    ) {
        parent::__construct($client, $logger);
    }

    protected function preparePayload(array $payload, array $options): array {
        $imagePath = (string)($options['imagePath'] ?? '');
        if (!file_exists($imagePath)) {
            throw new \InvalidArgumentException('Bilddatei nicht gefunden: ' . $imagePath);
        }

        try {
            $resizedPath = $this->getResizedImagePath($imagePath, 512);
            if ($resizedPath !== $imagePath) {
                $this->logger?->debug('Bild wurde für Alttext-Erzeugung skaliert.',
                    [
                        'original' => $imagePath,
                        'resized'  => $resizedPath,
                    ]);
            }
        } catch (\RuntimeException $e) {
            $this->logger?->error('generateAlttext Fehler: ' . $e->getMessage());
            throw new \InvalidArgumentException('generateAlttext Fehler: ' . $e->getMessage());
        }

        unset($payload['imagePath']);
        $payload['image'] = base64_encode(file_get_contents($resizedPath));

        return $payload;
    }

    protected function extractResult(array $response): string {
        if (($response['status'] ?? '') === 'OK' && isset($response[$this->responseKey])) {
            return (string)$response[$this->responseKey];
        }

        if (isset($response[$this->responseKey])) {
            return (string)$response[$this->responseKey];
        }

        $this->logger?->error('Zak_ai API Fehler',
            [
                'result' => $response,
            ]);

        return '';
    }

    private function getResizedImagePath(string $imagePath, int $maxDimension = 512): string {
        $image = $this->imageService->getImage($imagePath, null, false);
        $processed = $this->imageService->applyProcessingInstructions($image,
            [
                'maxWidth'  => $maxDimension,
                'maxHeight' => $maxDimension,
            ]);
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
