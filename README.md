# Ai3 Alt Text — TYPO3 Extension

**Ai3 Alttext** (`ai3_alttext`) is the image alternative-text capability
of the *Ai3 Suite*. It adds a backend module and a FormEngine field control
to TYPO3 that generate accessible alt texts for images in the file storage
using the **ZAK-AI REST API**.

## Requirements

| Dependency | Version |
|---|---|
| TYPO3 CMS | `^13.4.0 \| ^14.0` |
| `wegewerk/ai3_core` | `@dev` |

## Installation

```bash
composer require wegewerk/ai3_alttext
```

`wegewerk/ai3_core` is pulled in automatically as a Composer dependency.

## Quick start

1. Set the ZAK-AI credentials as environment variables:

   ```bash
   export ZAKAI_API_KEY=<your-api-key>
   export ZAKAI_SECRET=<your-secret>
   ```

2. Open **File > AI3 metadata** in the TYPO3 backend.

3. Select a folder, choose the images to process, and click
   **Generate Alt-Texts with AI**.

4. Review the AI suggestions and click **approve** (or **approve all**)
   to write them to the file metadata records.

## Features

- **Backend module** under **File > AI3 metadata** for browsing folders
  and files in the TYPO3 file storage
- **Batch generation** - select multiple images and generate alt texts
  in one operation
- **Review & approve workflow** - AI suggestions are stored separately
  and must be approved before they are written to file metadata
- **FormEngine field control** - the extension adds a button to the file metadata
  edit form which queues a singe alt-text generation
- **Recursive folder actions** — accept all AI suggestions across an
  entire folder tree in one click

## Configuration

This Extension provides not configuration settings. Configure the ZAK-AI credentials via environment variables.
See ai3_core

## Changelog

See [CHANGELOG.md](CHANGELOG.md).
