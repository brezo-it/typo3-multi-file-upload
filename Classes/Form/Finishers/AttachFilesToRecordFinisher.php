<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Form\Finishers;

use BrezoIt\MultiFileUpload\Domain\Model\MultiFile;
use BrezoIt\MultiFileUpload\Form\Elements\MultiFileUpload;
use BrezoIt\MultiFileUpload\Form\Elements\MultiImageUpload;
use TYPO3\CMS\Core\Database\ConnectionPool;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Form\Domain\Finishers\AbstractFinisher;
use TYPO3\CMS\Form\Domain\Model\FormElements\FormElementInterface;

/**
 * Finisher to attach uploaded files to a database record.
 *
 * This finisher creates sys_file_reference records for MultiImageUpload
 * and MultiFileUpload form elements, linking them to a record created
 * by the core SaveToDatabase finisher.
 *
 * Configuration example:
 *
 *   finishers:
 *     -
 *       identifier: SaveToDatabase
 *       options:
 *         table: 'tx_myext_domain_model_item'
 *         databaseColumnMappings:
 *           pid:
 *             value: 1
 *         elements:
 *           title:
 *             mapOnDatabaseColumn: title
 *
 *     -
 *       identifier: AttachFilesToRecord
 *       options:
 *         table: 'tx_myext_domain_model_item'
 *         recordUid: '{SaveToDatabase.insertedUids.0}'
 *         storagePid: 1
 *         elements:
 *           images:
 *             mapOnDatabaseColumn: images
 *           files:
 *             mapOnDatabaseColumn: files
 */
class AttachFilesToRecordFinisher extends AbstractFinisher
{
    protected $defaultOptions = [
        'table' => '',
        'recordUid' => '',
        'storagePid' => 0,
        'elements' => [],
    ];

    protected function executeInternal(): ?string
    {
        $table = $this->parseOption('table');
        $recordUid = (int)$this->parseOption('recordUid');
        $storagePid = (int)$this->parseOption('storagePid');
        $elementsConfiguration = $this->parseOption('elements');

        if ($recordUid <= 0 || empty($table)) {
            return null;
        }

        $formValues = $this->finisherContext->getFormValues();
        $fileReferenceMappings = [];

        foreach ($elementsConfiguration as $elementIdentifier => $config) {
            $element = $this->getElementByIdentifier($elementIdentifier);

            // Only process MultiImageUpload and MultiFileUpload elements
            if (!$element instanceof MultiImageUpload && !$element instanceof MultiFileUpload) {
                continue;
            }

            $fieldname = $config['mapOnDatabaseColumn'] ?? $elementIdentifier;
            $value = $formValues[$elementIdentifier] ?? null;

            if ($value === null) {
                continue;
            }

            $fileUids = $this->extractFileReferences($value);
            if (!empty($fileUids)) {
                $fileReferenceMappings[$fieldname] = $fileUids;
            }
        }

        if (!empty($fileReferenceMappings)) {
            $this->createFileReferences($table, $recordUid, $storagePid, $fileReferenceMappings);
            $this->updateFileCount($table, $recordUid, $fileReferenceMappings);
        }

        return null;
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
    protected function createFileReferences(
        string $table,
        int $recordUid,
        int $storagePid,
        array $fileReferenceMappings
    ): void {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable('sys_file_reference');

        foreach ($fileReferenceMappings as $fieldname => $fileUids) {
            $sorting = 0;
            foreach ($fileUids as $fileUid) {
                $connection->insert('sys_file_reference', [
                    'pid' => $storagePid,
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
    }

    /**
     * Update the file count in the main record.
     */
    protected function updateFileCount(string $table, int $recordUid, array $fileReferenceMappings): void
    {
        $connection = GeneralUtility::makeInstance(ConnectionPool::class)
            ->getConnectionForTable($table);

        $updateData = [];
        foreach ($fileReferenceMappings as $fieldname => $fileUids) {
            $updateData[$fieldname] = count($fileUids);
        }

        if (!empty($updateData)) {
            $connection->update($table, $updateData, ['uid' => $recordUid]);
        }
    }

    /**
     * Returns a form element object for a given identifier.
     */
    protected function getElementByIdentifier(string $elementIdentifier): ?FormElementInterface
    {
        return $this
            ->finisherContext
            ->getFormRuntime()
            ->getFormDefinition()
            ->getElementByIdentifier($elementIdentifier);
    }
}
