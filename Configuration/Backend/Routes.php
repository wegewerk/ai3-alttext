<?php

use Wegewerk\Ai3Alttext\Controller\Ai3FileController;
use Wegewerk\Ai3Alttext\Controller\Ai3FolderController;

return [

    'ai3_file' => [
        'path' => '/ai3/file',
        'target' => Ai3FileController::class . '::handleRequest',
    ],
    'ai3_folder' => [
        'path' => '/ai3/folder',
        'target' => Ai3FolderController::class . '::handleRequest',
    ],
];
