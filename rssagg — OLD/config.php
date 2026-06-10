<?php
return [
    'site_name' => 'IT News',
    'site_url'   => 'https://example.com/news',
    'timezone'   => 'Europe/Moscow',

    'ttl'        => 86400,

    'cache_txt'  => __DIR__ . '/cache/txt',
    'cache_img'  => __DIR__ . '/cache/img',
    'data_dir'   => __DIR__ . '/data',
    'logs_dir'   => __DIR__ . '/logs',

    'theme_default' => 'dark',

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