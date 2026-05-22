<?php
return [
    'created_by_column' => ['field' => 'created_by', 'comment' => ''],
    'updated_by_column' => ['field' => 'updated_by', 'comment' => ''],
    'deleted_by_column' => ['field' => 'deleted_by', 'comment' => ''],
    'del_flag_column' => ['field' => 'deleted_at', 'comment' => '', 'active' => 'false', 'deleted' => 'true'],
    'log_dir' => storage_path('logs'),
    'log_info_filename' => 'info',
    'log_error_filename' => 'errors',
    'log_warning_filename' => 'warning',
    'log_debug_filename' => 'debug',
    'tmp_upload_dir' => 'tmp_uploads',
    'media_dir' => 'media',
    'sql_log' => env('SQL_LOG', true),
];
