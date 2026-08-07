<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Controller\Ajax;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\UriBuilder;
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
use Wegewerk\Ai3Alttext\Domain\Repository\FilemetadataRepository;
use Wegewerk\Ai3Alttext\Service\AlttextTaskService;
use Wegewerk\Ai3Core\Controller\Ajax\AbstractAjaxController;
use Wegewerk\Ai3Core\Service\GenerationTaskService;

#[AllowDynamicProperties]
#[AsController]
class FilelistController extends AbstractAjaxController
{
    public function __construct(
        LoggerInterface $logger,
        private FilemetadataRepository $filemetadataRepository,
        private GenerationTaskService $generationTaskService,
        private AlttextTaskService $alttextTaskService,
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
            $langUid = (int)($parsedBody['langUid'] ?? 0);
            $langIsoCode = $parsedBody['language'] ?? 'de';

            $this->alttextTaskService->createTaskForFile($fileUid, $langUid, $langIsoCode);

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
                            'numrefs'       => (string)$this->alttextTaskService->getRefs($file->getUid()),
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

}
