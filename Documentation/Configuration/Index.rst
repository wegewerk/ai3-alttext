.. include:: /Includes.rst.txt

.. _configuration:

=============
Configuration
=============

ai3-alttext has no extension-specific settings in the TYPO3
Extension Manager. All configuration is done via environment variables
that are read by :composer:`wegewerk/ai3_core`.

.. _configuration-env:

Environment variables
=====================

The following environment variables must be set on the server before
the extension can communicate with the ZAK-AI API:

.. confval:: ZAKAI_API_KEY
    :name: ZAKAI_API_KEY
    :type: string
    :default: (empty)

    The API key issued by ZAK-AI for your account. When this variable
    is empty, all API calls will throw a ``RuntimeException``.

.. confval:: ZAKAI_SECRET
    :name: ZAKAI_SECRET
    :type: string
    :default: (empty)

    The shared secret used together with ``ZAKAI_API_KEY`` to build
    the HTTP Basic Authorization header
    (``base64(secret + ':' + apiKey)``).

Set these variables in your server environment, :file:`.env` file, or
TYPO3 :file:`config/system/additional.php`:

.. code-block:: php
    :caption: config/system/additional.php

    putenv('ZAKAI_API_KEY=your-api-key');
    putenv('ZAKAI_SECRET=your-secret');

.. _configuration-tca:

TCA override
============

The extension overrides the ``alternative`` column of the
``sys_file_metadata`` table to add the
:guilabel:`Add alttext generation to batch` field control button. No
manual TCA configuration is required.

.. _configuration-js:

JavaScript modules
==================

The extension registers its JavaScript modules under the
``@wegewerk/ai3alttext/`` namespace via
:file:`Configuration/JavaScriptModules.php`. The following modules are
available:

.. list-table::
    :header-rows: 1
    :widths: 40 60

    * - Module
      - Purpose
    * - ``@wegewerk/ai3alttext/fileList.js``
      - Renders the file list and handles bulk selection
    * - ``@wegewerk/ai3alttext/fileElement.js``
      - Handles per-file actions (generate, approve, edit)
    * - ``@wegewerk/ai3alttext/subFolders.js``
      - Renders the folder tree and recursive accept action
    * - ``@wegewerk/ai3alttext/add-to-batch.js``
      - FormEngine field control button behaviour
    * - ``@wegewerk/ai3alttext/ai3api.js``
      - Shared Ajax helper for all backend API calls
