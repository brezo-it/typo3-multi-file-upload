<?php
declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\ViewHelpers\Form;

use TYPO3\CMS\Core\Crypto\HashService;
use TYPO3\CMS\Extbase\Domain\Model\FileReference;
use TYPO3\CMS\Extbase\Property\PropertyMapper;
use TYPO3\CMS\Fluid\ViewHelpers\Form\AbstractFormFieldViewHelper;
use TYPO3\CMS\Form\Security\HashScope;

/**
 * Multi-file upload field that shows previously uploaded files
 *
 * Usage:
 * <sp:form.multiUploadedResource property="images" as="existingImages">
 *     <f:for each="{existingImages}" as="image">
 *         <f:image image="{image}" />
 *     </f:for>
 * </sp:form.multiUploadedResource>
 */
final class MultiUploadedResourceViewHelper extends AbstractFormFieldViewHelper
{
    protected $tagName = 'input';

    protected HashService $hashService;
    protected PropertyMapper $propertyMapper;

    public function injectHashService(HashService $hashService): void
    {
        $this->hashService = $hashService;
    }

    public function injectPropertyMapper(PropertyMapper $propertyMapper): void
    {
        $this->propertyMapper = $propertyMapper;
    }

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('as', 'string', 'Template variable name for existing files', false, 'files');
        $this->registerArgument('accept', 'array', 'Accepted MIME types', false, []);
        $this->registerArgument('errorClass', 'string', 'CSS class for errors', false, 'f3-form-error');
    }

    public function render(): string
    {
        $name = $this->getName();
        $resources = $this->getUploadedResources();

        $output = '';

        // Render hidden fields for existing files
        if (!empty($resources)) {
            $output .= $this->renderResourcePointers($resources);

            // Make files available in template
            $as = $this->arguments['as'];
            if (!empty($as)) {
                $this->templateVariableContainer->add($as, $resources);
                $output .= $this->renderChildren();
                $this->templateVariableContainer->remove($as);
            }
        }

        // Register field names for CSRF token - file upload fields
        // We register the array itself, not individual sub-fields
        $this->registerFieldNameForFormTokenGeneration($name);

        // Render file input
        $output .= $this->renderFileInput();

        return $output;
    }

    /**
     * Render hidden fields for existing file resource pointers
     */
    private function renderResourcePointers(array $resources): string
    {
        $name = $this->getName();
        $baseId = $this->additionalArguments['id'] ?? '';
        $output = '';

        foreach ($resources as $index => $resource) {
            $resourcePointerValue = $this->getResourcePointerValue($resource);
            $hashedValue = $this->hashService->appendHmac(
                (string)$resourcePointerValue,
                HashScope::ResourcePointer->prefix()
            );

            $id = $baseId !== '' ? sprintf(' id="%s-file-reference-%d"', htmlspecialchars($baseId), $index) : '';

            $output .= sprintf(
                '<input type="hidden" name="%s[][submittedFile][resourcePointer]" value="%s"%s />',
                htmlspecialchars($name),
                htmlspecialchars($hashedValue),
                $id
            );
        }

        return $output;
    }

    /**
     * Get resource pointer value for a file reference
     */
    private function getResourcePointerValue(FileReference $resource): string
    {
        $resourcePointerValue = $resource->getUid();

        if ($resourcePointerValue === null) {
            // Newly created file reference (not persisted yet)
            // Use file UID prefixed with "file:"
            $resourcePointerValue = 'file:' . $resource->getOriginalResource()->getOriginalFile()->getUid();
        }

        return (string)$resourcePointerValue;
    }

    /**
     * Render the file input field
     */
    private function renderFileInput(): string
    {
        $accept = $this->arguments['accept'];
        if (!empty($accept)) {
            $this->tag->addAttribute('accept', implode(',', $accept));
        }

        $this->tag->addAttribute('type', 'file');
        $this->tag->addAttribute('name', $this->getName() . '[]');
        $this->tag->addAttribute('multiple', 'multiple');

        $this->setErrorClassAttribute();

        return $this->tag->render();
    }

    /**
     * Get previously uploaded resources
     *
     * @return FileReference[]
     */
    protected function getUploadedResources(): array
    {
        if ($this->getMappingResultsForProperty()->hasErrors()) {
            return [];
        }

        $value = $this->getValueAttribute();

        // Handle different value types
        if ($value instanceof FileReference) {
            return [$value];
        }

        if (is_iterable($value)) {
            return $this->convertIterableToFileReferences($value);
        }

        // Try to convert single value
        $converted = $this->propertyMapper->convert($value, FileReference::class);
        return $converted instanceof FileReference ? [$converted] : [];
    }

    /**
     * Convert iterable value to FileReference array
     *
     * @return FileReference[]
     */
    private function convertIterableToFileReferences(iterable $value): array
    {
        $result = [];

        foreach ($value as $item) {
            if ($item instanceof FileReference) {
                $result[] = $item;
                continue;
            }

            $converted = $this->propertyMapper->convert($item, FileReference::class);
            if ($converted instanceof FileReference) {
                $result[] = $converted;
            }
        }

        return $result;
    }
}
