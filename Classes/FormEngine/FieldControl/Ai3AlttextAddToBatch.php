<?php

declare(strict_types=1);

namespace Wegewerk\Ai3Alttext\FormEngine\FieldControl;

use Psr\Log\LoggerInterface;
use TYPO3\CMS\Backend\Form\AbstractNode;
use TYPO3\CMS\Core\Localization\LanguageService;
use TYPO3\CMS\Core\Log\LogManager;
use TYPO3\CMS\Core\Page\JavaScriptModuleInstruction;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use Wegewerk\Ai3Core\Service\GenerationTaskService;

/**
 * Erzeugt den Button um die Generierung des Alttextes zum Schedule-Task hinzuzufügen
 */
class Ai3AlttextAddToBatch extends AbstractNode
{
    private const JS_MODULE = '@wegewerk/ai3alttext/add-to-batch.js';
    private const LANG_FILE = 'LLL:EXT:ai3_alttext/Resources/Private/Language/locallang.xlf:';
    private const FIELD_NAME = 'alternative';

    protected GenerationTaskService $generationTaskService;
    protected ?LoggerInterface $logger;
    private LanguageService $languageService;

    public function __construct(
        GenerationTaskService $generationTaskService,
    ) {
        $this->generationTaskService = $generationTaskService;
        $this->logger = GeneralUtility::makeInstance(LogManager::class)->getLogger(__CLASS__);
        $this->languageService = $GLOBALS['LANG'];
    }

    public function render(): array
    {
        return $this->buildRenderArray();
    }

    /**
     * Erstellt das Render-Array für das Button-Element
     */
    private function buildRenderArray(): array
    {
        return [
            'iconIdentifier' => 'actions-synchronize',
            'title' => $this->languageService->sL(self::LANG_FILE . 'generation.alttextAdToBatch'),
            'linkAttributes' => $this->buildLinkAttributes(),
            'javaScriptModules' => [
                JavaScriptModuleInstruction::create(self::JS_MODULE),
            ],
        ];
    }

    /**
     * Erstellt die Attribute für den Link
     */
    private function buildLinkAttributes(): array
    {
        $inProgress = $this->generationTaskService->isTaskRunning($this->data['databaseRow']['uid']);
        $isGenerated = $this->generationTaskService->isGenerated($this->data['databaseRow']['uid'], $this->data['databaseRow']['alternative']);
        $isReviewed = $this->generationTaskService->isReviewed($this->data['databaseRow']['uid']);

        return [
            'id' => 'alttext_add_to_batch',
            'class' => 'ai3-alttext-add-to-batch-btn',
            'data-id' => $this->data['databaseRow']['uid'] ?? '',
            'data-table' => $this->data['tableName'] ?? '',
            'data-field-name' => self::FIELD_NAME,
            'data-field-label' => 'Alttext batch generation',
            'data-in-progress' => $inProgress,
            'data-is-generated' => $isGenerated,
            'data-is-reviewed' => $isReviewed,
        ];
    }
}
