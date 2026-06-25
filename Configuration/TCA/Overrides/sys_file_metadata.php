<?php

declare(strict_types=1);

defined('TYPO3') or die();

use TYPO3\CMS\Core\Utility\ArrayUtility;

ArrayUtility::mergeRecursiveWithOverrule(
    $GLOBALS['TCA']['sys_file_metadata']['columns']['alternative']['config'],
    [
        'type' => 'text',
        'cols' => 43,
        'rows' => 2,
        'fieldControl' => [
            'ai3_alttext_add_to_batch' => [
                'renderType' => 'ai3AlttextAddToBatch',
            ],
        ],
    ],
    true,
    false
);
