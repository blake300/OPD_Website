<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/store.php';
require_once __DIR__ . '/../src/site_auth.php';

function opd_extract_video_embed_url(string $input): string
{
    $value = trim($input);
    if ($value === '') {
        return '';
    }

    if (preg_match('/<iframe[^>]+src=["\']([^"\']+)["\']/i', $value, $match)) {
        $value = $match[1];
    }
    $value = trim($value);
    if (str_starts_with($value, '//')) {
        $value = 'https:' . $value;
    }

    $host = '';
    $path = '';
    $query = '';
    $parts = parse_url($value);
    if (is_array($parts)) {
        $host = strtolower($parts['host'] ?? '');
        $path = $parts['path'] ?? '';
        $query = $parts['query'] ?? '';
    }
    $host = preg_replace('/^(www\.|m\.)/', '', $host);

    if ($host === 'youtu.be') {
        $id = trim($path, '/');
        if ($id !== '') {
            return 'https://www.youtube.com/embed/' . $id;
        }
    }
    if ($host !== '' && str_ends_with($host, 'youtube.com')) {
        if ($path === '/watch' && $query !== '') {
            parse_str($query, $params);
            if (!empty($params['v'])) {
                return 'https://www.youtube.com/embed/' . $params['v'];
            }
        }
        if (preg_match('#^/(embed|shorts|live)/([^/?]+)#', $path, $match)) {
            return 'https://www.youtube.com/embed/' . $match[2];
        }
    }
    if ($host !== '' && str_ends_with($host, 'vimeo.com')) {
        if (preg_match('#/(?:video/)?(\d+)#', $path, $match)) {
            return 'https://player.vimeo.com/video/' . $match[1];
        }
    }

    if (preg_match('/youtube\.com\/watch\?v=([a-zA-Z0-9_-]+)/', $value, $match)) {
        return 'https://www.youtube.com/embed/' . $match[1];
    }
    if (preg_match('/youtube\.com\/shorts\/([a-zA-Z0-9_-]+)/', $value, $match)) {
        return 'https://www.youtube.com/embed/' . $match[1];
    }
    if (preg_match('/youtube\.com\/embed\/([a-zA-Z0-9_-]+)/', $value, $match)) {
        return 'https://www.youtube.com/embed/' . $match[1];
    }
    if (preg_match('/youtube\.com\/live\/([a-zA-Z0-9_-]+)/', $value, $match)) {
        return 'https://www.youtube.com/embed/' . $match[1];
    }
    if (preg_match('/youtu\.be\/([a-zA-Z0-9_-]+)/', $value, $match)) {
        return 'https://www.youtube.com/embed/' . $match[1];
    }
    if (preg_match('/player\.vimeo\.com\/video\/(\d+)/', $value, $match)) {
        return 'https://player.vimeo.com/video/' . $match[1];
    }
    if (preg_match('/vimeo\.com\/(\d+)/', $value, $match)) {
        return 'https://player.vimeo.com/video/' . $match[1];
    }

    return '';
}

// Get page slug from URL
$slug = trim((string) ($_GET['slug'] ?? ''));

if ($slug === '') {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Page Not Found</title></head><body><h1>Page Not Found</h1></body></html>';
    exit;
}

$pdo = opd_db();

// Get page
$stmt = $pdo->prepare('SELECT * FROM pages WHERE slug = ? AND status = ? LIMIT 1');
$stmt->execute([$slug, 'published']);
$page = $stmt->fetch();

if (!$page) {
    http_response_code(404);
    echo '<!DOCTYPE html><html><head><title>Page Not Found</title></head><body><h1>Page Not Found</h1></body></html>';
    exit;
}

// Get sections
$sectionsStmt = $pdo->prepare('SELECT * FROM page_sections WHERE pageId = ? ORDER BY sortOrder ASC');
$sectionsStmt->execute([$page['id']]);
$sections = $sectionsStmt->fetchAll();

$rows = [];
foreach ($sections as $section) {
    $decoded = !empty($section['content']) ? json_decode($section['content'], true) : [];
    $content = is_array($decoded) ? $decoded : [];
    $type = $section['sectionType'];
    if ($type === 'row' && isset($content['sections']) && is_array($content['sections'])) {
        $rows[] = [
            'height' => $content['height'] ?? 'auto',
            'columns' => (int) ($content['columns'] ?? count($content['sections']) ?? 1),
            'sections' => $content['sections']
        ];
        continue;
    }
    $rows[] = [
        'height' => 'auto',
        'columns' => 1,
        'sections' => [[
            'type' => $type,
            'content' => $content
        ]]
    ];
}

$user = site_current_user();
?>
<!DOCTYPE html>
<html lang="en">
<head>
  <meta charset="UTF-8" />
  <meta name="viewport" content="width=device-width, initial-scale=1" />
  <title><?php echo htmlspecialchars($page['title'], ENT_QUOTES); ?> - <?php echo htmlspecialchars(opd_site_name(), ENT_QUOTES); ?></title>
  <?php if (!empty($page['metaDescription'])): ?>
    <meta name="description" content="<?php echo htmlspecialchars($page['metaDescription'], ENT_QUOTES); ?>" />
  <?php endif; ?>
  <link rel="stylesheet" href="/assets/css/site.css" />
  <style>
    .page-content {
      max-width: 900px;
      margin: 0 auto;
      padding: 40px 20px;
    }
    .page-sections {
      display: flex;
      flex-direction: column;
      gap: 32px;
    }
    .page-row {
      display: grid;
      gap: 24px;
      align-items: stretch;
    }
    .page-row-section h1 {
      font-size: 2.2rem;
      margin: 0 0 16px;
    }
    .page-row-section h2 {
      font-size: 1.9rem;
      margin: 0 0 16px;
    }
    .page-row-section h3 {
      font-size: 1.4rem;
      margin: 0 0 12px;
    }
    .page-row-section-text {
      line-height: 1.7;
      color: #333;
    }
    .page-row-section-text p {
      margin: 0 0 16px;
    }
    .page-row-section-image img {
      max-width: 100%;
      height: auto;
      border-radius: 8px;
    }
    .page-row-section-video {
      position: relative;
      padding-bottom: 56.25%;
      height: 0;
      overflow: hidden;
      border-radius: 8px;
    }
    .page-row-section-video iframe {
      position: absolute;
      top: 0;
      left: 0;
      width: 100%;
      height: 100%;
    }
    /* Hero template */
    .template-hero-content .page-row:first-child {
      background: linear-gradient(135deg, #1a1a2e 0%, #16213e 100%);
      color: white;
      padding: 60px 40px;
      border-radius: 12px;
      text-align: center;
    }
    .template-hero-content .page-row:first-child h1,
    .template-hero-content .page-row:first-child h2,
    .template-hero-content .page-row:first-child h3 {
      color: white;
    }
    @media (max-width: 768px) {
      .page-row {
        grid-template-columns: 1fr !important;
      }
    }
  </style>
</head>
<body>
  <?php require __DIR__ . '/partials/site-header.php'; ?>

  <main class="page-content template-<?php echo htmlspecialchars($page['template'], ENT_QUOTES); ?>">
    <div class="page-sections">
      <?php foreach ($rows as $row): ?>
        <?php
        $columns = (int) ($row['columns'] ?? 1);
        $columns = $columns > 0 ? $columns : 1;
        $heightValue = $row['height'] ?? 'auto';
        $heightStyle = '';
        if (is_numeric($heightValue) && (int) $heightValue > 0) {
            $heightStyle = 'min-height: ' . (int) $heightValue . 'px;';
        }
        ?>
        <div class="page-row" style="grid-template-columns: repeat(<?php echo $columns; ?>, minmax(0, 1fr)); <?php echo $heightStyle; ?>">
          <?php foreach ($row['sections'] as $section): ?>
            <?php
            $sectionType = $section['type'] ?? 'text';
            $sectionContent = $section['content'] ?? [];
            ?>
            <div class="page-row-section page-row-section-<?php echo htmlspecialchars($sectionType, ENT_QUOTES); ?>">
              <?php if ($sectionType === 'headline'): ?>
                <?php
                $text = htmlspecialchars($sectionContent['text'] ?? '', ENT_QUOTES);
                $size = $sectionContent['size'] ?? 'h2';
                echo "<{$size}>{$text}</{$size}>";
                ?>
              <?php elseif ($sectionType === 'text'): ?>
                <?php
                $headline = trim((string) ($sectionContent['headline'] ?? ''));
                if ($headline !== '') {
                    echo '<h3>' . htmlspecialchars($headline, ENT_QUOTES) . '</h3>';
                }
                $text = $sectionContent['text'] ?? '';
                $paragraphs = array_filter(preg_split('/\r?\n/', $text));
                if ($paragraphs) {
                    echo '<div class="page-row-section-text">';
                    foreach ($paragraphs as $para) {
                        echo '<p>' . htmlspecialchars(trim($para), ENT_QUOTES) . '</p>';
                    }
                    echo '</div>';
                }
                ?>
              <?php elseif ($sectionType === 'image'): ?>
                <?php
                $url = htmlspecialchars($sectionContent['url'] ?? '', ENT_QUOTES);
                $alt = htmlspecialchars($sectionContent['alt'] ?? '', ENT_QUOTES);
                if ($url): ?>
                  <div class="page-row-section-image">
                    <img src="<?php echo $url; ?>" alt="<?php echo $alt; ?>" />
                  </div>
                <?php endif; ?>
              <?php elseif ($sectionType === 'video'): ?>
                <?php
                $url = (string) ($sectionContent['url'] ?? '');
                $embedUrl = opd_extract_video_embed_url($url);
                if ($embedUrl !== ''): ?>
                  <div class="page-row-section-video">
                    <iframe src="<?php echo htmlspecialchars($embedUrl, ENT_QUOTES); ?>" frameborder="0" allow="accelerometer; autoplay; clipboard-write; encrypted-media; gyroscope; picture-in-picture" allowfullscreen></iframe>
                  </div>
                <?php endif; ?>
              <?php endif; ?>
            </div>
          <?php endforeach; ?>
        </div>
      <?php endforeach; ?>
    </div>
  </main>

  <?php require __DIR__ . '/partials/site-footer.php'; ?>
</body>
</html>
