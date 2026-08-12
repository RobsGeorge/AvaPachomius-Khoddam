<?php

return [

  /*
  |--------------------------------------------------------------------------
  | Curriculum hosted media
  |--------------------------------------------------------------------------
  |
  | Private document storage for slides/PDFs (not video — use external URLs).
  | Swap CURRICULUM_DISK to curriculum-s3 when moving to object storage.
  |
  */

  'disk' => env('CURRICULUM_DISK', 'curriculum'),

  'max_upload_kb' => (int) env('CURRICULUM_MAX_UPLOAD_KB', 20480),

  'allowed_mimes' => ['pdf', 'ppt', 'pptx', 'doc', 'docx', 'jpg', 'jpeg', 'png', 'webp'],

  'default_quota_bytes' => (int) env('CURRICULUM_DEFAULT_QUOTA_BYTES', 2 * 1024 * 1024 * 1024),

  'settings_quota_key' => 'storage_quota_bytes',

  'settings_used_key' => 'storage_used_bytes',

];
