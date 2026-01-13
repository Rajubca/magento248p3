<?php
return [
    'remote_storage' => [
        'driver' => 'file'
    ],
    'backend' => [
        'frontName' => 'admin'
    ],
    'cache' => [
        'graphql' => [
            'id_salt' => 'bcDVvicEA0WOXIwNEUh8Kxrf6CHj78Jm'
        ],
        'frontend' => [
            'default' => [
                'id_prefix' => '878_'
            ],
            'page_cache' => [
                'id_prefix' => '878_'
            ]
        ],
        'allow_parallel_generation' => false
    ],
    'config' => [
        'async' => 0
    ],
    'queue' => [
        'consumers_wait_for_messages' => 1
    ],
    'crypt' => [
        'key' => 'base647nKKB7nU6HEkLqyTQ8d++IclKuxwY66zjSDkD/X7z6s='
    ],
    'db' => [
        'table_prefix' => '',
        'connection' => [
            'default' => [
                'host' => 'localhost',
                'dbname' => 'magento248p3_1',
                'username' => 'root',
                'password' => 'root',
                'model' => 'mysql4',
                'engine' => 'innodb',
                'initStatements' => 'SET NAMES utf8;',
                'active' => '1',
                'driver_options' => [
                    1014 => false
                ]
            ]
        ]
    ],
    'resource' => [
        'default_setup' => [
            'connection' => 'default'
        ]
    ],
    'x-frame-options' => 'SAMEORIGIN',
    'MAGE_MODE' => 'default',
    'session' => [
        'save' => 'files'
    ],
    'lock' => [
        'provider' => 'db'
    ],
    'directories' => [
        'document_root_is_pub' => true
    ],
    'cache_types' => [
        'config' => 1,
        'layout' => 1,
        'block_html' => 1,
        'collections' => 1,
        'reflection' => 1,
        'db_ddl' => 1,
        'compiled_config' => 1,
        'eav' => 1,
        'customer_notification' => 1,
        'config_integration' => 1,
        'config_integration_api' => 1,
        'graphql_query_resolver_result' => 1,
        'full_page' => 1,
        'config_webservice' => 1,
        'translate' => 1
    ],
    'search' => [
        'engine' => 'opensearch',
        'opensearch' => [
            'host' => 'localhost',
            'port' => 9200,
            'username' => 'admin',
            'password' => '@Shiva$12345',
            'timeout' => 30,
            'client' => [
                'scheme' => 'https',
                'ssl' => [
                    'verify' => false,
                    'verify_host' => false
                ]
            ]
        ]
    ],

    'install' => [
        'date' => 'Tue, 06 Jan 2026 08:34:11 +0000'
    ],

];
