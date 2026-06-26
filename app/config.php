<?php
return [
    'db_path' => getenv('DORA_DB_PATH') ?: __DIR__ . '/../data/assets.sqlite',
    'default_view_id' => 1,
];
