.. include:: /Includes.rst.txt

=============
Configuration
=============

Form Element Properties
=======================

The Multi Image Upload element supports the following properties:

.. csv-table::
   :header: "Property", "Type", "Default", "Description"
   :widths: 20, 10, 25, 45

   "saveToFileMount", "string", "``1:/user_upload/``", "FAL storage path for uploaded files"
   "allowedMimeTypes", "array", "``image/jpeg, image/png, image/bmp``", "List of allowed MIME types"
   "imageMaxWidth", "int", "``400``", "Maximum width for preview images (pixels)"
   "imageMaxHeight", "int", "``400``", "Maximum height for preview images (pixels)"
   "imageLinkMaxWidth", "int", "``1200``", "Maximum width for lightbox images (pixels)"

Rendering Options (CSS Classes)
===============================

Customize the appearance by overriding these CSS classes:

.. csv-table::
   :header: "Option", "Default", "Description"
   :widths: 25, 30, 45

   "previewListClass", "``row g-3``", "Container class for the image grid"
   "previewItemClass", "``col-md-4 col-lg-3 mb-3``", "Class for each image item in the grid"
   "previewImageWrapperClass", "``card``", "Wrapper class for image card"
   "deleteWrapperClass", "``form-check card-footer``", "Class for delete checkbox wrapper"
   "summaryItemClass", "``col-6 col-md-4 col-lg-3 mb-2``", "Class for items on summary page"

YAML Configuration Example
==========================

Full example with all options:

.. code-block:: yaml

   renderables:
     - identifier: images
       type: MultiImageUpload
       label: 'Upload Images'
       properties:
         saveToFileMount: '1:/user_upload/'
         allowedMimeTypes:
           - 'image/jpeg'
           - 'image/png'
           - 'image/webp'
         imageMaxWidth: 400
         imageMaxHeight: 400
         imageLinkMaxWidth: 1200
       renderingOptions:
         previewListClass: 'row g-3'
         previewItemClass: 'col-md-4 col-lg-3 mb-3'
         previewImageWrapperClass: 'card'
         deleteWrapperClass: 'form-check card-footer'

Email Finisher Configuration
============================

To send emails with file attachments, use the ``MultiFileEmailFinisher``:

.. code-block:: yaml

   finishers:
     - identifier: EmailToReceiver
       options:
         implementationClassName: BrezoIt\MultiFileUpload\Form\Finishers\MultiFileEmailFinisher
         subject: 'New form submission'
         recipients:
           admin@example.com: 'Administrator'
         senderAddress: 'noreply@example.com'
         senderName: 'Website Contact Form'
         attachUploads: true

The finisher automatically:

- Detects all ``MultiImageUpload`` elements in the form
- Attaches all uploaded files to the email
- Supports both HTML and plain text email formats
- Works with standard ``FileUpload`` elements as well

Two pre-configured finisher variants are available in the Form Editor:

- ``MultiFileEmailToReceiver``: Send to site administrator
- ``MultiFileEmailToSender``: Send confirmation to form submitter

These finishers appear in the Form Editor's finisher selection dropdown and can be
configured like the standard email finishers. They automatically use the
``MultiFileEmailFinisher`` implementation class.

**Using pre-configured finishers in YAML:**

.. code-block:: yaml

   finishers:
     - identifier: MultiFileEmailToReceiver
       options:
         subject: 'New form submission'
         recipients:
           admin@example.com: 'Administrator'
         senderAddress: 'noreply@example.com'

Database Finisher Configuration
===============================

The standard TYPO3 ``SaveToDatabase`` finisher only stores file UIDs or identifiers,
**not** proper FAL references. This means uploaded images won't be visible in the
TYPO3 backend for TCA fields with ``type: file``.

The ``MultiFileSaveToDatabase`` finisher solves this by creating proper
``sys_file_reference`` records for each uploaded file.

**Basic configuration:**

.. code-block:: yaml

   finishers:
     - identifier: MultiFileSaveToDatabase
       options:
         table: 'tx_myext_domain_model_item'
         databaseColumnMappings:
           pid:
             value: 1
         elements:
           title:
             mapOnDatabaseColumn: title
           images:
             mapOnDatabaseColumn: images

**What happens:**

1. A new record is inserted into your custom table
2. Text fields are stored directly (like the standard finisher)
3. For ``MultiImageUpload`` fields:

   - The file count is stored in the database column
   - Proper ``sys_file_reference`` records are created
   - Images become visible and editable in the TYPO3 backend

**Required TCA configuration:**

Your TCA field must use ``type: file`` for the images to appear:

.. code-block:: php

   'images' => [
       'label' => 'Images',
       'config' => [
           'type' => 'file',
           'allowed' => 'jpg,jpeg,png',
           'maxitems' => 10,
       ],
   ],

**Additional options:**

The finisher inherits all options from the core ``SaveToDatabase`` finisher:

- ``mode``: ``insert`` (default) or ``update``
- ``whereClause``: Required for update mode
- ``databaseColumnMappings``: Set static values (pid, hidden, etc.)
- ``elements.<identifier>.skipIfValueIsEmpty``: Skip empty fields

Custom Styling
==============

The extension's CSS is automatically loaded via ``<f:asset.css>`` when the
form element is rendered. The CSS file is located at:
``EXT:multi_file_upload/Resources/Public/Css/multi-upload.css``

To override styles, use one of these approaches:

**Option 1: Asset ViewHelper (recommended)**

Add to your form template or layout:

.. code-block:: html

   <f:asset.css identifier="multiUploadCustom"
                href="EXT:your_extension/Resources/Public/Css/multi-upload-custom.css"
                priority="true" />

**Option 2: TypoScript (global)**

Include CSS on all pages:

.. code-block:: typoscript

   page.includeCSS {
       multiUploadCustom = EXT:your_extension/Resources/Public/Css/multi-upload-custom.css
   }

The extension's CSS uses modern CSS nesting syntax.
