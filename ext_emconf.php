<?php

$EM_CONF[$_EXTKEY] = [
    'title' => 'Multi File Upload',
    'description' => 'TYPO3 Form Framework enhancement for multi-file upload with FAL support, email attachments and database storage.',
    'category' => 'plugin',
    'author' => 'Maik Preuss',
    'author_email' => 'maik.preuss@gmx.de',
    'author_company' => 'Brezo IT',
    'state' => 'stable',
    'clearCacheOnLoad' => 1,
    'version' => '1.0.1',
    'constraints' => [
        'depends' => [
            'typo3' => '13.4.0-13.4.99',
            'form' => '13.4.0-13.4.99',
        ],
        'conflicts' => [],
        'suggests' => [],
    ],
];
