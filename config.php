<?php
return [
    'site_name' => 'IT News',
    'site_url'   => 'https://w99522bk.beget.tech',
    'timezone'   => 'Europe/Moscow',
    'theme_default' => 'dark',
    'ttl'        => 86400,

    'cron_token' => '7f3c2b9a4d8e6f1c0a9b5d3e7f8a1c4d6b2e9f0a7c5d8e1b3f6a9c2d4e7f8a1b',

    'cache_txt'  => __DIR__ . '/cache/txt',
    'cache_img'  => __DIR__ . '/cache/img',
    'data_dir'   => __DIR__ . '/data',
    'logs_dir'   => __DIR__ . '/logs',

    'sources' => [
        [
            'name' => 'Habr',
            'type' => 'rss',
            'url'  => 'https://habr.com/ru/rss/hubs/all/',
        ],
        [
            'name' => '3DNews',
            'type' => 'rss',
            'url'  => 'https://3dnews.ru/news/rss/',
        ],
        [
            'name' => 'Xakep',
            'type' => 'rss',
            'url'  => 'https://xakep.ru/feed/',
        ],
    ],
];