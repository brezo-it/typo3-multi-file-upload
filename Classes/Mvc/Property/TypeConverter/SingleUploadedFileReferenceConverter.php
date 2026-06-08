<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Mvc\Property\TypeConverter;

use BrezoIt\MultiFileUpload\Mvc\Property\UploadDeleteRequest;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\UploadedFileInterface;
use TYPO3\CMS\Core\Http\UploadedFile;
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
        $propertyName = (string)($configuration?->getConfigurationValue(self::class, self::OPTION_PROPERTY) ?? '');

        // When a form field is resubmitted with both a previous upload
        // (submittedFile.resourcePointer in the parsed body) and a new
        // file upload (UploadedFile in $_FILES), Extbase's RequestBuilder
        // runs array_merge_recursive on them — producing a structure where
        // the core converter no longer recognizes the new upload. In that
        // case, fetch the real UploadedFile from the request and pass it
        // directly: parent::convertFrom handles UploadedFile natively.
        $newUpload = $this->findUploadedFile($propertyName);
        if ($newUpload !== null) {
            return parent::convertFrom($newUpload, $targetType, $convertedChildProperties, $configuration);
        }

        if ($this->isMarkedForDeletion($source, $propertyName)) {
            return null;
        }

        return parent::convertFrom($source, $targetType, $convertedChildProperties, $configuration);
    }

    /**
     * Locate an UploadedFile in the request matching the given property name.
     * Returns null when no successful upload exists for the property.
     */
    private function findUploadedFile(string $propertyName): ?UploadedFile
    {
        if ($propertyName === '') {
            return null;
        }

        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return null;
        }

        foreach ($this->iterateUploadedFiles($request->getUploadedFiles()) as $path => $file) {
            if ($path !== $propertyName) {
                continue;
            }
            if ($file instanceof UploadedFile && $file->getError() === \UPLOAD_ERR_OK) {
                return $file;
            }
        }

        return null;
    }

    /**
     * Recursively yields UploadedFiles from the PSR-7 uploaded-files tree,
     * keyed by the final path segment (which equals the form field identifier).
     *
     * @param array<mixed> $files
     * @return \Generator<string, UploadedFileInterface>
     */
    private function iterateUploadedFiles(array $files): \Generator
    {
        foreach ($files as $key => $value) {
            if ($value instanceof UploadedFileInterface) {
                yield (string)$key => $value;
            } elseif (is_array($value)) {
                yield from $this->iterateUploadedFiles($value);
            }
        }
    }

    private function isMarkedForDeletion(mixed $source, string $propertyName): bool
    {
        if (!is_array($source) || !isset($source['submittedFile']['resourcePointer'])) {
            return false;
        }

        return UploadDeleteRequest::getMarkedFileUids($propertyName) !== [];
    }
}