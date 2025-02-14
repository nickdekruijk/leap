<?php

return [
    'organizations' => false,
    'login_image' => null,
    'filemanager' => [
        'disk' => 'public', // Must refer to a disk defined in config/filesystems.php
        'upload_max_filesize' => '12M', // Maximum size of an uploaded file in bytes, still limited by livewire.temporary_file_upload.rules.max configuration and php.ini upload_max_filesize and post_max_size
        'allowed_extensions' => ['png', 'jpg', 'jpeg', 'gif', 'svg', 'zip', 'pdf', 'doc', 'docx', 'csv', 'xls', 'xlsx', 'pages', 'numbers', 'psd', 'ai', 'eps', 'mp4', 'mp3', 'mpg', 'm4a', 'ogg', 'sketch', 'json', 'rtf', 'md'],
    ],
];
