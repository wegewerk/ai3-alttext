.. include:: /Includes.rst.txt

.. _usage:

=====
Usage
=====

.. _usage-backend-module:

Backend module
==============

The extension adds the :guilabel:`AI3 metadata` module to the
:guilabel:`File` section of the TYPO3 backend. The module has two
sub-views, accessible via the button bar at the top:

- :guilabel:`File Metadata` — work with images in a specific folder
- :guilabel:`Folders` — manage suggestions across the folder tree

.. _usage-file-metadata:

File metadata view
==================

Open :guilabel:`File > AI3 metadata` and select a folder from the
folder tree on the left. The main area shows a table of all images in
that folder with the following columns:

.. list-table::
    :header-rows: 1
    :widths: 20 80

    * - Column
      - Description
    * - :guilabel:`preview`
      - Thumbnail of the image
    * - :guilabel:`Title / File`
      - File title and file name
    * - :guilabel:`alternative Text`
      - Current alt text stored in the file metadata record
    * - :guilabel:`usages`
      - Number of content elements that reference this file
    * - :guilabel:`actions`
      - Per-file action buttons

.. _usage-per-file-actions:

Per-file actions
----------------

Each image row provides the following actions:

.. list-table::
    :header-rows: 1
    :widths: 30 70

    * - Action
      - Description
    * - :guilabel:`generate alt text with AI`
      - Sends the image to the ZAK-AI API and stores the result as an
        AI suggestion (does not overwrite the existing alt text).
    * - :guilabel:`AI Suggestion` badge
      - Shown when a suggestion is waiting for review.
    * - :guilabel:`approve`
      - Copies the AI suggestion into the ``alternative`` field of the
        file metadata record.
    * - :guilabel:`edit suggestion`
      - Opens an inline editor to modify the suggestion before
        approving it.
    * - :guilabel:`edit metadata`
      - Opens the full TYPO3 file metadata edit form.
    * - :guilabel:`add alt-text manually`
      - Opens an inline text field to enter an alt text without using
        the AI.

.. _usage-bulk-generation:

Bulk generation
---------------

Use the selection checkboxes to mark multiple images, then click
:guilabel:`Generate Alt-Texts with AI` to queue all selected images
for generation in one operation. The following quick-select helpers
are available:

- :guilabel:`select all Images without alt text`
- :guilabel:`select all used Images without alt text`

After generation completes, click :guilabel:`approve all` to accept
all pending suggestions for the current folder at once.

.. _usage-folder-view:

Folder view
===========

Switch to the :guilabel:`Folders` sub-view via the button bar. The
view lists all sub-folders of the current storage root together with
the number of pending AI suggestions in each folder.

Click :guilabel:`Accept %cntGens Suggestions in %cntFolder Subfolders`
to approve all pending suggestions recursively across the entire folder
tree. A flash message confirms how many files were updated.

.. _usage-formengine-control:

FormEngine field control
========================

When editing a file metadata record directly
(:guilabel:`File > Filelist > Edit metadata`), an additional button
:guilabel:`Add alttext generation to batch` appears next to the
:guilabel:`Alternative Text` field. Clicking it queues the current
image for alt text generation without leaving the edit form.

The button shows the current generation state:

- **No indicator** — no generation task exists yet
- **In progress** — a generation task is currently running
- **AI generated** — a suggestion is available and waiting for review
- **Approved** — the suggestion has already been accepted
