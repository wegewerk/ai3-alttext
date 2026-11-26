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
            'additionalInlineLanguageLabelFiles' => [
                'EXT:ai3_alttext/Resources/Private/Language/locallang.xlf'
            ]
        ];
    }

    /**
     * Erstellt die Attribute für den Link
     */
    private function buildLinkAttributes(): array
    {
        $uid = (int)($this->data['databaseRow']['uid'] ?? 0);
        $alternative = (string)($this->data['databaseRow']['alternative'] ?? '');
        $tstamp = (int)($this->data['databaseRow']['tstamp'] ?? 0);

        $inProgress = $this->generationTaskService->isTaskRunning($uid);
        $isGenerated = $this->generationTaskService->isGenerated($uid, $alternative);
        $isReviewed = $this->generationTaskService->isReviewed($uid);
        $hasGeneration = $this->hasGeneration($uid, $alternative, $tstamp);

        return [
            'id' => 'alttext_add_to_batch',
            'class' => 'ai3-alttext-add-to-batch-btn',
            'data-id' => $uid ?: '',
            'data-table' => $this->data['tableName'] ?? '',
            'data-field-name' => self::FIELD_NAME,
            'data-field-label' => 'Alttext batch generation',
            'data-in-progress' => $inProgress,
            'data-is-generated' => $isGenerated,
            'data-is-reviewed' => $isReviewed,
            'data-has-generation' => $hasGeneration,
            'data-alt-suggestion' => $hasGeneration ? $this->generationTaskService->getLatestResult($uid) : '',
        ];
    }

    /**
     * Prüft, ob ein noch nicht übernommener Generierungsvorschlag vorliegt.
     *
     * Entspricht der Logik aus fileElement.js: ein abgeschlossener Task existiert
     * und ist neuer als der aktuelle Datensatz (bzw. das Feld ist noch leer).
     */
    private function hasGeneration(int $uid, string $alternative, int $tstamp): bool
    {
        if ($uid === 0 || !$this->generationTaskService->hasGenerationDone($uid)) {
            return false;
        }

        return $alternative === '' || $this->generationTaskService->lastGenerationNewerThan($uid, $tstamp);
    }
}
