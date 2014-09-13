<?php

return [
    'nick'            => 'Tay[localhost]',
    'name'            => 'Taylor',
    'account'         => 'taylor',
    'password'        => false, // nickserv passy
    'version'         => 'V4.0',

    'queue_timeout'   => 1,
    'queue_buffer'    => 255,

    'admin_password'  => '',
    'god_mask'        => 'sid3260@staff.darkscience.net',
    'command_trigger' => '>',

    'join_channels'   => [
        '#bots',
        '#cybershade',
        '#darkscience',
    ],

    'log_file'       => storage_path().'/taylor.log',
    'log'            => function (\Cysha\Modules\Taylor\Helpers\Irc\Message $message, $sent = false) {
        echo $message->raw.PHP_EOL;
        //ob_flush(); flush();
    },
];
