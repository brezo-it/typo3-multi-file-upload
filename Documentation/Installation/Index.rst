.. include:: /Includes.rst.txt

============
Installation
============

The extension can be installed via Composer (recommended).

Prerequisites
=============

This extension requires **EXT:form** (TYPO3 Form Framework) to be installed and active.
EXT:form is included in TYPO3 but may need to be activated.

Composer Installation
=====================

Run the following command in your TYPO3 project root:

.. code-block:: bash

   composer require brezo-it/multi-file-upload

Activate the Extension
======================

After installation, activate the extension in the TYPO3 backend:

1. Go to **Admin Tools > Extensions**
2. Search for "multi_file_upload"
3. Click the activate icon

Or via CLI:

.. code-block:: bash

   vendor/bin/typo3 extension:activate multi_file_upload

Include TypoScript
==================

The extension automatically registers its form configuration via
``ext_localconf.php``. No additional TypoScript inclusion is required.

The form setup is registered for both frontend forms and backend module:

- ``plugin.tx_form.settings.yamlConfigurations``
- ``module.tx_form.settings.yamlConfigurations``

Verify Installation
===================

After installation, open the TYPO3 Form Editor in the backend. You should see
a new element **"Multi Image Upload"** in the **"Custom"** element group.
