<?php

return [
    'nick'           => 'Tay[localhost]',
    'name'           => 'Taylor',
    'account'        => 'taylor',
    'password'       => 'xxlinkk',
    'version'        => 'V4.0',

    'queue_timeout'  => 1,
    'queue_buffer'   => 255,

    'admin_password' => '',
    'god_mask'       => 'sid3260@staff.darkscience.net',

    'join_channels'  => [
        '#bots',
    ],

    'log_file'       => storage_path().'/taylor.log',
    'log'            => function (\Cysha\Modules\Taylor\Helpers\Irc\Message $message, $sent = false) {
        echo $message->raw.PHP_EOL;
        //ob_flush(); flush();
    },
];
