<?php

declare(strict_types=1);

echo "Checking for BOM and whitespace issues in PHP files...\n\n";

$dirs = [
    __DIR__ . '/../src',
    __DIR__ . '/../public',
    __DIR__ . '/../api',
    __DIR__ . '/../config',
];

$issues = [];

foreach ($dirs as $dir) {
    if (!is_dir($dir)) {
        continue;
    }

    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($dir, RecursiveDirectoryIterator::SKIP_DOTS)
    );

    foreach ($files as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        $path = $file->getPathname();
        $content = file_get_contents($path);

        if ($content === false) {
            continue;
        }

        $problems = [];

        // Check for BOM
        if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
            $problems[] = "Has UTF-8 BOM";
        }

        // Check for whitespace before opening tag
        $openTag = '<' . '?php';
        if (!preg_match('/^<\?php/', $content)) {
            if (preg_match('/^(\s+)<\?php/', $content, $matches)) {
                $whitespace = str_replace(["\r", "\n", "\t", " "],
                    ["\\r", "\\n", "\\t", "·"],
                    $matches[1]);
                $problems[] = "Whitespace before opening tag: " . $whitespace;
            }
        }

        // Check for whitespace after closing tag
        if (preg_match('/\?>\s+$/', $content)) {
            $problems[] = "Whitespace after closing tag";
        }

        // Check for closing tag at end (not recommended)
        if (preg_match('/\?>[\r\n]*$/', $content)) {
            $problems[] = "Has closing tag (should be removed)";
        }

        if (!empty($problems)) {
            $issues[$path] = $problems;
        }
    }
}

if (empty($issues)) {
    echo "✓ No issues found!\n";
} else {
    echo "Found issues in " . count($issues) . " files:\n\n";

    foreach ($issues as $file => $problems) {
        echo "FILE: " . $file . "\n";
        foreach ($problems as $problem) {
            echo "  - " . $problem . "\n";
        }
        echo "\n";
    }

    echo "\nTo fix these issues:\n";
    echo "1. Remove any whitespace before opening tags\n";
    echo "2. Remove UTF-8 BOM (save file as UTF-8 without BOM)\n";
    echo "3. Remove closing tags at the end of PHP-only files\n";
    echo "4. Remove any whitespace after closing tags\n";
}
