<?php

declare(strict_types=1);

// The install record `composer install` writes for two symlinked path repositories, as composer 2 wrote it, in this repository's code style.
return [
    'root' => [
        'name' => 'acme/consumer',
        'pretty_version' => '1.0.0+no-version-set',
        'version' => '1.0.0.0',
        'reference' => null,
        'type' => 'library',
        'install_path' => __DIR__ . '/../../',
        'aliases' => [],
        'dev' => true,
    ],
    'versions' => [
        'acme/base' => [
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'reference' => '7cc1488cd5dd8d3ceaf935e37314622dca8b5034',
            'type' => 'library',
            'install_path' => __DIR__ . '/../acme/base',
            'aliases' => [],
            'dev_requirement' => false,
        ],
        'acme/consumer' => [
            'pretty_version' => '1.0.0+no-version-set',
            'version' => '1.0.0.0',
            'reference' => null,
            'type' => 'library',
            'install_path' => __DIR__ . '/../../',
            'aliases' => [],
            'dev_requirement' => false,
        ],
        'acme/tier' => [
            'pretty_version' => '1.0.0',
            'version' => '1.0.0.0',
            'reference' => 'ac15728040490209595bc041795b3bdb656d0ee0',
            'type' => 'library',
            'install_path' => __DIR__ . '/../acme/tier',
            'aliases' => [],
            'dev_requirement' => false,
        ],
    ],
];
