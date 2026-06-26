<?php
return [
    'data_dir' => getenv('DORA_DATA_DIR') ?: '/data',
    'db_path' => getenv('DORA_DB_PATH') ?: '/data/assets.sqlite', // legacy fallback / migration source
    'default_view_id' => 1,
];
