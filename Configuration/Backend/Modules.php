<?php

use Wegewerk\Ai3Alttext\Controller\Ai3FileController;

return [
    'ai3_alttext' => [
        'parent' => 'file',
        'position' => ['after' => 'media_management'],
        'access' => 'user',
        'workspaces' => 'live',
        'path' => '/module/file/ai3_alttext',
        'iconIdentifier' => 'ai3alttext-extension',
        'labels' => 'LLL:EXT:ai3_alttext/Resources/Private/Language/locallang_mod.xlf',
        'routes' => [
            '_default' => [
                'target' => Ai3FileController::class . '::handleRequest',
            ],
        ],
        'moduleData' => [
            'currentSubaction' => '',
        ],
    ],
];
