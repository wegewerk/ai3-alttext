<?php

return [
    'ai3_alttext_generation_create_task' => [
        'path' => '/ai3/ai-alttext-generation-task',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\AlttextController::class . '::addAlttextGenerationTaskAction',
    ],
    'ai3_alttext_generation_review' => [
        'path' => '/ai3/ai-alttext-generation-review',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\AlttextController::class . '::reviewAlttextAction',
    ],
    'ai3_alttext_generation_select' => [
        'path' => '/ai3/ai-alttext-generation-select',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\AlttextController::class . '::selectAlttextAction',
    ],
    'ai3_alttext_generation_status' => [
        'path' => '/ai3/ai-alttext-generation-status',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\AlttextController::class . '::checkAlttextGenerationStatusAction',
    ],
    'ai3_filelist' => [
        'path' => '/ai3/filelist',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\FilelistController::class . '::listFiles',
    ],
    'ai3_filelist_save_file' => [
        'path' => '/ai3/filelist/save-file',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\FilelistController::class . '::saveFile',
    ],
    'ai3_filelist_create_task_alttext' => [
        'path' => '/ai3/filelist/create-task-alttext',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\FilelistController::class . '::addAlttextTaskForFile',
    ],
    'ai3_folders_acceptAll_recursive' => [
        'path' => '/ai3/folders/accept-all-suggestions-recursive',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\FolderController::class . '::acceptAllSuggestionsRecursive',
    ],
    'ai3_folders' => [
        'path' => '/ai3/folders',
        'target' => \Wegewerk\Ai3Alttext\Controller\Ajax\FolderController::class . '::listFolders',
    ],
];
