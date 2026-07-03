.. include:: /Includes.rst.txt

.. _installation:

============
Installation
============

.. _installation-composer:

Installation via Composer
=========================

The recommended way to install ai3-alttext is via Composer:

.. code-block:: bash
    :caption: Install the extension

    composer require wegewerk/ai3_alttext

This will also pull in :composer:`wegewerk/ai3_core` automatically.

.. _installation-activate:

Activate the extension
======================

After installation, activate the extension in the TYPO3 backend:

1. Open :guilabel:`Admin Tools > Extensions`
2. Find **Ai3 Alt Text** in the list
3. Click the :guilabel:`Activate` button

.. note::

    When using Composer-based TYPO3 installations the extension is
    activated automatically. No manual activation is required.

.. _installation-database:

Database update
===============

No additional database tables are created by this extension. All
generation task data is stored via :composer:`wegewerk/ai3_core`.

Run the database analyser after installation to ensure the core tables
are up to date:

:guilabel:`Admin Tools > Maintenance > Analyze Database Structure`
