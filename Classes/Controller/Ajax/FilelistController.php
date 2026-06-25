<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Controller\Ajax;

use Doctrine\DBAL\ParameterType;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
use TYPO3\CMS\Core\Database\Connection;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Http\Response;
use TYPO3\CMS\Core\Resource\Exception\ResourceDoesNotExistException;
use TYPO3\CMS\Core\Resource\File;
use TYPO3\CMS\Core\Resource\FileInterface;
use TYPO3\CMS\Core\Resource\FileType;
use TYPO3\CMS\Core\Resource\Folder;
use TYPO3\CMS\Core\Resource\FolderInterface;
use TYPO3\CMS\Core\Resource\MetaDataAspect;
use TYPO3\CMS\Core\Resource\ProcessedFile;
use TYPO3\CMS\Core\Resource\ResourceFactory;
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
class FilelistController extends AbstractAjaxController
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
    public function listFiles(ServerRequestInterface $request): ResponseInterface
    {
        $langUid = (int)($request->getQueryParams()['langUid'] ?? 0);

        $requestedFolderIdentifier = $request->getQueryParams()['folder'] ?: null;
        if ($requestedFolderIdentifier !== null) {
            try {
                $folder = $this->getFolderFromIdentifier($requestedFolderIdentifier);
                $files = $this->getFilesIn($folder, $langUid);
                return $this->createJsonSuccessResponse(
                    new Response(),
                    [
                        'folder' => [
                            'identifier' => $folder->getCombinedIdentifier(),
                            'name' => $folder->getName(),
                            'storageUid' => $folder->getStorage()->getUid(),
                            'numUsed' => $this->countRefs($files),
                            'numWithoutAlttext' => $this->countWithoutAlttext($files),
                            'numUsedWithoutAlttext' => $this->countRefsWithoutAlttext($files),
                        ],
                        'files' => $files,
                    ]
                );
            } catch (\RuntimeException $e) {
                return $this->createJsonErrorResponse(
                    new Response(),
                    ['message' => $e->getMessage()]
                );
            } catch (ResourceDoesNotExistException $e) {
                return $this->createJsonErrorResponse(
                    new Response(),
                    ['message' => $e->getMessage()]
                );
            }
        } else {
            return $this->createJsonErrorResponse(
                new Response(),
                ['message' => 'Unbekannter Ordner (null)']
            );
        }
    }

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function saveFile(ServerRequestInterface $request): ResponseInterface
    {
        $file = $request->getParsedBody();
        $updateResult = $this->filemetadataRepository->updateAlttext((int)$file['uid'], $file['altText']);
        return $this->createJsonSuccessResponse(
            new Response(),
            [
                'file' => $file,
            ]
        );
    }

    public function addAlttextTaskForFile(ServerRequestInterface $request): ResponseInterface
    {
        try {
            $parsedBody = $request->getParsedBody();
            $fileUid = (int)$parsedBody['file'];
            $langUid = $parsedBody['langUid'] ?? 0;
            $langIsoCode = $parsedBody['language'] ?? 'de';

            $metadataUid = $this->filemetadataRepository->getFilemetadataUidForLanguage($fileUid, $langUid);
            $metadata = $this->filemetadataRepository->getMetadata($fileUid, $langUid);

            $prompt = $metadata['title'] ?? '' . $metadata['description'] ?? '';
            $service = 'zakaiservice';
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
                '',
                ''
            );
            $this->generationTaskService->addTask($dto);

            return $this->createJsonSuccessResponse(
                new Response(),
                [ 'message' => 'Generation task added' ]
            );
        } catch (\Exception $e) {
            $this->logger->error(
                sprintf('Fehler beim Generieren des Alttextes: %s', $e->getMessage()),
                new Response(),
                500
            );
            return $this->createJsonErrorResponse(
                new Response(),
                [ 'message' => $e->getMessage() ]
            );
        }
    }
    /**
     * @param $folderIdentifier
     * @return FolderInterface
     * @throws \RuntimeException
     * @throws ResourceDoesNotExistException
     */
    protected function getFolderFromIdentifier($folderIdentifier): FolderInterface
    {
        /** @var ResourceFactory $resourceFactory */
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $object = $resourceFactory->retrieveFileOrFolderObject($folderIdentifier);
        if ($object instanceof Folder) {
            return $object;
        }
        if ($object instanceof FileInterface) {
            return $object->getParentFolder();
        }

        throw new \RuntimeException('Folder ' . $folderIdentifier . ' is not accessible current user.', 1713000001);
    }

    private function getFilesIn(FolderInterface $folder, int $langUid)
    {
        $filesData = [];
        $files = $folder->getFiles();
        foreach ($files as $file) {
            if ($file instanceof FileInterface) {
                $isImage = ($file instanceof File && $file->getType() === FILETYPE::IMAGE) || str_starts_with((string)$file->getMimeType(), 'image/');

                if ($isImage) {
                    try {
                        $processed = $file->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, [
                            'width' => 200,
                            'height' => 150,
                        ]);
                        $thumbUrl = $processed->getPublicUrl();
                    } catch (\Throwable $e) {
                        $thumbUrl = null;
                    }
                    /** @var MetaDataAspect $meta */
                    $meta = method_exists($file, 'getMetaData') ? $file->getMetaData() : [];
                    $metadataUid = $meta['uid'] ?? null;
                    if ($metadataUid) {
                        if (($meta['alternative'] ?? '') == '') {
                            $hasGeneration = $this->generationTaskService->hasGenerationDone($metadataUid);
                        } else {
                            $hasGeneration = $this->generationTaskService->hasGenerationDone($metadataUid) && $this->generationTaskService->lastGenerationNewerThan(
                                $metadataUid,
                                $meta['tstamp']
                            );
                        }
                        $filesData[] = [
                            'uid'           => $file->getUid(),
                            'metadataUid'   => $metadataUid,
                            'name'          => $file->getName(),
                            'identifier'    => $file->getCombinedIdentifier(),
                            'publicUrl'     => $file->getPublicUrl(),
                            'editlink'      => $this->generateEditRecordLink($file->getUid(), $langUid),
                            'thumbnailUrl'  => $thumbUrl,
                            'numrefs'       => (string)$this->getRefs($file->getUid()),
                            'title'         => (string)($meta['title'] ?? ''),
                            'description'   => (string)($meta['description'] ?? ''),
                            'alternative'   => (string)($meta['alternative'] ?? ''),
                            'mimeType'      => $file->getMimeType(),
                            'size'          => $file->getSize(),
                            'isImage'       => true,
                            'inProgress'    => $this->generationTaskService->isTaskRunning($metadataUid),
                            'isGenerated'   => $this->generationTaskService->isGenerated(
                                $metadataUid,
                                (string)($meta['alternative'] ?? '')
                            ),
                            'isReviewed'    => $this->generationTaskService->isReviewed($metadataUid),
                            'hasGeneration' => $hasGeneration,
                            'altSuggestion' => $this->generationTaskService->getLatestResult($metadataUid),
                        ];
                    }
                }
            }
        }
        return $filesData;
    }

    private function generateEditRecordLink(int $uid, int $langUid)
    {
        $metadataUid = $this->filemetadataRepository->getFilemetadataUidForLanguage($uid, $langUid);
        $params = [
            'edit' => ['sys_file_metadata' => [$metadataUid => 'edit']],
        ];
        $uriBuilder = GeneralUtility::makeInstance(UriBuilder::class);
        return (string)$uriBuilder->buildUriFromRoute('record_edit', $params);
    }

    private function getRefs(int $fileUid)
    {
        $queryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_refindex');

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

    private function countRefs(array $files)
    {
        $numUsedFiles = 0;
        foreach ($files as $file) {
            if ($file['numrefs'] > 0) {
                $numUsedFiles += 1;
            }
        }
        return $numUsedFiles;
    }

    private function countRefsWithoutAlttext(array $files)
    {
        $num = 0;
        foreach ($files as $file) {
            if ($file['numrefs'] > 0 && $file['alternative'] === '') {
                $num += 1;
            }
        }
        return $num;
    }
    private function countWithoutAlttext(array $files)
    {
        $num = 0;
        foreach ($files as $file) {
            if ($file['alternative'] === '') {
                $num += 1;
            }
        }
        return $num;
    }

    private function getImagePath(int $fileUid)
    {
        $fileQueryBuilder = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getQueryBuilderForTable('sys_file');
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

        $storage = $fileRecord['storage'] ?? 1;
        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);
        $file = $resourceFactory->getFileObjectFromCombinedIdentifier(implode(':', [$storage, $fileRecord['identifier']]));

        return $file->getForLocalProcessing(false);
    }

}
