.. include:: /Includes.rst.txt

============
Introduction
============

What does it do?
================

This TYPO3 extension adds a **Multi Image Upload** form element to the
TYPO3 Form Framework. It allows website visitors to upload multiple files
at once in a single form field.

Features
========

- **Multi-file upload**: Upload multiple files in a single form field
- **Two form elements**: ``MultiImageUpload`` (with preview) and ``MultiFileUpload`` (generic)
- **Preview gallery**: Shows uploaded images with lightbox support
- **Delete functionality**: Users can remove individual files before submission
- **Email attachments**: Extended email finisher automatically attaches uploaded files
- **Database storage with FAL**: Creates proper ``sys_file_reference`` records for backend visibility
- **Bootstrap 5 styling**: Responsive grid layout with customizable CSS classes
- **Localization**: German and English translations included
- **Form Editor support**: Full integration with TYPO3 backend Form Editor
- **Secure**: CSRF protection, HMAC-signed file references, path validation

Requirements
============

- TYPO3 13.4 LTS
- PHP 8.2 or higher
- EXT:form (TYPO3 Form Framework)

Screenshots
===========

The Multi Image Upload element renders as a file input that accepts multiple files.
Uploaded files are displayed in a responsive grid with preview thumbnails and
individual delete checkboxes.

Technical Background
====================

The extension uses a custom ``MultiFile`` ObjectStorage type to enable automatic
type converter selection without requiring XCLASS overrides. This clean architecture
ensures compatibility with future TYPO3 versions.

Key components:

- **MultiFile**: Custom ObjectStorage wrapper for type converter targeting
- **MultiFileEmailFinisher**: Extended email finisher with attachment support
- **MultiUploadedFileReferenceConverter**: Handles conversion of uploaded files
- **MultiFilePropertyMappingConfiguration**: Hook-based configuration
