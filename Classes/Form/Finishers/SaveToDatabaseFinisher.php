<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Form\Finishers;

use BrezoIt\MultiFileUpload\Domain\Model\MultiFile;
use BrezoIt\MultiFileUpload\Form\Elements\MultiFileUpload;
use BrezoIt\MultiFileUpload\Form\Elements\MultiImageUpload;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Form\Domain\Finishers\SaveToDatabaseFinisher as CoreSaveToDatabaseFinisher;

/**
 * Extended SaveToDatabase finisher with MultiImageUpload FAL support.
 *
 * This finisher extends the core SaveToDatabaseFinisher to handle
 * MultiImageUpload form elements by creating proper sys_file_reference
 * records for uploaded files.
 *
 * Configuration example:
 *
 *   finishers:
 *     -
 *       identifier: MultiFileSaveToDatabase
 *       options:
 *         table: 'tx_myext_domain_model_item'
 *         databaseColumnMappings:
 *           pid:
 *             value: 1
 *         elements:
 *           title:
 *             mapOnDatabaseColumn: title
 *           images:
 *             mapOnDatabaseColumn: images
 */
class SaveToDatabaseFinisher extends CoreSaveToDatabaseFinisher
{
    protected array $fileReferenceMappings = [];

    protected function prepareData(array $elementsConfiguration, array $databaseData): array
    {
        $formValues = $this->getFormValues();

        foreach ($formValues as $elementIdentifier => $elementValue) {
            if (
                ($elementValue === null || $elementValue === '')
                && isset($elementsConfiguration[$elementIdentifier]['skipIfValueIsEmpty'])
                && $elementsConfiguration[$elementIdentifier]['skipIfValueIsEmpty'] === true
            ) {
                continue;
            }

            $element = $this->getElementByIdentifier($elementIdentifier);

            if (!isset($elementsConfiguration[$elementIdentifier]['mapOnDatabaseColumn'])) {
                continue;
            }

            $databaseColumn = $elementsConfiguration[$elementIdentifier]['mapOnDatabaseColumn'];

            // Handle MultiImageUpload and MultiFileUpload elements
            if ($element instanceof MultiImageUpload || $element instanceof MultiFileUpload) {
                $files = $this->extractFileReferences($elementValue);
                if (!empty($files)) {
                    // Store file references for later processing
                    $this->fileReferenceMappings[$databaseColumn] = $files;
                    // Set the count for the database column
                    $databaseData[$databaseColumn] = count($files);
                } else {
                    $databaseData[$databaseColumn] = 0;
                }
                continue;
            }

            // Handle single FileReference
            if ($elementValue instanceof FileReference) {
                if ($elementsConfiguration[$elementIdentifier]['saveFileIdentifierInsteadOfUid'] ?? false) {
                    $elementValue = $elementValue->getOriginalResource()->getCombinedIdentifier();
                } else {
                    $elementValue = $elementValue->getOriginalResource()->getProperty('uid_local');
                }
            } elseif (is_array($elementValue)) {
                $elementValue = implode(',', $elementValue);
            } elseif ($elementValue instanceof \DateTimeInterface) {
                $format = $elementsConfiguration[$elementIdentifier]['dateFormat'] ?? 'U';
                $elementValue = $elementValue->format($format);
            }

            $databaseData[$databaseColumn] = $elementValue;
        }

        return $databaseData;
    }

    protected function saveToDatabase(array $databaseData, string $table, int $iterationCount): void
    {
        if (empty($databaseData)) {
            return;
        }

        if ($this->parseOption('mode') === 'update') {
            $whereClause = $this->parseOption('whereClause');
            foreach ($whereClause as $columnName => $columnValue) {
                $whereClause[$columnName] = $this->parseOption('whereClause.' . $columnName);
            }
            $this->databaseConnection->update($table, $databaseData, $whereClause);
            $recordUid = (int)array_values($whereClause)[0];
        } else {
            $this->databaseConnection->insert($table, $databaseData);
            $recordUid = (int)$this->databaseConnection->lastInsertId();
            $this->finisherContext->getFinisherVariableProvider()->add(
                $this->shortFinisherIdentifier,
                'insertedUids.' . $iterationCount,
                $recordUid
            );
        }

        // Create sys_file_reference records for uploaded files
        if ($recordUid > 0 && !empty($this->fileReferenceMappings)) {
            $this->createFileReferences($table, $recordUid);
        }
    }

    /**
     * Extract file UIDs from MultiFile or iterable of FileReferences.
     */
    protected function extractFileReferences(mixed $value): array
    {
        $files = [];

        if ($value instanceof MultiFile || is_iterable($value)) {
            foreach ($value as $file) {
                $fileUid = $this->getFileUid($file);
                if ($fileUid > 0) {
                    $files[] = $fileUid;
                }
            }
        } elseif ($value instanceof FileReference) {
            $fileUid = $this->getFileUid($value);
            if ($fileUid > 0) {
                $files[] = $fileUid;
            }
        }

        return $files;
    }

    /**
     * Get the sys_file UID from a FileReference.
     */
    protected function getFileUid(mixed $file): int
    {
        if ($file instanceof FileReference) {
            $originalResource = $file->getOriginalResource();
            if ($originalResource !== null) {
                return (int)$originalResource->getProperty('uid_local');
            }
        }

        return 0;
    }

    /**
     * Create sys_file_reference records for the uploaded files.
     */
    protected function createFileReferences(string $table, int $recordUid): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_file_reference');

        $pid = (int)($this->parseOption('databaseColumnMappings.pid.value') ?? 0);

        foreach ($this->fileReferenceMappings as $fieldname => $fileUids) {
            $sorting = 0;
            foreach ($fileUids as $fileUid) {
                $connection->insert('sys_file_reference', [
                    'pid' => $pid,
                    'tstamp' => time(),
                    'crdate' => time(),
                    'uid_local' => $fileUid,
                    'uid_foreign' => $recordUid,
                    'tablenames' => $table,
                    'fieldname' => $fieldname,
                    'sorting_foreign' => $sorting++,
                ]);
            }
        }

        // Reset for next iteration
        $this->fileReferenceMappings = [];
    }
}
