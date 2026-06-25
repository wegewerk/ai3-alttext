.. include:: /Includes.rst.txt

.. |vendor| replace:: Wegewerk GmbH

.. _start:

============
Ai3 Alt Text
============

:Extension key:
    |extension_key|

:Package name:
    |composer_name|

:Version:
    |release|

:Language:
    en

:Author:
    |vendor|

:License:
    This document is published under the
    `Creative Commons BY 4.0 <https://creativecommons.org/licenses/by/4.0/>`__
    license.

:Rendered:
    |today|

----

**Ai3 Alt Text** is a TYPO3 backend module that generates accessible
alternative texts for images using the **ZAK-AI REST API**. It is part
of the *Ai3 Suite* and requires :composer:`wegewerk/ai3_core`.

Key features:

- **Backend module** under :guilabel:`File` for browsing folders and
  files in the TYPO3 file storage
- **Batch generation** — select multiple images and generate alt texts
  in one operation
- **Review & approve workflow** — AI suggestions are stored separately
  and must be approved before they are written to file metadata
- **FormEngine field control** — add a single file to the generation
  batch directly from the file metadata edit form
- **Recursive folder actions** — accept all AI suggestions across an
  entire folder tree in one click

This package supports TYPO3 |min_typo3|, 13.4, and 14.0.

.. toctree::
    :maxdepth: 2
    :titlesonly:
    :hidden:

    Introduction/Index
    Installation/Index
    Configuration/Index
    Usage/Index
    Developer/Index
    Changelog/Index
