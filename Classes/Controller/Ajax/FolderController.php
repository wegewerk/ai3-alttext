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
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Resource\StorageRepository;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wegewerk\Ai3Alttext\Domain\Capabilities\AlttextCapability;
use Wegewerk\Ai3Alttext\Domain\Repository\FilemetadataRepository;
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
                $subfolders = $folder->getSubfolders(0, 0, $folder::FILTER_MODE_USE_OWN_AND_STORAGE_FILTERS, true);
                foreach ($subfolders as $subfolder) {
                    if ($subfolder instanceof Folder
                        && !str_contains($subfolder->getIdentifier(), '_processed_')) {
                        $visibleSubfolders[] = $folder;
                    }
                }

                return $this->createJsonSuccessResponse(
                    new Response(),
                    [
                        'folder' => [
                            'identifier' => $folder->getCombinedIdentifier(),
                            'name' => $folder->getName(),
                            'storageUid' => $folder->getStorage()->getUid(),
                            'numSubfolders' => count($visibleSubfolders),
                            'countGenerations' => $this->countGenerationsRecursive($requestedFolderIdentifier),
                        ],
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

    private function getFilesIn(FolderInterface $folder, $langUid = 0): array
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
                            'hasGeneration' => $hasGeneration,
                            'metadataUid' => $metadataUid,
                        ];
                    }
                }
            }
        }
        return $filesData;
    }

    private function countGenerationsRecursive(mixed $requestedFolderIdentifier): int
    {
        $folder = $this->getFolderFromIdentifier($requestedFolderIdentifier);
        $files = $this->getFilesIn($folder);
        $count = 0;
        try {
            foreach ($files as $file) {
                if (!empty($file['hasGeneration'])) {
                    if ($file['hasGeneration'] === true) {
                        $count++;
                    }
                }
            }
            return $count;
        } catch (\Throwable $e) {
            // im Fehlerfall ist count einfach 0
            return $count;
        }
    }

}
