<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Mvc\Property\TypeConverter;

use BrezoIt\MultiFileUpload\Mvc\Property\UploadDeleteRequest;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\UploadedFileReferenceConverter;

/**
 * Extends the core UploadedFileReferenceConverter with delete support.
 *
 * If the parsed body contains "<property>__delete[<fileUid>]=1" matching the
 * resubmitted resource pointer, the converter returns null instead of
 * recreating the FileReference — effectively removing the previously
 * uploaded file from the form value.
 */
final class SingleUploadedFileReferenceConverter extends UploadedFileReferenceConverter
{
    public const OPTION_PROPERTY = 'property';

    public function convertFrom(
        $source,
        $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ) {
        $source = $this->normalizeMergedUpload($source);

        if ($this->isMarkedForDeletion($source, $configuration)) {
            return null;
        }

        return parent::convertFrom($source, $targetType, $convertedChildProperties, $configuration);
    }

    /**
     * When a form field is resubmitted with both a previous upload (submittedFile.resourcePointer
     * in the parsed body) and a new file upload (UploadedFile in $_FILES), Extbase's
     * RequestBuilder runs array_merge_recursive on them. This produces a broken array where
     * the UploadedFile's protected properties become mangled keys like "\0*\0error" and the
     * "error" key the converter expects is missing — causing the core converter to silently
     * fall through to the restore-from-submittedFile branch.
     *
     * Detect that shape and rebuild a clean upload-info array so the new upload wins.
     */
    private function normalizeMergedUpload(mixed $source): mixed
    {
        if (!is_array($source) || !array_key_exists("\0*\0error", $source)) {
            return $source;
        }

        return [
            'name' => $source["\0*\0clientFilename"] ?? '',
            'type' => $source["\0*\0clientMediaType"] ?? '',
            'tmp_name' => $source["\0*\0file"] ?? '',
            'error' => (int)$source["\0*\0error"],
            'size' => (int)($source["\0*\0size"] ?? 0),
        ];
    }

    private function isMarkedForDeletion(
        mixed $source,
        ?PropertyMappingConfigurationInterface $configuration
    ): bool {
        if (!is_array($source)) {
            return false;
        }

        // A new upload always wins — the delete checkbox only matters
        // when keeping the previously uploaded file would be the default.
        if (isset($source['error']) && $source['error'] === \UPLOAD_ERR_OK) {
            return false;
        }

        if (!isset($source['submittedFile']['resourcePointer'])) {
            return false;
        }

        $propertyName = (string)($configuration?->getConfigurationValue(self::class, self::OPTION_PROPERTY) ?? '');

        return UploadDeleteRequest::getMarkedFileUids($propertyName) !== [];
    }
}