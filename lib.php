<?php

function cfg(): array {
    static $c = null;
    if ($c === null) $c = require __DIR__ . '/config.php';
    return $c;
}

function ensureDirs(): void {
    $c = cfg();
    foreach ([$c['cache_txt'], $c['cache_img'], $c['data_dir'], $c['logs_dir']] as $dir) {
        if (!is_dir($dir)) mkdir($dir, 0775, true);
    }
}

function initEnv(): void {
    $c = cfg();
    date_default_timezone_set($c['timezone'] ?? 'Europe/Moscow');
    ensureDirs();
}

function normalizeUtf8(string $text): string {
    if ($text === '') return '';
    if (!mb_check_encoding($text, 'UTF-8')) {
        $enc = mb_detect_encoding($text, ['UTF-8', 'Windows-1251', 'ISO-8859-1', 'Windows-1252'], true);
        if ($enc && $enc !== 'UTF-8') {
            $text = mb_convert_encoding($text, 'UTF-8', $enc);
        } else {
            $text = utf8_encode($text);
        }
    }
    return $text;
}

function httpGet(string $url): string {
    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 20,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ITNewsBot/1.0)',
        CURLOPT_ENCODING => '',
    ]);
    $res = curl_exec($ch);
    curl_close($ch);
    return normalizeUtf8($res ?: '');
}

function cleanText(string $text): string {
    $text = normalizeUtf8($text);
    $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    $text = strip_tags($text);
    $text = preg_replace('/\s+/u', ' ', $text);
    return trim($text);
}

function shortText(string $text, int $len = 240): string {
    $text = cleanText($text);
    return mb_strlen($text) > $len ? mb_substr($text, 0, $len - 1) . '…' : $text;
}

function slug(string $text): string {
    $text = mb_strtolower($text);
    $text = preg_replace('/[^\p{L}\p{N}]+/u', '-', $text);
    $text = trim($text, '-');
    return $text ?: 'news';
}

function hashKey(array $item): string {
    return sha1(($item['title'] ?? '') . '|' . ($item['link'] ?? ''));
}

function txtPath(string $id): string {
    return cfg()['cache_txt'] . '/' . $id . '.txt';
}

function imgPath(string $id, string $ext = 'jpg'): string {
    return cfg()['cache_img'] . '/' . $id . '.' . $ext;
}

function saveTxtCache(array $item): string {
    ensureDirs();
    $file = txtPath($item['id']);
    file_put_contents($file, json_encode($item, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    return $file;
}

function downloadFile(string $url, string $dest): bool {
    $fp = fopen($dest, 'wb');
    if (!$fp) return false;

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_FILE => $fp,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 30,
        CURLOPT_CONNECTTIMEOUT => 10,
        CURLOPT_USERAGENT => 'Mozilla/5.0 (compatible; ITNewsBot/1.0)',
        CURLOPT_ENCODING => '',
    ]);
    $ok = curl_exec($ch);
    curl_close($ch);
    fclose($fp);

    return $ok && is_file($dest) && filesize($dest) > 0;
}

function detectImageExt(string $url): string {
    $path = parse_url($url, PHP_URL_PATH) ?: '';
    $ext = strtolower(pathinfo($path, PATHINFO_EXTENSION));
    return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true) ? $ext : 'jpg';
}

function downloadImage(string $url, string $id): string {
    ensureDirs();
    if (!$url) return '';

    $ext = detectImageExt($url);
    $dst = imgPath($id, $ext);
    if (is_file($dst)) return 'cache/img/' . basename($dst);

    if (!downloadFile($url, $dst)) {
        @unlink($dst);
        return '';
    }

    return 'cache/img/' . basename($dst);
}

function extractRssImage($it): string {
    $ns = $it->getNameSpaces(true);

    if (isset($ns['media'])) {
        $media = $it->children($ns['media']);
        if (isset($media->content)) {
            $a = $media->content->attributes();
            if (!empty($a['url'])) return (string)$a['url'];
        }
        if (isset($media->thumbnail)) {
            $a = $media->thumbnail->attributes();
            if (!empty($a['url'])) return (string)$a['url'];
        }
    }

    if (!empty($it->enclosure)) {
        $a = $it->enclosure->attributes();
        if (!empty($a['url'])) return (string)$a['url'];
    }

    return '';
}

function extractArticleText(string $html): string {
    $html = normalizeUtf8($html);
    if ($html === '') return '';

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xp = new DOMXPath($dom);

    $queries = [
        '//article',
        '//div[contains(@class,"article")]',
        '//div[contains(@class,"post")]',
        '//div[contains(@class,"content")]',
        '//main',
    ];

    foreach ($queries as $q) {
        $nodes = $xp->query($q);
        if ($nodes && $nodes->length) {
            $text = cleanText($nodes->item(0)->textContent);
            if (mb_strlen($text) > 200) return $text;
        }
    }

    $body = $xp->query('//body')->item(0);
    return $body ? cleanText($body->textContent) : '';
}

function fetchPageContent(string $url): array {
    $html = httpGet($url);
    if (!$html) return ['title' => '', 'description' => '', 'image' => '', 'content' => ''];

    libxml_use_internal_errors(true);
    $dom = new DOMDocument('1.0', 'UTF-8');
    @$dom->loadHTML('<?xml encoding="UTF-8">' . $html);
    $xp = new DOMXPath($dom);

    $getMeta = function(string $key) use ($xp): string {
        $node = $xp->query("//meta[@property='$key' or @name='$key']")->item(0);
        if (!$node) return '';
        $attr = $node->attributes?->getNamedItem('content');
        return $attr ? cleanText((string)$attr->nodeValue) : '';
    };

    return [
        'title' => $getMeta('og:title'),
        'description' => $getMeta('og:description'),
        'image' => $getMeta('og:image') ?: $getMeta('twitter:image'),
        'content' => extractArticleText($html),
    ];
}

function fetchRss(string $sourceName, string $url): array {
    $xml = httpGet($url);
    if (!$xml) return [];

    $xml = normalizeUtf8($xml);
    $xml = preg_replace('/encoding=("|\')([^"\']+)(\1)/i', 'encoding="UTF-8"', $xml, 1);

    libxml_use_internal_errors(true);
    $rss = simplexml_load_string($xml, 'SimpleXMLElement', LIBXML_NOCDATA);
    if (!$rss || !isset($rss->channel->item)) return [];

    $items = [];
    foreach ($rss->channel->item as $it) {
        $title = cleanText((string)$it->title);
        $link = cleanText((string)$it->link);
        $desc = cleanText((string)$it->description);
        $date = cleanText((string)$it->pubDate);
        $img = extractRssImage($it);

        $items[] = [
            'source' => $sourceName,
            'type' => 'rss',
            'title' => $title,
            'link' => $link,
            'description' => $desc,
            'content' => '',
            'image_remote' => $img,
            'published_at' => $date ? date('Y-m-d H:i:s', strtotime($date)) : date('Y-m-d H:i:s'),
        ];
    }

    return $items;
}

function semanticKey(array $item): string {
    $txt = mb_strtolower(($item['title'] ?? '') . ' ' . ($item['content'] ?? '') . ' ' . ($item['description'] ?? ''));
    $txt = preg_replace('/[^\p{L}\p{N}\s]+/u', ' ', $txt);
    $txt = preg_replace('/\s+/u', ' ', trim($txt));

    $words = array_values(array_filter(explode(' ', $txt)));
    $stop = ['и','в','на','для','что','как','или','the','to','of','по','из','за','с','у','от','об','это','этот','эта','those','this'];
    $words = array_values(array_filter($words, fn($w) => mb_strlen($w) > 2 && !in_array($w, $stop, true)));

    return implode(' ', array_slice($words, 0, 8));
}

function groupByMeaning(array $items): array {
    $groups = [];
    foreach ($items as $item) {
        $key = semanticKey($item);
        if ($key === '') $key = slug($item['title'] ?? 'news');
        if (!isset($groups[$key])) {
            $groups[$key] = ['main' => $item, 'items' => []];
        }
        $groups[$key]['items'][] = $item;
    }
    return array_values($groups);
}

function refreshAll(): array {
    initEnv();
    $cfg = cfg();
    $all = [];

    foreach ($cfg['sources'] as $source) {
        if (($source['type'] ?? '') !== 'rss') continue;

        $items = fetchRss($source['name'], $source['url']);
        foreach ($items as $item) {
            $item['id'] = hashKey($item);

            $page = fetchPageContent($item['link']);

            $item['content'] = !empty($page['content']) ? $page['content'] : ($item['description'] ?? '');
            if (!empty($page['title']) && empty($item['title'])) $item['title'] = $page['title'];
            if (!empty($page['description']) && empty($item['description'])) $item['description'] = $page['description'];

            $item['summary'] = shortText($item['description'] ?: $item['content'] ?: $item['title'], 260);

            $localImage = '';
            if (!empty($item['image_remote'])) {
                $localImage = downloadImage($item['image_remote'], $item['id']);
            }
            if (!$localImage && !empty($page['image'])) {
                $localImage = downloadImage($page['image'], $item['id']);
            }

            $item['image'] = $localImage ?: '';
            unset($item['image_remote']);

            saveTxtCache($item);
            $all[] = $item;
        }
    }

    usort($all, fn($a, $b) => strtotime($b['published_at']) <=> strtotime($a['published_at']));

    file_put_contents(cfg()['data_dir'] . '/index.json', json_encode($all, JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
    file_put_contents(cfg()['data_dir'] . '/last_update.txt', date('Y-m-d H:i:s'));

    return $all;
}

function loadAll(): array {
    initEnv();
    $index = cfg()['data_dir'] . '/index.json';
    if (!is_file($index)) return [];
    $data = json_decode(file_get_contents($index), true);
    return is_array($data) ? $data : [];
}

function cleanupOldFiles(): array {
    initEnv();
    $ttl = cfg()['ttl'];
    $now = time();
    $res = ['txt' => 0, 'img' => 0, 'json' => 0];

    foreach (glob(cfg()['cache_txt'] . '/*.txt') ?: [] as $f) {
        if ($now - filemtime($f) >= $ttl) {
            @unlink($f);
            $res['txt']++;
        }
    }

    foreach (glob(cfg()['cache_img'] . '/*.*') ?: [] as $f) {
        if ($now - filemtime($f) >= $ttl) {
            @unlink($f);
            $res['img']++;
        }
    }

    foreach (['index.json', 'last_update.txt'] as $name) {
        $f = cfg()['data_dir'] . '/' . $name;
        if (is_file($f) && $now - filemtime($f) >= $ttl) {
            @unlink($f);
            $res['json']++;
        }
    }

    return $res;
}

function buildFeedItems(array $items): array {
    $groups = groupByMeaning($items);
    $out = [];

    foreach ($groups as $g) {
        $main = $g['main'];
        $sources = array_values(array_unique(array_map(fn($x) => $x['source'], $g['items'])));
        $out[] = [
            'id' => $main['id'] ?? hashKey($main),
            'title' => $main['title'] ?? '',
            'link' => $main['link'] ?? '',
            'content' => $main['content'] ?? '',
            'description' => $main['summary'] ?? shortText($main['description'] ?? ($main['content'] ?? $main['title'] ?? ''), 260),
            'image' => $main['image'] ?? '',
            'source' => $main['source'] ?? '',
            'sources' => $sources,
            'count' => count($g['items']),
            'published_at' => $main['published_at'] ?? date('Y-m-d H:i:s'),
        ];
    }

    return $out;
}