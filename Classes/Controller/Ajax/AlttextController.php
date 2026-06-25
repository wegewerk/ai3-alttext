<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Controller\Ajax;

use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wegewerk\Ai3Alttext\Domain\Capabilities\AlttextCapability;
use Wegewerk\Ai3Alttext\Domain\Repository\FilemetadataRepository;
use Wegewerk\Ai3Core\Controller\Ajax\AbstractAjaxController;
use Wegewerk\Ai3Core\Domain\Model\Dto\AddGenerationTask;
use Wegewerk\Ai3Core\Enums\Status;
use Wegewerk\Ai3Core\Service\GenerationTaskService;

#[\AllowDynamicProperties]
#[AsController]
class AlttextController extends AbstractAjaxController
{
    public function __construct(
        LoggerInterface $logger,
        private FilemetadataRepository $filemetadataRepository,
        private AlttextCapability $alttextCapability,
        private GenerationTaskService $generationTaskService,
        protected StorageRepository $storageRepository
    ) {
        parent::__construct(
            $logger
        );
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function addAlttextGenerationTaskAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = $request->getParsedBody();
            $langUid = (int)($parsedBody['langUid'] ?? 0);
            $langIsoCode = $parsedBody['language'] ?? 'de';

            $this->validateRequestParameters(['record_uid'], $parsedBody);
            $this->validateConcurrentTaskAdding((int)$parsedBody['record_uid']);

            $metadataUid = $this->filemetadataRepository->getFilemetadataUidForLanguage((int)$parsedBody['record_uid'], $langUid);
            $metadata = $this->filemetadataRepository->getMetadata((int)$parsedBody['record_uid'] ?? 0, $langUid);

            $prompt = $metadata['title'] ?? '' . $metadata['description'] ?? '';
            $service = 'zakaiservice';

            $imagePath = $this->getImagePath((int)$parsedBody['record_uid']);
            $dto = new AddGenerationTask(
                Status::pending->value,
                $prompt,
                $imagePath,
                $this->alttextCapability->key,
                $service,
                '',
                'sys_file_metadata',
                'alternative',
                $metadataUid,
                $langIsoCode,
                '',
                '',
                '',
                '',
            );
            $this->generationTaskService->addTask($dto);

            return $this->createJsonSuccessResponse(
                new Response(),
                ['message' => 'Generation task added']
            );
        } catch (\Exception $e) {
            $this->logger->Error(
                sprintf('Fehler beim Generieren des Alttextes: %s', $e->getMessage()),
            );
            return $this->createJsonErrorResponse(
                new Response(),
                ['message' => $e->getMessage()]
            );

        }
    }

    private function validateRequestParameters(array $requiredParams, array $parameters): void
    {
        $missing = array_diff($requiredParams, array_keys($parameters));

        if (!empty($missing)) {
            throw new \InvalidArgumentException(
                sprintf('Fehlende Parameter: %s', implode(', ', $missing))
            );
        }
    }

    private function validateConcurrentTaskAdding(int $record_uid): void
    {
        $inProgress = $this->generationTaskService->isTaskRunning($record_uid);

        if ($inProgress) {
            throw new \InvalidArgumentException(
                sprintf('Generation Task für uid %s läuft bereits', $record_uid)
            );
        }
    }

    private function getImagePath(int $record_uid): string
    {
        $this->logger->debug('getImagePath', ['record_uid' => $record_uid]);

        // Hole den Datensatz aus sys_file_metadata
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file_metadata');
        $record = $queryBuilder
            ->select('file')
            ->from('sys_file_metadata')
            ->where(
                $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($record_uid, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$record || empty($record['file'])) {
            throw new \InvalidArgumentException('Metadaten mit der angegebenen ID nicht gefunden.');
        }

        $this->logger->debug('Metadaten gefunden', ['record' => $record]);

        // Hole den sys_file-Datensatz
        $fileQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file');
        $fileRecord = $fileQueryBuilder
            ->select('identifier', 'storage')
            ->from('sys_file')
            ->where(
                $fileQueryBuilder->expr()->eq('uid', $fileQueryBuilder->createNamedParameter($record['file'], ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$fileRecord || empty($fileRecord['identifier'])) {
            throw new \InvalidArgumentException('Datei mit der angegebenen ID nicht gefunden.');
        }

        $this->logger->debug('Datei gefunden', ['fileRecord' => $fileRecord]);

        $storage = $this->storageRepository->getStorageObject($fileRecord['storage'] ?? 1, [], $fileRecord['identifier']);
        $file = $storage->getFileByIdentifier($fileRecord['identifier']);

        $this->logger->debug('Datei-Storage gefunden', ['file' => $file]);

        return $file->getForLocalProcessing(false);
    }

    public function reviewAlttextAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = $request->getParsedBody();

            $this->validateRequestParameters(['record_uid'], $parsedBody);

            $task = $this->generationTaskService->getTask((int)$parsedBody['record_uid']);
            if (($task !== null) && !$task->isReviewed()) {
                $this->generationTaskService->setReviewed($task);
            }

            return $this->createJsonSuccessResponse(
                new Response(),
                []
            );
        } catch (\Exception $e) {
            $message = sprintf('Fehler beim Prüfen des Alttextes: %s', $e->getMessage());
            $this->logger->error(
                $message,
            );
            return $this->createJsonErrorResponse(
                new Response(),
                ['message' => $message ]
            );
        }
    }

    public function selectAlttextAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = $request->getParsedBody();

            $this->validateRequestParameters(['record_uid'], $parsedBody);

            $task = $this->generationTaskService->getTask((int)$parsedBody['record_uid']);
            if (($task !== null) && $task->getStatus() == Status::done) {
                $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
                    ->getQueryBuilderForTable($task->getRecordTable());

                $queryBuilder
                    ->update($task->getRecordTable())
                    ->where(
                        $queryBuilder->expr()->eq('uid', $queryBuilder->createNamedParameter($task->getRecordUid(), Connection::PARAM_INT)),
                    )
                    ->set($task->getRecordField(), $task->getResult())
                    ->executeStatement();
            }

            return $this->createJsonSuccessResponse(
                new Response(),
                ['alttext' => $task->getResult(), 'message' => 'metadaten gespeichert'],
            );
        } catch (\Exception $e) {
            $this->logger->Error(
                sprintf('Fehler beim Prüfen des Alttextes: %s', $e->getMessage()),
            );
        }
        return $this->createJsonErrorResponse(
            new Response(),
            []
        );
    }

    public function checkAlttextGenerationStatusAction(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = $request->getParsedBody();
            $this->validateRequestParameters(['record_uid'], $parsedBody);

            $uid = (int)$parsedBody['record_uid'];

            $task = $this->generationTaskService->getTask($uid);

            $running = $this->generationTaskService->isTaskRunning($uid);

            $payload = [
                'running' => $running,
                'done' => false,
                'reviewed' => false,
                'result' => '',
            ];

            if ($task !== null && !$running) {
                try {
                    $status = $task->getStatus();
                    if ($status === Status::done) {
                        $payload['done'] = true;
                        $payload['result'] = $task->getResult();
                        $payload['reviewed'] = $task->isReviewed();
                    }
                } catch (\Throwable $t) {
                }
            }

            return $this->createJsonSuccessResponse(
                new Response(),
                $payload
            );
        } catch (\Exception $e) {
            $message = sprintf('Fehler beim Prüfen des Alttextes: %s', $e->getMessage());
            $this->logger->error(
                $message,
            );
            return $this->createJsonErrorResponse(
                new Response(),
                ['message' => $message ]
            );
        }
    }

    private function prepareSlideParams(array $requestData, array $answers): array
    {
        return [
            'alttextSuggestions' => $answers,
            'record_field' => $requestData['record_field'] ?? '',
            'record_table' => $requestData['record_table'] ?? '',
            'record_uid' => $requestData['record_uid'],
        ];
    }

    /**
     * Extrahiert den ersten brauchbaren Text aus einer Chat-Completions-Antwort
     * und bereitet ihn als Alt-Text auf (max. 120 Zeichen, ohne Floskeln).
     *
     * @return string|null Alt-Text oder null, wenn keiner extrahierbar ist
     */
    private function extractAltTextFromChatCompletion(array $body): ?string
    {
        if (empty($body['choices']) || !is_array($body['choices'])) {
            return null;
        }

        foreach ($body['choices'] as $choice) {
            // finish_reason prüfen (z. B. "stop", "length", "content_filter")
            $finish = $choice['finish_reason'] ?? null;

            // 1) Normalfall: assistant->content ist ein String
            $msg = $choice['message'] ?? [];
            $content = $msg['content'] ?? '';

            // 2) Fallback: Manche Proxies liefern content als Parts-Array
            if (is_array($content)) {
                $textParts = [];
                foreach ($content as $part) {
                    if (($part['type'] ?? '') === 'text' && isset($part['text'])) {
                        $textParts[] = (string)$part['text'];
                    }
                }
                $content = trim(implode("\n", $textParts));
            }

            $text = is_string($content) ? trim($content) : '';

            // Wenn leer und wegen Längenlimit abgebrochen, nächsten Choice prüfen
            if ($text === '' && $finish === 'length') {
                continue;
            }

            if ($text !== '') {
                // Post-Processing für Alt-Text
                //  - Zeilenumbrüche & Mehrfachspaces reduzieren
                $text = preg_replace('/\s+/u', ' ', $text);
                //  - Floskeln entfernen („Bild von …“, „Foto von …“)
                $text = preg_replace('/\b(Bild|Foto|Grafik)\s+(von|mit)\b.*$/iu', '', $text);
                $text = trim($text, " \t\n\r\0\x0B\"'"); // Anführungszeichen etc. kappen

                //  - „EMPTY“ als leeren Alt-Text interpretieren (Deko-Bild)
                if (mb_strtoupper($text, 'UTF-8') === 'EMPTY') {
                    return '';
                }

                //  - auf 120 Zeichen begrenzen
                $text = mb_substr($text, 0, 120, 'UTF-8');

                return $text !== '' ? $text : null;
            }
        }

        return null;
    }
}
