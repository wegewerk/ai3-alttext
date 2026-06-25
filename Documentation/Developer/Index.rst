.. include:: /Includes.rst.txt

.. _developer:

================
Developer corner
================

.. _developer-architecture:

Architecture overview
=====================

|extension_name| follows the capability pattern introduced by
:composer:`wegewerk/ai3_core`. The central pieces are:

.. list-table::
    :header-rows: 1
    :widths: 40 60

    * - Class
      - Responsibility
    * - ``ZakAiAlttext``
      - Implements ``ZakAiEndpointInterface``; resizes the image and
        calls the ``/alttexts`` REST endpoint
    * - ``AlttextCapability``
      - Registers the alt text capability under the key ``alttext``
        so the core task queue can dispatch jobs to it
    * - ``AlttextController`` (Ajax)
      - Manages the generation task lifecycle: create, poll status,
        review, and select a suggestion
    * - ``FilelistController`` (Ajax)
      - Lists files in a folder and saves approved alt texts back to
        ``sys_file_metadata``
    * - ``FolderController`` (Ajax)
      - Lists sub-folders and bulk-accepts suggestions recursively
    * - ``Ai3AlttextAddToBatch``
      - FormEngine ``AbstractNode`` that renders the batch button in
        the file metadata edit form
    * - ``AfterFormEnginePageInitializedEventListener``
      - Loads the extension language labels whenever a FormEngine page
        is initialised

.. _developer-api:

ZakAiAlttext API class
======================

``Wegewerk\Ai3Alttext\Api\ZakAiAlttext`` implements
``Wegewerk\Ai3Core\Api\ZakAiEndpointInterface`` and exposes a single
public method:

.. code-block:: php
    :caption: Classes/Api/ZakAiAlttext.php (signature)

    public function generate(
        string $imagePath,
        string $caption,
        string $language
    ): string

The method:

1. Verifies the image file exists on disk.
2. Resizes the image to a maximum of 512 × 512 px using
   ``ImageService`` and ``ImageProcessingService`` to keep API
   payload sizes small.
3. Base64-encodes the resized image and POSTs it to the
   ``/alttexts`` endpoint via ``ZakAiClient::postJson()``.
4. Returns the ``alttext`` string from the API response, or an empty
   string if the API reports an error.

.. _developer-ajax-routes:

Ajax routes
===========

All Ajax endpoints are registered in
:file:`Configuration/Backend/AjaxRoutes.php`:

.. list-table::
    :header-rows: 1
    :widths: 45 55

    * - Route identifier
      - Target
    * - ``ai3_alttext_generation_create_task``
      - ``AlttextController::addAlttextGenerationTaskAction``
    * - ``ai3_alttext_generation_review``
      - ``AlttextController::reviewAlttextAction``
    * - ``ai3_alttext_generation_select``
      - ``AlttextController::selectAlttextAction``
    * - ``ai3_alttext_generation_status``
      - ``AlttextController::checkAlttextGenerationStatusAction``
    * - ``ai3_filelist``
      - ``FilelistController::listFiles``
    * - ``ai3_filelist_save_file``
      - ``FilelistController::saveFile``
    * - ``ai3_filelist_create_task_alttext``
      - ``FilelistController::addAlttextTaskForFile``
    * - ``ai3_folders``
      - ``FolderController::listFolders``
    * - ``ai3_folders_acceptAll_recursive``
      - ``FolderController::acceptAllSuggestionsRecursive``

.. _developer-capability:

Capability registration
=======================

``AlttextCapability`` is registered as a DI-tagged service so that the
:composer:`wegewerk/ai3_core` capability registry picks it up
automatically:

.. code-block:: yaml
    :caption: Configuration/Services.yaml

    Wegewerk\Ai3Alttext\Domain\Capabilities\AlttextCapability:
        autowire: false
        arguments:
            $key: 'alttext'
            $title: 'Alt Text'
            $endpoint: '@Wegewerk\Ai3Alttext\Api\ZakAiAlttext'
        tags:
            - name: ai3.capability

.. _developer-formengine:

FormEngine field control
========================

The field control is registered as a custom FormEngine node in
:file:`ext_localconf.php`:

.. code-block:: php
    :caption: ext_localconf.php

    $GLOBALS['TYPO3_CONF_VARS']['SYS']['formEngine']['nodeRegistry'][1759225979] = [
        'nodeName' => 'ai3AlttextAddToBatch',
        'priority' => 30,
        'class' => \Wegewerk\Ai3Alttext\FormEngine\FieldControl\Ai3AlttextAddToBatch::class,
    ];

The TCA override in
:file:`Configuration/TCA/Overrides/sys_file_metadata.php` attaches
this node to the ``alternative`` field of ``sys_file_metadata`` as a
``fieldControl``.

.. _developer-event-listener:

Event listener
==============

``AfterFormEnginePageInitializedEventListener`` listens to
``AfterFormEnginePageInitializedEvent`` and injects the extension
language labels into the page renderer so that the JavaScript modules
can use them via the TYPO3 inline label API.
