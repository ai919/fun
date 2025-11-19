<?php
return [
    'db' => [
        'host'     => 'localhost',
        'port'     => '3306',
        'name'     => 'fun_quiz',
        'user'     => 'root',
        'password' => 'your_password',
    ],
    'root_path'      => __DIR__,
    'token'          => 'CHANGE_ME_TO_A_LONG_RANDOM_STRING',
    'mysqldump_path' => 'mysqldump',
    'temp_dir'       => sys_get_temp_dir(),
    'backup_dir'     => __DIR__ . '/backups',
    'max_keep'       => 5,
];
