<?php
return [
    'data_dir' => getenv('DORA_DATA_DIR') ?: '/data',
    'db_path' => getenv('DORA_DB_PATH') ?: '/data/assets.sqlite', // legacy fallback / migration source
    'default_view_id' => 1,
    'change_log_retention_days' => (int)(getenv('DORA_CHANGE_LOG_RETENTION_DAYS') ?: 90),
    'change_log_max_records' => (int)(getenv('DORA_CHANGE_LOG_MAX_RECORDS') ?: 5000),
];
