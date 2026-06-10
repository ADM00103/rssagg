<?php
require __DIR__ . '/lib.php';

initEnv();

header('Content-Type: application/rss+xml; charset=utf-8');

$items = loadAll();
$feed = buildFeedItems($items);

$dom = new DOMDocument('1.0', 'UTF-8');
$dom->formatOutput = true;

$rss = $dom->createElement('rss');
$rss->setAttribute('version', '2.0');
$rss->setAttribute('xmlns:content', 'http://purl.org/rss/1.0/modules/content/');
$dom->appendChild($rss);

$channel = $dom->createElement('channel');
$rss->appendChild($channel);

$channel->appendChild($dom->createElement('title', cfg()['site_name']));
$channel->appendChild($dom->createElement('link', cfg()['site_url']));
$channel->appendChild($dom->createElement('description', 'IT news RSS feed'));
$channel->appendChild($dom->createElement('language', 'ru-RU'));
$channel->appendChild($dom->createElement('lastBuildDate', date(DATE_RSS)));

foreach ($feed as $f) {
    $item = $dom->createElement('item');
    $item->appendChild($dom->createElement('title', $f['title']));
    $item->appendChild($dom->createElement('link', $f['link']));
    $item->appendChild($dom->createElement('guid', $f['link']));
    $item->appendChild($dom->createElement('pubDate', date(DATE_RSS, strtotime($f['published_at']))));

    $desc = $dom->createElement('description');
    $desc->appendChild($dom->createCDATASection(normalizeUtf8($f['description'] ?? '')));
    $item->appendChild($desc);

    $content = $dom->createElement('content:encoded');
    $content->appendChild($dom->createCDATASection(normalizeUtf8($f['content'] ?: $f['description'])));
    $item->appendChild($content);

    if (!empty($f['image'])) {
        $enclosure = $dom->createElement('enclosure');
        $enclosure->setAttribute('url', $f['image']);
        $enclosure->setAttribute('type', 'image/jpeg');
        $item->appendChild($enclosure);
    }

    $channel->appendChild($item);
}

echo $dom->saveXML();