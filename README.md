# Multi File Upload

TYPO3 extension for multi-file upload in the Form Framework.

## Features

- Multi-image upload form element for TYPO3 Form Framework
- Preview gallery with lightbox support
- Delete functionality for uploaded files
- Email attachment support for multiple files
- Database storage with FAL file references (sys_file_reference)
- Bootstrap 5 compatible styling
- German and English localization

## Requirements

- TYPO3 13.4 LTS
- PHP 8.2+
- EXT:form (TYPO3 Form Framework)

## Installation

```bash
composer require brezo-it/multi-file-upload
```

## Usage

### Form Editor

1. Open the TYPO3 Form Editor
2. Add a new element "Multi Image Upload" from the "Custom" group
3. Configure allowed MIME types and upload folder as needed

### YAML Configuration

```yaml
renderables:
  - identifier: images
    type: MultiImageUpload
    label: 'Upload Images'
    properties:
      saveToFileMount: '1:/user_upload/'
      allowedMimeTypes:
        - 'image/jpeg'
        - 'image/png'
        - 'image/bmp'
```

### Email Finisher

Two pre-configured email finishers with attachment support are available in the Form Editor:

- **Multi-file email to receiver** - Send to site administrator
- **Multi-file email to sender** - Send confirmation to form submitter

For YAML configuration, use the pre-configured finisher identifiers:

```yaml
finishers:
  - identifier: MultiFileEmailToReceiver
    options:
      subject: 'New submission'
      recipients:
        admin@example.com: 'Admin'
      senderAddress: 'noreply@example.com'
```

Or override an existing email finisher with the custom implementation class:

```yaml
finishers:
  - identifier: EmailToReceiver
    options:
      implementationClassName: BrezoIt\MultiFileUpload\Form\Finishers\MultiFileEmailFinisher
      subject: 'New submission'
      recipients:
        admin@example.com: 'Admin'
      senderAddress: 'noreply@example.com'
      attachUploads: true
```

### Database Finisher (with FAL support)

The standard TYPO3 `SaveToDatabase` finisher only stores file UIDs, not proper FAL references.
This extension provides `AttachFilesToRecord` which creates `sys_file_reference` records,
making uploaded images visible in the TYPO3 backend.

**How it works:**

1. Use the core `SaveToDatabase` finisher to create the record (without file fields)
2. Use `AttachFilesToRecord` to attach files via `sys_file_reference` records
3. The finisher reads the record UID from `{SaveToDatabase.insertedUids.0}`
4. Images are immediately visible and editable in the TYPO3 backend

**Example: Classified Ads / Marketplace Form**

```yaml
type: Form
identifier: classified-ad
label: 'Post a Classified Ad'
prototypeName: standard

renderables:
  - type: Page
    identifier: page-1
    label: 'Your Ad'
    renderables:
      - type: Text
        identifier: title
        label: 'Ad Title'
        validators:
          - identifier: NotEmpty
      - type: Textarea
        identifier: description
        label: 'Description'
      - type: MultiImageUpload
        identifier: images
        label: 'Photos (max. 5)'
        properties:
          saveToFileMount: '1:/user_upload/'
          allowedMimeTypes:
            - 'image/jpeg'
            - 'image/png'

finishers:
  # 1. Core finisher creates the record
  - identifier: SaveToDatabase
    options:
      table: 'tx_myext_domain_model_classifiedad'
      databaseColumnMappings:
        pid:
          value: 1
        hidden:
          value: 1
      elements:
        title:
          mapOnDatabaseColumn: title
        description:
          mapOnDatabaseColumn: description
        # Note: Do NOT include file fields here!

  # 2. AttachFilesToRecord creates sys_file_reference entries
  - identifier: AttachFilesToRecord
    options:
      table: 'tx_myext_domain_model_classifiedad'
      recordUid: '{SaveToDatabase.insertedUids.0}'
      storagePid: 1
      elements:
        images:
          mapOnDatabaseColumn: images

  - identifier: MultiFileEmailToReceiver
    options:
      subject: 'New classified ad submitted'
      recipients:
        admin@example.com: 'Admin'
      senderAddress: 'noreply@example.com'
  - identifier: Confirmation
    options:
      message: 'Thank you! Your ad will be reviewed and published soon.'
```

**AttachFilesToRecord Options:**

| Option | Type | Description |
|--------|------|-------------|
| `table` | string | Target database table |
| `recordUid` | string | UID of the record (use `{SaveToDatabase.insertedUids.0}`) |
| `storagePid` | int | Page ID for sys_file_reference records |
| `elements` | array | Mapping of form elements to database columns |

**TCA configuration for the images field:**

```php
'images' => [
    'label' => 'Images',
    'config' => [
        'type' => 'file',
        'allowed' => 'jpg,jpeg,png',
        'maxitems' => 5,
    ],
],
```

## Configuration Options

### Form Element Properties

| Property | Type | Default | Description |
|----------|------|---------|-------------|
| `saveToFileMount` | string | `1:/user_upload/` | FAL storage path for uploads |
| `allowedMimeTypes` | array | `image/jpeg, image/png, image/bmp` | Allowed file types |
| `imageMaxWidth` | int | `400` | Max width for preview images |
| `imageMaxHeight` | int | `400` | Max height for preview images |
| `imageLinkMaxWidth` | int | `1200` | Max width for lightbox images |

### Rendering Options (CSS Classes)

| Option | Default | Description |
|--------|---------|-------------|
| `previewListClass` | `row g-3` | Container class for image grid |
| `previewItemClass` | `col-md-4 col-lg-3 mb-3` | Class for each image item |
| `previewImageWrapperClass` | `card` | Wrapper class for image card |
| `deleteWrapperClass` | `form-check card-footer` | Class for delete checkbox wrapper |

## File Structure

```
Classes/
  Domain/Model/
    MultiFile.php                              # ObjectStorage wrapper for multi-files
  Form/
    Elements/MultiImageUpload.php              # Form element definition
    Finishers/
      AttachFilesToRecordFinisher.php          # Attach files to database record
      MultiFileEmailFinisher.php               # Email finisher with attachments
  Mvc/Property/
    MultiFilePropertyMappingConfiguration.php  # Property mapping config (hooks)
    TypeConverter/
      MultiUploadedFileReferenceConverter.php  # File upload converter
  ViewHelpers/Form/
    MultiUploadedResourceViewHelper.php        # File input rendering
    MultiUploadDeleteCheckboxViewHelper.php    # Delete checkbox

Configuration/
  Icons.php                 # Icon registration
  JavaScriptModules.php     # Form editor JS
  Services.yaml             # DI configuration
  Yaml/
    FormSetup.yaml          # Form framework setup
    FormElements/           # Form element YAML configs
    Finishers/              # Finisher YAML configs

Resources/
  Private/
    Language/               # Translations (en, de)
    Partials/Form/          # Fluid templates
    Templates/Finishers/    # Email templates
  Public/
    Css/multi-upload.css    # Styles
    Icons/Extension.svg     # Extension icon
    JavaScript/             # Form editor JS
```

## Customization

### Custom Styling

The extension's CSS is automatically loaded via `<f:asset.css>` when the form element is rendered.

To override styles, add your own CSS with higher specificity or use the Asset ViewHelper in your template:

```html
<f:asset.css identifier="multiUploadCustom" href="EXT:your_extension/Resources/Public/Css/multi-upload-custom.css" priority="true" />
```

Or include it globally via TypoScript (loaded on all pages):

```typoscript
page.includeCSS {
    multiUploadCustom = EXT:your_extension/Resources/Public/Css/multi-upload-custom.css
}
```

### Custom Templates

Override templates via TypoScript:

```typoscript
plugin.tx_form.settings.yamlConfigurations {
    100 = EXT:your_extension/Configuration/Yaml/CustomFormSetup.yaml
}
```

## Author

Maik Preuss

## License

GPL-2.0-or-later
