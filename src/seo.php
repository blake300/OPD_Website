<?php

declare(strict_types=1);

require_once __DIR__ . '/api_helpers.php';

/**
 * Render SEO meta tags for the <head> section.
 *
 * @param array $opts {
 *   title: string,        Page title (without brand suffix)
 *   description: string,  Meta description (max ~155 chars)
 *   canonical: string,    Canonical URL path (e.g. /products.php)
 *   ogType: string,       Open Graph type (default: website)
 *   ogImage: string,      OG image URL
 *   noindex: bool,        Add noindex directive
 *   jsonLd: array|null,   JSON-LD structured data
 * }
 */
function opd_seo_meta(array $opts = []): void
{
    $siteName = opd_site_name();
    $baseUrl = opd_site_url();

    $title = $opts['title'] ?? $siteName;
    $description = $opts['description'] ?? 'Oilfield equipment, tools, parts, and supplies. Field-ready procurement with nationwide shipping and Oklahoma same-day delivery.';
    $canonical = $opts['canonical'] ?? ($_SERVER['REQUEST_URI'] ?? '/');
    $ogType = $opts['ogType'] ?? 'website';
    $ogImage = $opts['ogImage'] ?? '/assets/Oil-Patch-Depot-Logo_New.jpg';
    $noindex = $opts['noindex'] ?? false;
    $jsonLd = $opts['jsonLd'] ?? null;

    // Clean canonical - remove query params for listing pages if needed
    $canonicalUrl = $baseUrl . parse_url($canonical, PHP_URL_PATH);
    $query = parse_url($canonical, PHP_URL_QUERY);
    if ($query) {
        $canonicalUrl .= '?' . $query;
    }

    // Ensure absolute OG image URL
    if ($ogImage && strpos($ogImage, 'http') !== 0) {
        $ogImage = $baseUrl . $ogImage;
    }

    // Truncate description
    if (strlen($description) > 160) {
        $description = substr($description, 0, 157) . '...';
    }

    // Meta description
    echo '<meta name="description" content="' . htmlspecialchars($description, ENT_QUOTES) . '" />' . "\n";

    // Canonical
    echo '  <link rel="canonical" href="' . htmlspecialchars($canonicalUrl, ENT_QUOTES) . '" />' . "\n";

    // Robots
    if ($noindex) {
        echo '  <meta name="robots" content="noindex, nofollow" />' . "\n";
    }

    // Open Graph
    echo '  <meta property="og:type" content="' . htmlspecialchars($ogType, ENT_QUOTES) . '" />' . "\n";
    echo '  <meta property="og:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '" />' . "\n";
    echo '  <meta property="og:description" content="' . htmlspecialchars($description, ENT_QUOTES) . '" />' . "\n";
    echo '  <meta property="og:url" content="' . htmlspecialchars($canonicalUrl, ENT_QUOTES) . '" />' . "\n";
    echo '  <meta property="og:site_name" content="' . htmlspecialchars($siteName, ENT_QUOTES) . '" />' . "\n";
    if ($ogImage) {
        echo '  <meta property="og:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES) . '" />' . "\n";
    }

    // Twitter Card
    echo '  <meta name="twitter:card" content="summary_large_image" />' . "\n";
    echo '  <meta name="twitter:title" content="' . htmlspecialchars($title, ENT_QUOTES) . '" />' . "\n";
    echo '  <meta name="twitter:description" content="' . htmlspecialchars($description, ENT_QUOTES) . '" />' . "\n";
    if ($ogImage) {
        echo '  <meta name="twitter:image" content="' . htmlspecialchars($ogImage, ENT_QUOTES) . '" />' . "\n";
    }

    // Favicon
    echo '  <link rel="icon" type="image/jpeg" href="/assets/Oil-Patch-Depot-Logo_New.jpg" />' . "\n";

    // JSON-LD
    if ($jsonLd) {
        echo '  <script type="application/ld+json">' . json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT) . '</script>' . "\n";
    }
}

/**
 * Get the site's base URL.
 */
function opd_site_url(): string
{
    $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
    $host = $_SERVER['HTTP_HOST'] ?? 'oilpatchdepot.com';
    return $scheme . '://' . $host;
}

/**
 * Build Organization JSON-LD (for homepage).
 */
function opd_organization_jsonld(): array
{
    return [
        '@context' => 'https://schema.org',
        '@type' => 'Organization',
        'name' => opd_site_name(),
        'url' => opd_site_url(),
        'logo' => opd_site_url() . '/assets/Oil-Patch-Depot-Logo_New.jpg',
        'description' => 'Oilfield equipment, tools, parts, and supplies with nationwide shipping.',
        'contactPoint' => [
            '@type' => 'ContactPoint',
            'contactType' => 'sales',
            'availableLanguage' => 'English'
        ]
    ];
}

/**
 * Build Product JSON-LD.
 */
function opd_product_jsonld(array $product, string $baseUrl): array
{
    $data = [
        '@context' => 'https://schema.org',
        '@type' => 'Product',
        'name' => $product['name'] ?? '',
        'description' => strip_tags((string) ($product['shortDescription'] ?? $product['longDescription'] ?? '')),
        'sku' => $product['sku'] ?? '',
        'url' => $baseUrl . '/product.php?id=' . urlencode($product['id'] ?? ''),
        'offers' => [
            '@type' => 'Offer',
            'priceCurrency' => 'USD',
            'price' => number_format((float) ($product['price'] ?? 0), 2, '.', ''),
            'availability' => 'https://schema.org/InStock',
            'seller' => [
                '@type' => 'Organization',
                'name' => opd_site_name()
            ]
        ]
    ];

    if (!empty($product['imageUrl'])) {
        $img = $product['imageUrl'];
        if (strpos($img, 'http') !== 0) $img = $baseUrl . $img;
        $data['image'] = $img;
    }

    $inv = (int) ($product['inventory'] ?? 0);
    if ($inv <= 0 && empty($product['allowBackorders'])) {
        $data['offers']['availability'] = 'https://schema.org/OutOfStock';
    }

    return $data;
}

/**
 * Build BreadcrumbList JSON-LD.
 */
function opd_breadcrumb_jsonld(array $crumbs, string $baseUrl): array
{
    $items = [];
    foreach ($crumbs as $i => $crumb) {
        $items[] = [
            '@type' => 'ListItem',
            'position' => $i + 1,
            'name' => $crumb['name'],
            'item' => $baseUrl . $crumb['url']
        ];
    }
    return [
        '@context' => 'https://schema.org',
        '@type' => 'BreadcrumbList',
        'itemListElement' => $items
    ];
}
