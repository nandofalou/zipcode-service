<?php

declare(strict_types=1);

return [
    'displayErrorDetails' => filter_var(getenv('DISPLAY_ERROR_DETAILS') ?: 'false', FILTER_VALIDATE_BOOLEAN),
    'db_path' => getenv('DB_PATH') ?: dirname(__DIR__) . '/data/zipcode.db',
    'install_enabled' => filter_var(getenv('INSTALL_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
    'default_country' => [
        'name' => 'Brasil',
        'alphacode2' => 'BR',
        'alphacode3' => 'BRA',
        'numcode' => 76,
    ],
    'nominatim_base_url' => getenv('NOMINATIM_BASE_URL') ?: 'https://nominatim.openstreetmap.org',
    'nominatim_user_agent' => getenv('NOMINATIM_USER_AGENT') ?: 'ZipcodeMicroservice/1.0 (dev-local)',
];
