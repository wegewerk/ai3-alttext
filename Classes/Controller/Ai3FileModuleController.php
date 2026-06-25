<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\Controller;

use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Backend\Attribute\AsController;
use TYPO3\CMS\Backend\Routing\RouteResult;
use TYPO3\CMS\Core\Authentication\BackendUserAuthentication;
use TYPO3\CMS\Core\Http\RedirectResponse;
use Wegewerk\Ai3Core\Controller\AbstractBackendController;

/**
 * Implements the ZakAi Dashboard
 */
#[AsController]
class Ai3FileModuleController extends AbstractBackendController
{
    public function initialize(ServerRequestInterface $request): void
    {
        $this->request = $request;
        $this->view = $this->moduleTemplateFactory->create($request);
        $this->buildButtonBar();
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai3_alttext/Resources/Private/Language/locallang.xlf');
    }
    public function handleRequest(ServerRequestInterface $request): ResponseInterface
    {
        $identifier = $request->getAttribute('route')->getOption('_identifier');
        /**
         * @var BackendUserAuthentication $backendUser
         */
        $backendUser = $GLOBALS['BE_USER'] ?? null;
        if ($backendUser !== null) {
            $modData = $backendUser->getModuleData('ai3_alttext');
            if ($modData == null) {
                $backendUser->pushModuleData('ai3_alttext', ['currentSubaction' => 'ai3_file']);
            } else {
                if ($identifier != 'ai3_alttext') {
                    $backendUser->pushModuleData('ai3_alttext', ['currentSubaction' => $identifier]);
                }
            }
            if ($identifier == 'ai3_alttext' && ($modData['currentSubaction'] ?? null) != null) {
                $url = $this->uriBuilder->buildUriFromRoute($modData['currentSubaction']);
                return new RedirectResponse($url, 302);
            }
        }

        $this->initialize($request);
        return $this->processRequest($request);
    }

    public function buildButtonBar()
    {
        /**
         * @var $route RouteResult
         */
        $identifier = $this->request->getAttribute('route')->getOption('_identifier');
        $buttonBar = $this->view->getDocHeaderComponent()->getButtonBar();
        $xlfPrefix = 'LLL:EXT:ai3_alttext/Resources/Private/Language/locallang.xlf:';
        $buttonBar->addButton(
            $this->buildButton(
                'actions-menu',
                $xlfPrefix . 'tx_ai3_alttext.module.actionmenu.files',
                'btn-md rounded btn-default ' . (in_array($identifier, ['ai3_file', 'ai3_alttext']) ? 'active' : ''),
                'ai3_file'
            )
        );
        $buttonBar->addButton(
            $this->buildButton(
                'actions-folder',
                $xlfPrefix . 'tx_ai3_alttext.module.actionmenu.folders',
                'btn-md mx-2 rounded btn-default ' . ($identifier == 'ai3_folder' ? 'active' : ''),
                'ai3_folder'
            )
        );
    }

}
