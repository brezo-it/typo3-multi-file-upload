<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Mvc\Property\TypeConverter;

use BrezoIt\MultiFileUpload\Domain\Model\MultiFile;
use Psr\Http\Message\ServerRequestInterface;
use TYPO3\CMS\Core\Http\UploadedFile;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfiguration;
use TYPO3\CMS\Extbase\Property\PropertyMappingConfigurationInterface;
use TYPO3\CMS\Extbase\Property\TypeConverter\AbstractTypeConverter;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\UploadedFileReferenceConverter;

/**
 * Converts an array of UploadedFile objects (multi upload) into a MultiFile collection.
 */
final class MultiUploadedFileReferenceConverter extends AbstractTypeConverter
{
    protected array $sourceTypes = ['array'];
    protected string $targetType = MultiFile::class;
    protected int $priority = 10;

    public const OPTION_UPLOAD_FOLDER = 'uploadFolder';
    public const OPTION_UPLOAD_SEED = 'uploadSeed';
    public const OPTION_PROPERTY = 'property';

    public function convertFrom(
        $source,
        string $targetType,
        array $convertedChildProperties = [],
        ?PropertyMappingConfigurationInterface $configuration = null
    ): ?MultiFile {
        if (!is_array($source)) {
            return null;
        }

        $storage = $this->convertFiles($source, $configuration);
        $this->applyDeletions($storage, $configuration);

        return $storage;
    }

    /**
     * Convert uploaded files to FileReference objects
     */
    private function convertFiles(array $source, ?PropertyMappingConfigurationInterface $configuration): MultiFile
    {
        $coreConverter = GeneralUtility::makeInstance(UploadedFileReferenceConverter::class);
        $coreConfiguration = $this->createCoreConfiguration($configuration);

        $storage = new MultiFile();

        foreach ($source as $item) {
            if ($item === null || (!$item instanceof UploadedFile && !is_array($item))) {
                continue;
            }

            $converted = $coreConverter->convertFrom($item, FileReference::class, [], $coreConfiguration);
            if ($converted instanceof FileReference) {
                $storage->attach($converted);
            }
        }

        return $storage;
    }

    /**
     * Create configuration for core UploadedFileReferenceConverter
     */
    private function createCoreConfiguration(?PropertyMappingConfigurationInterface $configuration): ?PropertyMappingConfigurationInterface
    {
        if (!$configuration instanceof PropertyMappingConfiguration) {
            return $configuration;
        }

        $uploadFolder = (string)($configuration->getConfigurationValue(self::class, self::OPTION_UPLOAD_FOLDER) ?? '');
        $uploadSeed = (string)($configuration->getConfigurationValue(self::class, self::OPTION_UPLOAD_SEED) ?? '');

        if ($uploadFolder !== '') {
            $configuration->setTypeConverterOption(
                UploadedFileReferenceConverter::class,
                UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_FOLDER,
                $uploadFolder
            );
        }

        if ($uploadSeed !== '') {
            $configuration->setTypeConverterOption(
                UploadedFileReferenceConverter::class,
                UploadedFileReferenceConverter::CONFIGURATION_UPLOAD_SEED,
                $uploadSeed
            );
        }

        return $configuration;
    }

    /**
     * Remove files marked for deletion
     */
    private function applyDeletions(MultiFile $storage, ?PropertyMappingConfigurationInterface $configuration): void
    {
        $propertyName = (string)($configuration?->getConfigurationValue(self::class, self::OPTION_PROPERTY) ?? '');
        $deleteUids = $this->getDeleteFileUids($propertyName);

        if ($deleteUids === []) {
            return;
        }

        $toRemove = [];
        foreach ($storage as $ref) {
            // All references are PseudoFileReference objects
            $uid = (int)$ref->getOriginalResource()->getOriginalFile()->getUid();
            if ($uid > 0 && isset($deleteUids[$uid])) {
                $toRemove[] = $ref;
            }
        }

        foreach ($toRemove as $ref) {
            $storage->detach($ref);
        }
    }

    /**
     * Get file UIDs marked for deletion from POST data
     *
     * @return array<int,true>
     */
    private function getDeleteFileUids(string $propertyName): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return [];
        }

        $body = (array)$request->getParsedBody();

        // Auto-detect property name if not provided
        if ($propertyName === '') {
            $propertyName = $this->detectPropertyName($body);
        }

        if ($propertyName === '') {
            return [];
        }

        return $this->parseDeleteFlags($body[$propertyName . '__delete'] ?? []);
    }

    /**
     * Auto-detect property name from POST data
     */
    private function detectPropertyName(array $body): string
    {
        $candidates = array_filter(
            array_keys($body),
            fn($key) => is_string($key) && str_ends_with($key, '__delete')
        );

        if (count($candidates) === 1) {
            $firstCandidate = reset($candidates);
            return substr($firstCandidate, 0, -strlen('__delete'));
        }

        return '';
    }

    /**
     * Parse delete flags into UID map
     *
     * @return array<int,true>
     */
    private function parseDeleteFlags(mixed $deleteMap): array
    {
        if (!is_array($deleteMap)) {
            return [];
        }

        $uids = [];
        foreach ($deleteMap as $fileUid => $flag) {
            if ((int)$flag === 1) {
                $uid = (int)$fileUid;
                if ($uid > 0) {
                    $uids[$uid] = true;
                }
            }
        }

        return $uids;
    }
}
