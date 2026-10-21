<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Controller\Ajax;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
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
use Wegewerk\Ai3Alttext\Service\AlttextTaskService;
use Wegewerk\Ai3Core\Controller\Ajax\AbstractAjaxController;
use Wegewerk\Ai3Core\Service\GenerationTaskService;

#[\AllowDynamicProperties]
#[AsController]
class FolderController extends AbstractAjaxController
{
    public function __construct(
        LoggerInterface $logger,
        private FilemetadataRepository $filemetadataRepository,
        private AlttextCapability $alttextCapability,
        private GenerationTaskService $generationTaskService,
        private AlttextTaskService $alttextTaskService,
        protected StorageRepository $storageRepository
    ) {
        parent::__construct(
            $logger
        );
    }

    public function acceptAllSuggestionsRecursive(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $langUid = (int)($body['langUid'] ?? 0);
        $requestedFolderIdentifier = $body['folder'] ?: null;

        if ($requestedFolderIdentifier === null) {
            return $this->createJsonErrorResponse(
                new Response(),
                [ 'message' => 'Unbekannter Ordner (null)' ]
            );
        }
        try {
            $folder = $this->getFolderFromIdentifier($requestedFolderIdentifier);
            $files = $this->getFilesIn($folder);
            $responseData = ['reviewed' => 0, 'accepted' => 0];
            foreach ($files as $file) {
                if ($file['hasGeneration'] === true) {
                    $task = $this->generationTaskService->getTask((int)$file['metadataUid']);
                    if ($task !== null) {
                        if ($task->hasResult() && $task->isDone()) {
                            $this->generationTaskService->setReviewed($task);
                            $responseData['reviewed']++;
                            $updateResult = $this->filemetadataRepository->updateAlttext(
                                (int)$file['uid'],
                                $task->getResult()
                            );
                            if ($updateResult == 1) {
                                $responseData['accepted']++;
                            }
                        }
                    }
                }
            }
            return $this->createJsonSuccessResponse(
                new Response(),
                $responseData
            );
        } catch (\RuntimeException $e) {
            return $this->createJsonErrorResponse(
                new Response(),
                [ 'message' => $e->getMessage() ]
            );
        } catch (ResourceDoesNotExistException $e) {
            return $this->createJsonErrorResponse(
                new Response(),
                [ 'message' => $e->getMessage() ]
            );

        }

    }

    public function addAlttextTasksRecursive(ServerRequestInterface $request): ResponseInterface
    {
        $body = $request->getParsedBody();
        $langUid = (int)($body['langUid'] ?? 0);
        $langIsoCode = $body['language'] ?? 'de';
        $filter = $body['filter'] ?? 'all';
        $requestedFolderIdentifier = $body['folder'] ?: null;

        if ($requestedFolderIdentifier === null) {
            return $this->createJsonErrorResponse(
                new Response(),
                [ 'message' => 'Unbekannter Ordner (null)' ]
            );
        }
        try {
            $folder = $this->getFolderFromIdentifier($requestedFolderIdentifier);
            $files = $this->getFilesIn($folder, $langUid, true);
            $responseData = ['tasksCreated' => 0, 'skipped' => 0];
            foreach ($files as $file) {
                if ($this->shouldCreateTask($file, $filter)) {
                    $this->alttextTaskService->createTaskForFile((int)$file['uid'], $langUid, $langIsoCode);
                    $responseData['tasksCreated']++;
                } else {
                    $responseData['skipped']++;
                }
            }
            return $this->createJsonSuccessResponse(
                new Response(),
                $responseData
            );
        } catch (\RuntimeException $e) {
            return $this->createJsonErrorResponse(
                new Response(),
                [ 'message' => $e->getMessage() ]
            );
        } catch (ResourceDoesNotExistException $e) {
            return $this->createJsonErrorResponse(
                new Response(),
                [ 'message' => $e->getMessage() ]
            );

        }
    }

    private function shouldCreateTask(array $file, string $filter): bool
    {
        if (($file['inProgress'] ?? false) === true) {
            return false;
        }
        if ($filter === 'withoutAlttext') {
            return ($file['alternative'] ?? '') === '';
        }
        if ($filter === 'usedWithoutAlttext') {
            return ($file['alternative'] ?? '') === '' && ($file['numrefs'] ?? 0) > 0;
        }
        return true;
    }

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

    /**
     * @throws \InvalidArgumentException
     * @throws \RuntimeException
     */
    public function listFolders(ServerRequestInterface $request): ResponseInterface
    {
        $requestedFolderIdentifier = $request->getQueryParams()['folder'] ?: null;
        if ($requestedFolderIdentifier !== null) {
            try {
                $folder = $this->getFolderFromIdentifier($requestedFolderIdentifier);
                $visibleSubfolders = [];
                $subfolders = $folder->getSubfolders(0, 0, $folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, false);
                foreach ($subfolders as $subfolder) {
                    if ($subfolder instanceof Folder
                        && !str_contains($subfolder->getIdentifier(), '_processed_')) {
                        $subfolderMetadata = $this->getFolderMetadata($subfolder);
                        $visibleSubfolders[] = array_merge([
                            'identifier' => $subfolder->getCombinedIdentifier(),
                            'name' => $subfolder->getName(),
                        ], $subfolderMetadata);
                    }
                }

                $folderMetadata = $this->getFolderMetadata($folder, true);
                return $this->createJsonSuccessResponse(
                    new Response(),
                    [
                        'folder' => array_merge([
                            'identifier' => $folder->getCombinedIdentifier(),
                            'name' => $folder->getName(),
                            'storageUid' => $folder->getStorage()->getUid(),
                            'numSubfolders' => count($visibleSubfolders),
                        ], $folderMetadata),
                        'children' => $visibleSubfolders,
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

    private function getFilesIn(FolderInterface $folder, $langUid = 0, bool $includeRefs = false, bool $includeThumbnails = false): array
    {
        $filesData = [];
        $files = $folder->getFiles(0, 0, $folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, true);
        foreach ($files as $file) {
            if ($file instanceof FileInterface) {
                $isImage = ($file instanceof File && $file->getType() === FILETYPE::IMAGE) || str_starts_with((string)$file->getMimeType(), 'image/');

                if ($isImage) {
                    /** @var MetaDataAspect $meta */
                    $meta = method_exists($file, 'getMetaData') ? $file->getMetaData() : [];
                    $metadataUid = $meta['uid'] ?? null;
                    if ($metadataUid) {
                        $hasGeneration = $this->generationTaskService->hasGenerationDone($metadataUid);
                        if (($meta['alternative'] ?? '') != '') {
                            $hasGeneration = $hasGeneration
                                          && $this->generationTaskService->lastGenerationNewerThan($metadataUid, $meta['tstamp']);
                        }
                        $filesData[] = [
                            'uid' => $file->getUid(),
                            'name' => $file->getName(),
                            'hasGeneration' => $hasGeneration,
                            'metadataUid' => $metadataUid,
                            'alternative' => (string)($meta['alternative'] ?? ''),
                            'inProgress' => $this->generationTaskService->isTaskRunning($metadataUid),
                            'numrefs' => $includeRefs ? $this->alttextTaskService->getRefs($file->getUid()) : 0,
                            'thumbnailUrl' => $includeThumbnails ? $this->getThumbnailUrl($file) : null,
                        ];
                    }
                }
            }
        }
        return $filesData;
    }

    private function getThumbnailUrl(FileInterface $file): ?string
    {
        try {
            $processed = $file->process(ProcessedFile::CONTEXT_IMAGEPREVIEW, [
                'width' => 200,
                'height' => 150,
            ]);
            return $processed->getPublicUrl();
        } catch (\Throwable $e) {
            return null;
        }
    }

    /**
     * @return array{
     *     imageCount: int,
     *     imagesWithoutAlttextCount: int,
     *     hasImagesWithoutAlttext: bool,
     *     hasImages: bool,
     *     countGenerations: int,
     *     countPending: int,
     *     queueFiles: array
     * }
     */
    private function getFolderMetadata(FolderInterface $folder, bool $withQueueFiles = false): array
    {
        $metadata = [
            'imageCount' => 0,
            'imagesWithoutAlttextCount' => 0,
            'hasImagesWithoutAlttext' => false,
            'hasImages' => false,
            'countGenerations' => 0,
            'countPending' => 0,
            'queueFiles' => [],
        ];
        try {
            $files = $this->getFilesIn($folder, 0, false, $withQueueFiles);
            $metadata['imageCount'] = count($files);
            $metadata['hasImages'] = $metadata['imageCount'] > 0;
            foreach ($files as $file) {
                if (($file['alternative'] ?? '') === '') {
                    $metadata['imagesWithoutAlttextCount']++;
                }
                if (($file['hasGeneration'] ?? false) === true) {
                    $metadata['countGenerations']++;
                }
                if (($file['inProgress'] ?? false) === true) {
                    $metadata['countPending']++;
                }
                if ($withQueueFiles && (($file['inProgress'] ?? false) === true || ($file['hasGeneration'] ?? false) === true)) {
                    $metadata['queueFiles'][] = [
                        'uid' => $file['uid'],
                        'name' => $file['name'],
                        'thumbnailUrl' => $file['thumbnailUrl'],
                        'inProgress' => (bool)($file['inProgress'] ?? false),
                        'hasGeneration' => (bool)($file['hasGeneration'] ?? false),
                    ];
                }
            }
            $metadata['hasImagesWithoutAlttext'] = $metadata['imagesWithoutAlttextCount'] > 0;
        } catch (\Throwable $e) {
            // im Fehlerfall Standardwerte zurückgeben
        }
        return $metadata;
    }
}
