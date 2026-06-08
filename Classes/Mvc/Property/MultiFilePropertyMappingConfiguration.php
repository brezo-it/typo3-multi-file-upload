<?php

declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\Mvc\Property;

use BrezoIt\MultiFileUpload\Form\Elements\MultiFileUpload;
use BrezoIt\MultiFileUpload\Mvc\Property\TypeConverter\MultiUploadedFileReferenceConverter;
use BrezoIt\MultiFileUpload\Mvc\Property\TypeConverter\SingleUploadedFileReferenceConverter;
use TYPO3\CMS\Core\Resource\ResourceFactory;
use TYPO3\CMS\Core\Utility\GeneralUtility;
use TYPO3\CMS\Core\Utility\PathUtility;
use TYPO3\CMS\Form\Domain\Model\FormElements\FileUpload;
use TYPO3\CMS\Form\Domain\Model\Renderable\RenderableInterface;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime;
use TYPO3\CMS\Form\Domain\Runtime\FormRuntime\Lifecycle\AfterFormStateInitializedInterface;

/**
 * Hook listener for configuring MultiFile property mapping.
 *
 * This class replaces the XCLASS override of the core PropertyMappingConfiguration
 * by using the standard TYPO3 Form hooks:
 * - afterBuildingFinished: Sets upload folder and property name options
 * - afterFormStateInitialized: Sets upload seed for session-based subfolder
 */
final class MultiFilePropertyMappingConfiguration implements AfterFormStateInitializedInterface
{
    /**
     * Called after the form definition is built.
     * Configures the TypeConverter options for MultiFileUpload and core FileUpload elements.
     */
    public function afterBuildingFinished(RenderableInterface $renderable): void
    {
        if ($renderable instanceof MultiFileUpload) {
            $this->configureMultiUpload($renderable);
            return;
        }

        // Core FileUpload (incl. ImageUpload): swap in our delete-aware converter
        if ($renderable instanceof FileUpload) {
            $this->configureSingleUpload($renderable);
        }
    }

    private function configureMultiUpload(MultiFileUpload $renderable): void
    {
        $propertyMappingConfiguration = $renderable->getRootForm()
            ->getProcessingRule($renderable->getIdentifier())
            ->getPropertyMappingConfiguration();
        $properties = $renderable->getProperties();

        $saveToFileMount = (string)($properties['saveToFileMount'] ?? '');
        if ($this->checkSaveFileMountAccess($saveToFileMount)) {
            $propertyMappingConfiguration->setTypeConverterOption(
                MultiUploadedFileReferenceConverter::class,
                MultiUploadedFileReferenceConverter::OPTION_UPLOAD_FOLDER,
                $saveToFileMount
            );
        }

        $propertyMappingConfiguration->setTypeConverterOption(
            MultiUploadedFileReferenceConverter::class,
            MultiUploadedFileReferenceConverter::OPTION_PROPERTY,
            $renderable->getIdentifier()
        );
    }

    private function configureSingleUpload(FileUpload $renderable): void
    {
        $propertyMappingConfiguration = $renderable->getRootForm()
            ->getProcessingRule($renderable->getIdentifier())
            ->getPropertyMappingConfiguration();

        // Replace the core converter with our delete-aware subclass.
        // Other options (uploadFolder, uploadSeed, validators) are read by the
        // parent class under the core class key and need no remapping.
        $propertyMappingConfiguration->setTypeConverter(
            GeneralUtility::makeInstance(SingleUploadedFileReferenceConverter::class)
        );
        $propertyMappingConfiguration->setTypeConverterOption(
            SingleUploadedFileReferenceConverter::class,
            SingleUploadedFileReferenceConverter::OPTION_PROPERTY,
            $renderable->getIdentifier()
        );
    }

    /**
     * Called after the form state is initialized at runtime.
     * Sets the upload seed for session-based subfolder creation.
     */
    public function afterFormStateInitialized(FormRuntime $formRuntime): void
    {
        if (!$formRuntime->canProcessFormSubmission()) {
            return;
        }

        $formSessionIdentifier = $formRuntime->getFormSession()?->getIdentifier();
        if (empty($formSessionIdentifier)) {
            return;
        }

        foreach ($formRuntime->getFormDefinition()->getRenderablesRecursively() as $renderable) {
            if (!$renderable instanceof MultiFileUpload) {
                continue;
            }

            $formRuntime->getFormDefinition()
                ->getProcessingRule($renderable->getIdentifier())
                ->getPropertyMappingConfiguration()
                ->setTypeConverterOption(
                    MultiUploadedFileReferenceConverter::class,
                    MultiUploadedFileReferenceConverter::OPTION_UPLOAD_SEED,
                    $formSessionIdentifier
                );
        }
    }

    /**
     * Check if the save file mount is accessible.
     */
    private function checkSaveFileMountAccess(string $saveToFileMountIdentifier): bool
    {
        if (empty($saveToFileMountIdentifier)) {
            return false;
        }

        if (PathUtility::isExtensionPath($saveToFileMountIdentifier)) {
            return false;
        }

        $resourceFactory = GeneralUtility::makeInstance(ResourceFactory::class);

        try {
            $resourceFactory->getFolderObjectFromCombinedIdentifier($saveToFileMountIdentifier);
            return true;
        } catch (\InvalidArgumentException) {
            return false;
        }
    }
}
