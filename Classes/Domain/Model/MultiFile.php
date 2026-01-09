<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Domain\Model;

use TYPO3\CMS\Extbase\Persistence\ObjectStorage;

/**
 * Custom type for multi-file uploads.
 *
 * This class exists to provide a distinct target type for the PropertyMapper,
 * allowing the MultiUploadedFileReferenceConverter to be automatically selected
 * without requiring an XCLASS override of the core PropertyMappingConfiguration.
 *
 * @extends ObjectStorage<\TYPO3\CMS\Extbase\Domain\Model\FileReference>
 */
final class MultiFile extends ObjectStorage
{
}