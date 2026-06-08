<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Mvc\Property;

use Psr\Http\Message\ServerRequestInterface;

/**
 * Reads file delete flags from the current request body.
 *
 * Delete flags are submitted by UploadDeleteCheckboxViewHelper under the key
 * "<propertyName>__delete[<fileUid>]" with value "1" meaning "delete this file".
 *
 * Shared by MultiUploadedFileReferenceConverter and SingleUploadedFileReferenceConverter.
 */
final class UploadDeleteRequest
{
    public const SUFFIX = '__delete';

    /**
     * Returns the set of file UIDs marked for deletion for the given property.
     *
     * @return array<int, true>
     */
    public static function getMarkedFileUids(string $propertyName): array
    {
        $request = $GLOBALS['TYPO3_REQUEST'] ?? null;
        if (!$request instanceof ServerRequestInterface) {
            return [];
        }

        $body = (array)$request->getParsedBody();
        if ($propertyName === '') {
            $propertyName = self::detectPropertyName($body);
        }

        if ($propertyName === '') {
            return [];
        }

        return self::parseFlags($body[$propertyName . self::SUFFIX] ?? []);
    }

    /**
     * Auto-detect the form property name from POST keys ending in the delete suffix.
     * Returns the bare property name only when exactly one candidate exists.
     */
    private static function detectPropertyName(array $body): string
    {
        $candidates = array_filter(
            array_keys($body),
            static fn($key) => is_string($key) && str_ends_with($key, self::SUFFIX)
        );

        if (count($candidates) === 1) {
            $firstCandidate = reset($candidates);
            return substr($firstCandidate, 0, -strlen(self::SUFFIX));
        }

        return '';
    }

    /**
     * @return array<int, true>
     */
    private static function parseFlags(mixed $deleteMap): array
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