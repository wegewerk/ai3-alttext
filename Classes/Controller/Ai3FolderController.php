<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;

#[AsController]
class Ai3FolderController extends Ai3FileModuleController
{
    protected string $id = '';
    protected string $cmd = '';
    protected int $currentPage = 1;

    protected function processRequest(ServerRequestInterface $request): ResponseInterface
    {
        $this->pageRenderer->loadJavaScriptModule('@wegewerk/ai3alttext/subFolders.js');
        $this->pageRenderer->addCssFile('EXT:ai3_alttext/Resources/Public/Css/files.css');
        return $this->view->renderResponse('Ai3File/Folder');
    }
}
