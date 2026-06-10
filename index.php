<?php
require __DIR__ . '/lib.php';

initEnv();

if (isset($_GET['theme']) && in_array($_GET['theme'], ['dark', 'light'], true)) {
    setcookie('theme', $_GET['theme'], time() + 365 * 24 * 3600, '/');
    header('Location: index.php');
    exit;
}

$theme = $_COOKIE['theme'] ?? cfg()['theme_default'];

$items = loadAll();
if (!$items) $items = refreshAll();

$feed = buildFeedItems($items);
$lastUpdate = @file_get_contents(cfg()['data_dir'] . '/last_update.txt') ?: '—';
?>
<!doctype html>
<html lang="ru" data-theme="<?= htmlspecialchars($theme) ?>">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= htmlspecialchars(cfg()['site_name']) ?></title>
<style>
:root{
  --bg:#0e1621;
  --panel:#17212b;
  --panel2:#1f2c3a;
  --text:#e9eef3;
  --muted:#8f9aa5;
  --link:#8ab4ff;
  --border:#2a3a49;
  --accent:#2ea6ff;
}
html[data-theme="light"]{
  --bg:#eef2f7;
  --panel:#ffffff;
  --panel2:#f8fafc;
  --text:#111827;
  --muted:#6b7280;
  --link:#2563eb;
  --border:#e5e7eb;
  --accent:#2ea6ff;
}
*{box-sizing:border-box}
body{
  margin:0;
  background:var(--bg);
  color:var(--text);
  font-family:system-ui,-apple-system,Segoe UI,Roboto,Arial,sans-serif;
}
.wrap{max-width:760px;margin:0 auto;padding:14px}
.top{
  display:flex;justify-content:space-between;align-items:flex-start;gap:12px;
  margin-bottom:14px
}
.brand{font-size:22px;font-weight:800}
.sub{color:var(--muted);font-size:13px;margin-top:4px}
.nav a{color:var(--link);text-decoration:none;margin-left:10px;font-size:14px}
.card{
  background:var(--panel);
  border:1px solid var(--border);
  border-radius:18px;
  overflow:hidden;
  margin-bottom:14px;
  box-shadow:0 8px 28px rgba(0,0,0,.12);
}
.img{
  width:100%;
  aspect-ratio:16/9;
  object-fit:cover;
  display:block;
  background:var(--panel2);
}
.body{padding:13px 14px 14px}
.title{
  font-size:17px;
  line-height:1.35;
  font-weight:700;
  margin-bottom:8px;
}
.title a{color:var(--text);text-decoration:none}
.desc{
  color:var(--text);
  opacity:.95;
  line-height:1.55;
  font-size:15px;
  margin-bottom:10px;
  white-space:normal;
}
.quote{
  border-left:3px solid var(--accent);
  padding-left:10px;
  margin:10px 0;
  color:var(--muted);
  font-style:italic;
  line-height:1.45;
}
.meta{
  display:flex;flex-wrap:wrap;gap:8px;align-items:center;
  color:var(--muted);font-size:13px
}
.badge{
  background:rgba(46,166,255,.12);
  color:var(--accent);
  border:1px solid rgba(46,166,255,.18);
  padding:4px 8px;
  border-radius:999px;
  font-size:12px;
}
.small{color:var(--muted);font-size:12px;margin-top:10px}
@media (max-width: 520px){
  .wrap{padding:10px}
  .title{font-size:16px}
  .desc{font-size:14px}
}
</style>
</head>
<body>
<div class="wrap">
  <div class="top">
    <div>
      <div class="brand"><?= htmlspecialchars(cfg()['site_name']) ?></div>
      <div class="sub">Обновлено: <?= htmlspecialchars($lastUpdate) ?></div>
    </div>
    <div class="nav">
      <a href="?theme=dark">Тёмная</a>
      <a href="?theme=light">Светлая</a>
      <a href="rss.php">RSS</a>
      <a href="refresh.php">Refresh</a>
    </div>
  </div>

  <?php foreach ($feed as $item): ?>
    <div class="card">
      <?php if (!empty($item['image'])): ?>
        <img class="img" src="<?= htmlspecialchars($item['image']) ?>" alt="">
      <?php endif; ?>
      <div class="body">
        <div class="title">
          <a href="<?= htmlspecialchars($item['link']) ?>" target="_blank" rel="noopener noreferrer">
            <?= htmlspecialchars($item['title']) ?>
          </a>
        </div>

        <div class="desc"><?= nl2br(htmlspecialchars($item['content'] ?? $item['description'] ?? '')) ?></div>

        <div class="quote">Новости группируются автоматически. Сообщение может быть неполным.</div>

        <div class="meta">
          <span class="badge"><?= htmlspecialchars($item['source']) ?></span>
          <span><?= htmlspecialchars((string)$item['count']) ?> источн.</span>
          <span><?= htmlspecialchars(implode(' • ', $item['sources'])) ?></span>
        </div>

        <div class="small"><?= htmlspecialchars($item['published_at']) ?></div>
      </div>
    </div>
  <?php endforeach; ?>
</div>
</body>
</html>