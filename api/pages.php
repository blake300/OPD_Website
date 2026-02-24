<?php

declare(strict_types=1);

require_once __DIR__ . '/../src/auth.php';
require_once __DIR__ . '/../src/api_helpers.php';

// Require admin/manager role
$user = opd_require_role(['admin', 'manager']);

$method = $_SERVER['REQUEST_METHOD'];
$pdo = opd_db();

// GET - List pages or get single page
if ($method === 'GET') {
    $pageId = $_GET['id'] ?? null;

    if ($pageId) {
        // Get single page with sections
        $stmt = $pdo->prepare('SELECT * FROM pages WHERE id = ? LIMIT 1');
        $stmt->execute([$pageId]);
        $page = $stmt->fetch();

        if (!$page) {
            opd_json_response(['error' => 'Page not found'], 404);
        }

        // Get sections
        $sectionsStmt = $pdo->prepare('SELECT * FROM page_sections WHERE pageId = ? ORDER BY sortOrder ASC');
        $sectionsStmt->execute([$pageId]);
        $sections = $sectionsStmt->fetchAll();

        // Parse JSON content for each section
        foreach ($sections as &$section) {
            if (!empty($section['content'])) {
                $section['content'] = json_decode($section['content'], true);
            }
        }

        $page['sections'] = $sections;
        opd_json_response($page);
    }

    // List all pages
    $stmt = $pdo->query('SELECT id, slug, title, template, status, updatedAt FROM pages ORDER BY updatedAt DESC');
    $pages = $stmt->fetchAll();
    opd_json_response(['pages' => $pages]);
}

// POST - Create or update page
if ($method === 'POST') {
    opd_require_csrf();
    $data = opd_read_json();

    $pageId = $data['id'] ?? null;
    $title = trim((string) ($data['title'] ?? ''));
    $slug = trim((string) ($data['slug'] ?? ''));
    $template = $data['template'] ?? 'custom';
    $status = $data['status'] ?? 'draft';
    $metaDescription = $data['metaDescription'] ?? '';
    $sections = $data['sections'] ?? [];

    if ($title === '') {
        opd_json_response(['error' => 'Title is required'], 400);
    }

    if ($slug === '') {
        // Generate slug from title
        $slug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', $title));
        $slug = trim($slug, '-');
    }

    // Validate slug format
    if (!preg_match('/^[a-z0-9-]+$/', $slug)) {
        opd_json_response(['error' => 'Invalid page address. Use only lowercase letters, numbers, and hyphens.'], 400);
    }

    $now = gmdate('Y-m-d H:i:s');

    if ($pageId) {
        // Update existing page
        $checkSlug = $pdo->prepare('SELECT id FROM pages WHERE slug = ? AND id != ? LIMIT 1');
        $checkSlug->execute([$slug, $pageId]);
        if ($checkSlug->fetch()) {
            opd_json_response(['error' => 'A page with this address already exists'], 400);
        }

        $update = $pdo->prepare(
            'UPDATE pages SET title = ?, slug = ?, template = ?, status = ?, metaDescription = ?, updatedAt = ? WHERE id = ?'
        );
        $update->execute([$title, $slug, $template, $status, $metaDescription, $now, $pageId]);

        // Delete existing sections and re-create
        $pdo->prepare('DELETE FROM page_sections WHERE pageId = ?')->execute([$pageId]);
    } else {
        // Create new page
        $checkSlug = $pdo->prepare('SELECT id FROM pages WHERE slug = ? LIMIT 1');
        $checkSlug->execute([$slug]);
        if ($checkSlug->fetch()) {
            opd_json_response(['error' => 'A page with this address already exists'], 400);
        }

        $pageId = opd_generate_id('page');
        $insert = $pdo->prepare(
            'INSERT INTO pages (id, title, slug, template, status, metaDescription, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?, ?)'
        );
        $insert->execute([$pageId, $title, $slug, $template, $status, $metaDescription, $now, $now]);
    }

    // Insert sections
    $sectionStmt = $pdo->prepare(
        'INSERT INTO page_sections (id, pageId, sectionType, content, sortOrder, createdAt, updatedAt) VALUES (?, ?, ?, ?, ?, ?, ?)'
    );

    foreach ($sections as $index => $section) {
        $sectionId = opd_generate_id('sect');
        $sectionType = $section['type'] ?? 'text';
        $content = json_encode($section['content'] ?? []);
        $sectionStmt->execute([$sectionId, $pageId, $sectionType, $content, $index, $now, $now]);
    }

    opd_json_response(['ok' => true, 'id' => $pageId, 'slug' => $slug]);
}

// DELETE - Delete page
if ($method === 'DELETE') {
    opd_require_csrf();
    $data = opd_read_json();
    $pageId = $data['id'] ?? null;

    if (!$pageId) {
        opd_json_response(['error' => 'Page ID is required'], 400);
    }

    // Sections will be deleted via CASCADE
    $pdo->prepare('DELETE FROM pages WHERE id = ?')->execute([$pageId]);
    opd_json_response(['ok' => true]);
}

opd_json_response(['error' => 'Method not allowed'], 405);
