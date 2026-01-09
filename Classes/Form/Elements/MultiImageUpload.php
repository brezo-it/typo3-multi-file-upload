<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Form\Elements;

use BrezoIt\MultiFileUpload\Domain\Model\MultiFile;
use TYPO3\CMS\Form\Domain\Model\FormElements\AbstractFormElement;

final class MultiImageUpload extends AbstractFormElement
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
