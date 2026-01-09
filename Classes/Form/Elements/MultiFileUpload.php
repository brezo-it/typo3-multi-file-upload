<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Form\Elements;

use BrezoIt\MultiFileUpload\Domain\Model\MultiFile;
use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;

/**
 * Multi file upload form element for generic files (not just images).
 *
 * This element allows uploading multiple files of various types,
 * such as PDFs, documents, spreadsheets, etc.
 */
final class MultiFileUpload extends AbstractFormElement
{
    public function initializeFormElement(): void
    {
        // Multi upload will be converted to a MultiFile collection
        // The PropertyMapper will automatically select MultiUploadedFileReferenceConverter
        $this->setDataType(MultiFile::class);
        parent::initializeFormElement();
    }

    public function isMultiValue(): bool
    {
        return true;
    }
}