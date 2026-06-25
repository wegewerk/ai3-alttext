<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\EventListener;

use TYPO3\CMS\Backend\Controller\Event\AfterFormEnginePageInitializedEvent;
use TYPO3\CMS\Core\Attribute\AsEventListener;
use TYPO3\CMS\Core\Page\PageRenderer;

#[AsEventListener(
    identifier: 'ai3_alttext/after-form-engine-page-initialized-event-listener',
    event: AfterFormEnginePageInitializedEvent::class,
)]
class AfterFormEnginePageInitializedEventListener
{
    protected PageRenderer $pageRenderer;
    public function __construct(PageRenderer $pageRenderer)
    {
        $this->pageRenderer = $pageRenderer;
    }

    public function __invoke(AfterFormEnginePageInitializedEvent $event): void
    {
        $this->pageRenderer->addInlineLanguageLabelFile('EXT:ai3_alttext/Resources/Private/Language/locallang.xlf');
    }
}
