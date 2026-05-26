<?php

declare(strict_types=1);

return [
    'displayErrorDetails' => filter_var(getenv('DISPLAY_ERROR_DETAILS') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'db_path' => getenv('DB_PATH') ?: '/app/data/zipcode.db',
    'install_enabled' => filter_var(getenv('INSTALL_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'default_country' => [
        'name' => 'Brasil',
        'alphacode2' => 'BR',
        'alphacode3' => 'BRA',
        'numcode' => 76,
    ],
];
