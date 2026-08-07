<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Service;

use Doctrine\DBAL\ParameterType;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wegewerk\Ai3Alttext\Domain\Capabilities\AlttextCapability;
use Wegewerk\Ai3Alttext\Domain\Repository\FilemetadataRepository;
use Wegewerk\Ai3Core\Domain\Model\Dto\AddGenerationTask;
use Wegewerk\Ai3Core\Domain\Model\GenerationTask;
use Wegewerk\Ai3Core\Enums\Status;
use Wegewerk\Ai3Core\Service\GenerationTaskService;

class AlttextTaskService
{
    public function __construct(
        private FilemetadataRepository $filemetadataRepository,
        private AlttextCapability $alttextCapability,
        private GenerationTaskService $generationTaskService,
        private ConnectionPool $connectionPool,
    ) {}

    public function createTaskForFile(int $fileUid, int $langUid = 0, string $langIsoCode = 'de'): GenerationTask
    {
        $metadataUid = $this->filemetadataRepository->getFilemetadataUidForLanguage($fileUid, $langUid);
        $metadata = $this->filemetadataRepository->getMetadata($fileUid, $langUid);

        $prompt = $metadata['title'] ?? '' . $metadata['description'] ?? '';
        $imagePath = $this->getImagePath($fileUid);
        $dto = new AddGenerationTask(
            Status::pending->value,
            $prompt,
            $imagePath,
            $this->alttextCapability->key,
            'sys_file_metadata',
            'alternative',
            $metadataUid,
            $langIsoCode,
            '',
            '',
            '',
            ''
        );

        return $this->generationTaskService->addTask($dto);
    }

    public function getImagePath(int $fileUid): string
    {
        $fileQueryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_file');
        $fileRecord = $fileQueryBuilder
            ->select('identifier', 'storage')
            ->from('sys_file')
            ->where(
                $fileQueryBuilder->expr()->eq('uid', $fileQueryBuilder->createNamedParameter($fileUid, ParameterType::INTEGER))
            )
            ->executeQuery()
            ->fetchAssociative();

        if (!$fileRecord || empty($fileRecord['identifier'])) {
            throw new \InvalidArgumentException('Datei mit der angegebenen ID nicht gefunden.');
        }

        $storage = (int)($fileRecord['storage'] ?? 1);
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $file = $resourceFactory->getFileObjectFromCombinedIdentifier(implode(':', [$storage, $fileRecord['identifier']]));

        return $file->getForLocalProcessing(false);
    }

    public function getRefs(int $fileUid): int
    {
        $queryBuilder = $this->connectionPool->getQueryBuilderForTable('sys_refindex');

        $predicates = [
            $queryBuilder->expr()->eq(
                'ref_table',
                $queryBuilder->createNamedParameter('sys_file')
            ),
            $queryBuilder->expr()->eq(
                'ref_uid',
                $queryBuilder->createNamedParameter($fileUid, Connection::PARAM_INT)
            ),
            $queryBuilder->expr()->neq(
                'tablename',
                $queryBuilder->createNamedParameter('sys_file_metadata')
            ),
        ];

        $rows = $queryBuilder
            ->select('*')
            ->from('sys_refindex')
            ->where(...$predicates)
            ->executeQuery()
            ->fetchAllAssociative();
        return count($rows);
    }
}