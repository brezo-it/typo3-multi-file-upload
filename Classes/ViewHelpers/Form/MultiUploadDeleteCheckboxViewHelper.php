<?php
declare(strict_types=1);

namespace BrezoIt\MultiFileUpload\ViewHelpers\Form;

use TYPO3\CMS\Fluid\ViewHelpers\Form\AbstractFormFieldViewHelper;
use TYPO3\CMS\Form\Mvc\Property\TypeConverter\PseudoFileReference;

/**
 * Renders a delete checkbox for a file in a multi-upload field
 *
 * Usage:
 * <sp:form.multiUploadDeleteCheckbox property="imageupload-1" fileReference="{image}" />
 */
final class MultiUploadDeleteCheckboxViewHelper extends AbstractFormFieldViewHelper
{
    protected $tagName = 'input';

    public function initializeArguments(): void
    {
        parent::initializeArguments();
        $this->registerArgument('id', 'string', 'ID of the checkbox element');
        $this->registerArgument('class', 'string', 'CSS class for the checkbox');
        $this->registerArgument('property', 'string', 'Name of the form property', true);
        $this->registerArgument('fileReference', PseudoFileReference::class, 'The file reference object', true);
    }

    public function render(): string
    {
        $fileReference = $this->arguments['fileReference'];
        if (!$fileReference instanceof PseudoFileReference) {
            return '';
        }

        // All references are PseudoFileReference objects with guaranteed structure
        $fileUid = (int)$fileReference->getOriginalResource()->getOriginalFile()->getUid();
        if ($fileUid === 0) {
            return '';
        }

        $property = (string)$this->arguments['property'];
        $nameAttribute = $property . '__delete[' . $fileUid . ']';
        $idAttribute = !empty($this->arguments['id']) ? $this->arguments['id'] : $nameAttribute;
        $cssClass = (string)$this->arguments['class'];

        // Register field name for CSRF token
        $this->registerFieldNameForFormTokenGeneration($nameAttribute);

        // Hidden field ensures we always get a value (0 or 1)
        $output = sprintf(
            '<input type="hidden" name="%s" value="0" />',
            htmlspecialchars($nameAttribute, ENT_QUOTES)
        );

        $this->tag->addAttribute('type', 'checkbox');
        $this->tag->addAttribute('name', $nameAttribute);
        $this->tag->addAttribute('value', '1');
        $this->tag->addAttribute('id', $idAttribute);

        if ($cssClass !== '') {
            $this->tag->addAttribute('class', $cssClass);
        }

        return $output . $this->tag->render();
    }
}
