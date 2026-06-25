.. include:: /Includes.rst.txt

.. _introduction:

============
Introduction
============

.. _what-it-does:

What does it do?
================

**Ai3 Alt Text** (|extension_key|) is a TYPO3 extension that automates
the creation of accessible alternative texts for images stored in the
TYPO3 file storage. It communicates with the **ZAK-AI REST API** —
provided by :composer:`wegewerk/ai3_core` — to generate descriptive,
language-aware alt texts using a vision-capable AI model.

The extension adds a dedicated backend module under
:guilabel:`File > AI3 metadata` with two views:

File metadata view
    Lists all images in a selected folder. For each image the current
    alt text, AI-generated suggestion, usage count, and available
    actions are shown. Editors can generate alt texts individually or
    in bulk, review suggestions, and approve or edit them before they
    are saved to the file metadata record.

Folder view
    Shows the sub-folder tree of the file storage. Editors can accept
    all pending AI suggestions across an entire folder hierarchy with a
    single click.

In addition, a **FormEngine field control** button is injected into the
:guilabel:`Alternative Text` field of the file metadata edit form,
allowing editors to queue a single image for alt text generation without
leaving the record editor.

.. _screenshots:

Screenshots
===========

.. note::

    Screenshots will be added in a future release.

.. _requirements:

Requirements
============

- TYPO3 CMS |min_typo3|, 13.4, or 14.0
- :composer:`wegewerk/ai3_core` (installed automatically as a
  Composer dependency)
- A valid ZAK-AI account with ``ZAKAI_API_KEY`` and ``ZAKAI_SECRET``
  environment variables set on the server
