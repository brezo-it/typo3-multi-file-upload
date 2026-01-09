.. include:: /Includes.rst.txt

=====
Usage
=====

Using the Form Editor
=====================

The easiest way to add a Multi Image Upload field:

1. Open **Web > Forms** in the TYPO3 backend
2. Create a new form or edit an existing one
3. Click **"Create new element"**
4. Select **"Multi Image Upload"** from the **"Custom"** group
5. Configure the element properties in the inspector panel

Configure the element:

- **Label**: The field label shown to users
- **Save to file mount**: Where uploaded files are stored (e.g., ``1:/user_upload/``)
- **Allowed MIME types**: Which file types are accepted

Using YAML Configuration
========================

For version-controlled form definitions, use YAML:

.. code-block:: yaml

   type: Form
   identifier: contact-form
   label: 'Contact Form'
   prototypeName: standard

   renderables:
     - type: Page
       identifier: page-1
       label: 'Contact'
       renderables:
         - type: Text
           identifier: name
           label: 'Your Name'
           validators:
             - identifier: NotEmpty

         - type: Text
           identifier: email
           label: 'Email Address'
           validators:
             - identifier: NotEmpty
             - identifier: EmailAddress

         - type: MultiImageUpload
           identifier: attachments
           label: 'Attach Images'
           properties:
             saveToFileMount: '1:/user_upload/'
             allowedMimeTypes:
               - 'image/jpeg'
               - 'image/png'

   finishers:
     - identifier: EmailToReceiver
       options:
         implementationClassName: BrezoIt\MultiFileUpload\Form\Finishers\MultiFileEmailFinisher
         subject: 'New Contact Form Submission'
         recipients:
           info@example.com: 'Info'
         senderAddress: '{email}'
         senderName: '{name}'
         attachUploads: true

Complete Example: Classified Ads
=================================

This example shows a complete classified ads/marketplace form that:

- Collects ad details (title, description, images)
- Saves to a custom database table with proper FAL references
- Sends email notification to administrator
- Shows confirmation message to user

**Form definition (YAML):**

.. code-block:: yaml

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
           label: 'Photos'
           properties:
             saveToFileMount: '1:/user_upload/'
             allowedMimeTypes:
               - 'image/jpeg'
               - 'image/png'

     - type: SummaryPage
       identifier: summary
       label: 'Review Your Ad'

   finishers:
     - identifier: MultiFileSaveToDatabase
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
           images:
             mapOnDatabaseColumn: images

     - identifier: MultiFileEmailToReceiver
       options:
         subject: 'New classified ad: {title}'
         recipients:
           admin@example.com: 'Administrator'
         senderAddress: 'noreply@example.com'

     - identifier: Confirmation
       options:
         message: 'Thank you! Your ad will be reviewed shortly.'

**Required TCA (ext_tables.sql):**

.. code-block:: sql

   CREATE TABLE tx_myext_domain_model_classifiedad (
       title varchar(255) DEFAULT '' NOT NULL,
       description text,
       images int(11) unsigned DEFAULT '0' NOT NULL
   );

**Required TCA configuration:**

.. code-block:: php

   return [
       'ctrl' => [
           'title' => 'Classified Ad',
           'label' => 'title',
           // ... standard ctrl settings
       ],
       'columns' => [
           'title' => [
               'label' => 'Title',
               'config' => ['type' => 'input', 'max' => 255],
           ],
           'description' => [
               'label' => 'Description',
               'config' => ['type' => 'text'],
           ],
           'images' => [
               'label' => 'Images',
               'config' => [
                   'type' => 'file',
                   'allowed' => 'jpg,jpeg,png',
                   'maxitems' => 10,
               ],
           ],
       ],
   ];

After form submission, the ad appears in the TYPO3 backend with all images
properly attached and editable.

User Experience
===============

When users interact with the form:

1. **Upload**: Click the file input and select multiple images
2. **Preview**: Uploaded images appear in a responsive grid with thumbnails
3. **Delete**: Each image has a checkbox to mark it for removal
4. **Submit**: On form submission, selected files are attached to the email

The preview gallery uses:

- Responsive Bootstrap 5 grid layout
- Card-based design for each image
- Lightbox support for full-size preview
- Clear delete checkboxes with labels

Template Customization
======================

Override the default templates by creating custom YAML configuration:

.. code-block:: yaml

   TYPO3:
     CMS:
       Form:
         prototypes:
           standard:
             formElementsDefinition:
               MultiImageUpload:
                 renderingOptions:
                   templateRootPaths:
                     100: 'EXT:your_extension/Resources/Private/Templates/Form/'
                   partialRootPaths:
                     100: 'EXT:your_extension/Resources/Private/Partials/Form/'

The main templates are:

- ``Partials/Form/MultiImageUpload.html`` - Main field rendering
- ``Partials/Form/SummaryPage.html`` - Summary page display

Troubleshooting
===============

**Files not uploading**

- Check that the upload folder exists and is writable
- Verify ``saveToFileMount`` points to a valid FAL storage
- Ensure MIME types are correctly configured

**Files not attached to email**

- Use ``MultiFileEmailFinisher`` instead of standard ``EmailFinisher``
- Set ``attachUploads: true`` in finisher options

**Preview images not showing**

- Clear TYPO3 caches after configuration changes
- Check browser console for JavaScript errors
- Verify CSS is loaded correctly

**Images not visible in backend after database save**

- Use ``MultiFileSaveToDatabase`` instead of standard ``SaveToDatabase``
- Ensure your TCA field uses ``type: file`` (not ``type: inline``)
- Check that ``pid`` is set correctly in ``databaseColumnMappings``
- Verify the form field identifier matches the ``elements`` mapping
