<?php
/**
 * SKU Redesign Script
 * Reads parent and variation CSVs, assigns new short SKUs, writes back.
 *
 * IMPORTANT: This script must be run from the original (unmodified) CSV files.
 * It reads the originals from git first, then writes both files.
 */

$parentFile = __DIR__ . '/../Plan/products-variable-simple_A.csv';
$variationFile = __DIR__ . '/../Plan/products-variations_a.csv';

// ─── STEP 1: Read parents, build ID→new SKU map ───

$parentRows = [];
$parentHeader = null;
if (($fh = fopen($parentFile, 'r')) !== false) {
    $parentHeader = fgetcsv($fh);
    while (($row = fgetcsv($fh)) !== false) {
        $parentRows[] = $row;
    }
    fclose($fh);
}
echo "Read " . count($parentRows) . " parent rows\n";

// Group parents by their group code (col index 3)
$groupBuckets = []; // group_upper => [row_index, ...]
foreach ($parentRows as $i => $row) {
    $grp = strtoupper(trim($row[3] ?? ''));
    if ($grp === '') $grp = 'USD';
    $groupBuckets[$grp][] = $i;
}

// Sort group names for deterministic ordering
ksort($groupBuckets);

// Assign new SKUs
$idToNewSku = [];   // product ID => new SKU
$oldToNewSku = [];  // old SKU => new SKU
$newSkuSet = [];    // track uniqueness

foreach ($groupBuckets as $grp => $indices) {
    // Pad group to 3 chars
    $grpCode = str_pad(strtoupper($grp), 3, 'X', STR_PAD_RIGHT);
    $grpCode = substr($grpCode, 0, 3);

    $seq = 1;
    foreach ($indices as $idx) {
        $newSku = $grpCode . str_pad($seq, 3, '0', STR_PAD_LEFT);
        $oldSku = $parentRows[$idx][1];
        $productId = $parentRows[$idx][0];

        $idToNewSku[$productId] = $newSku;
        $oldToNewSku[$oldSku] = $newSku;
        $parentRows[$idx][1] = $newSku;
        $newSkuSet[$newSku] = true;

        $seq++;
    }
}

echo "Assigned " . count($idToNewSku) . " parent SKUs\n";

// ─── STEP 2: Write parents file ───

$fh = fopen($parentFile, 'w');
fputcsv($fh, $parentHeader);
foreach ($parentRows as $row) {
    fputcsv($fh, $row);
}
fclose($fh);
echo "Wrote parents file\n";

// ─── STEP 3: Read variations ───

$varRows = [];
$varHeader = null;
if (($fh = fopen($variationFile, 'r')) !== false) {
    $varHeader = fgetcsv($fh);
    while (($row = fgetcsv($fh)) !== false) {
        $varRows[] = $row;
    }
    fclose($fh);
}
echo "Read " . count($varRows) . " variation rows\n";

// ─── STEP 4: Generate variation SKUs ───

// Group variations by parent ID for collision detection
$varsByParent = [];
foreach ($varRows as $i => $row) {
    $parentRef = trim($row[14] ?? '');
    $parentId = str_replace('id:', '', $parentRef);
    $varsByParent[$parentId][] = $i;
}

// Build orphan parent SKU map - for parents not in the parent file
$orphanSeq = [];
function getOrphanSku($parentId) {
    global $orphanSeq, $idToNewSku;
    if (isset($idToNewSku[$parentId])) return $idToNewSku[$parentId];

    // Generate a stable orphan SKU using ORP group
    if (!isset($orphanSeq[$parentId])) {
        $orphanSeq[$parentId] = 'ORP' . str_pad(count($orphanSeq) + 1, 3, '0', STR_PAD_LEFT);
    }
    return $orphanSeq[$parentId];
}

/**
 * Convert a fractional size string to a compact code
 */
function sizeToCode($s) {
    $s = trim($s);
    // Remove trailing quote marks and whitespace
    $s = rtrim($s, '"\' ');
    $s = trim($s);
    // Remove \r\n artifacts
    $s = preg_replace('/[\r\n\\\\rn]+/', '', $s);
    $s = trim($s);

    $map = [
        '1/4' => '14',
        '3/8' => '38',
        '1/2' => '12',
        '5/8' => '58',
        '3/4' => '34',
        '7/8' => '78',
        '1' => '1',
        '1 1/4' => '114',
        '1 1/2' => '112',
        '1 3/4' => '134',
        '2' => '2',
        '2 1/2' => '212',
        '2 3/8' => '238',
        '2 7/8' => '278',
        '3' => '3',
        '4' => '4',
        '6' => '6',
        '8' => '8',
        '9' => '9',
        '10' => '10',
        '12' => '12X',
        '14' => '14X',
        '16' => '16X',
        '1 1/8' => '118',
        '1 3/8' => '138',
        '11/16' => '1116',
        '13/16' => '1316',
        '15/16' => '1516',
        '9/16' => '916',
    ];

    if (isset($map[$s])) return $map[$s];
    $stripped = rtrim($s, '"');
    if (isset($map[$stripped])) return $map[$stripped];
    return null;
}

/**
 * Extract descriptor from variation description (col 4, index 4)
 */
function extractDescriptor($desc, $name) {
    $desc = trim($desc);
    // Clean up HTML and whitespace
    $desc = strip_tags($desc);
    $desc = trim($desc);
    // Normalize all whitespace including \r\n and literal \n
    $desc = str_replace(["\r\n", "\r", "\n", "\\r\\n", "\\n", "\\r"], ' ', $desc);
    $desc = preg_replace('/\s+/', ' ', $desc);
    $desc = trim($desc, '"\' ');

    // Check for "used" suffix
    $isUsed = false;
    if (preg_match('/[,\s-]+used\s*$/i', $desc)) {
        $isUsed = true;
        $desc = preg_replace('/[,\s-]+used\s*$/i', '', $desc);
        $desc = trim($desc, ', ');
    }

    $usuf = $isUsed ? 'U' : '';

    // ─── Pump specs like "9-3-0-0", "14-3-0-0", "6-3-2-2" ───
    if (preg_match('/^(\d+)-(\d+)-(\d+)-(\d+)$/', $desc, $m)) {
        $code = $m[1] . $m[2] . $m[3] . $m[4];
        return substr($code . $usuf, 0, 8);
    }

    // ─── Barrel specs: "X" x Y" x Z' ─── e.g., 2" x 1 1/4" x 6'
    // Use compact format without dashes: {seat}{plunger}{length}
    // e.g., 2x114x6 or 212x134x4
    if (preg_match('/^([\d\s\/]+)"?\s*x\s*([\d\s\/]+)"?\s*x\s*([\d]+)[\'′]?\s*$/i', $desc, $m)) {
        $seat = sizeToCode(trim($m[1]));
        $plunger = sizeToCode(trim($m[2]));
        $length = trim($m[3]);
        if ($seat && $plunger) {
            // Remove the X suffix added for pipe sizes 12/14/16 when used as barrel dims
            $seat = str_replace('X', '', $seat);
            $plunger = str_replace('X', '', $plunger);
            $code = $seat . $plunger . $length;
            return substr($code . $usuf, 0, 8);
        }
    }

    // ─── Ball and Seat: "X" Alloy/Carbide/SS Seat, Y" Alloy/Cobalt/SS Ball ───
    if (preg_match('/([\d\s\/]+)"?\s*(Alloy|Carbide|Cobalt|SS|Stainless)\s*Seat/i', $desc, $seatMatch) &&
        preg_match('/([\d\s\/]+)"?\s*(Alloy|Carbide|Cobalt|SS|Stainless)\s*Ball/i', $desc, $ballMatch)) {
        $seatSize = sizeToCode(trim($seatMatch[1]));
        $seatMat = strtoupper(substr(trim($seatMatch[2]), 0, 1));
        if (strtoupper(trim($seatMatch[2])) === 'SS' || strtoupper(trim($seatMatch[2])) === 'STAINLESS') $seatMat = 'S';
        $ballSize = sizeToCode(trim($ballMatch[1]));
        $ballMat = strtoupper(substr(trim($ballMatch[2]), 0, 1));
        if (strtoupper(trim($ballMatch[2])) === 'SS' || strtoupper(trim($ballMatch[2])) === 'STAINLESS') $ballMat = 'S';
        if ($seatSize && $ballSize) {
            $code = $seatSize . $seatMat . $ballSize . $ballMat;
            return substr($code . $usuf, 0, 8);
        }
    }

    // ─── Volume: "55 gal", "2 gal", "1 qt", "32 oz" ───
    if (preg_match('/^(\d+)\s*gal(?:lon)?s?\s*$/i', $desc, $m)) {
        return substr($m[1] . 'G' . $usuf, 0, 8);
    }
    if (preg_match('/^(\d+)\s*qt(?:uart)?s?\s*$/i', $desc, $m)) {
        return substr($m[1] . 'QT' . $usuf, 0, 8);
    }
    if (preg_match('/^(\d+)\s*oz\s*$/i', $desc, $m)) {
        return substr($m[1] . 'OZ' . $usuf, 0, 8);
    }
    if (preg_match('/^(\d+)\s*quart\s*$/i', $desc, $m)) {
        return substr($m[1] . 'QT' . $usuf, 0, 8);
    }

    // ─── Counts: "1 Acid Stick", "Box of 10", "Pail of 50", "10 Box", "1 Tube", "10 Tubes" ───
    if (preg_match('/^(\d+)\s*(?:14oz\s+)?(?:Acid\s+)?(?:Stick|Tube|Box|Pack|Pail|Bag|Filter|Roll|Sheet|Pad)s?\s*$/i', $desc, $m)) {
        return substr($m[1] . 'PK' . $usuf, 0, 8);
    }
    if (preg_match('/^(?:Box|Pail|Pack|Bag|Roll)\s+of\s+(\d+)\s*(?:Acid\s+)?(?:Stick|Tube|Filter|Sheet|Pad)?s?\s*$/i', $desc, $m)) {
        return substr($m[1] . 'PK' . $usuf, 0, 8);
    }

    // ─── Foot lengths: "25'", "50'", "100'", "500'" (also with extra text like "w/ ..." ) ───
    if (preg_match("/^(\d+)['\x{2032}]/u", $desc, $m) || preg_match("/^(\d+)'\s/", $desc, $m)) {
        return substr($m[1] . 'FT' . $usuf, 0, 8);
    }

    // ─── Horsepower: "1 HP", "3 HP" etc ───
    if (preg_match('/^(\d+)\s*HP\s*$/i', $desc, $m)) {
        return substr($m[1] . 'HP' . $usuf, 0, 8);
    }

    // ─── Weight: "40 lb" ───
    if (preg_match('/^(\d+)\s*(?:lb|lbs)\s*$/i', $desc, $m)) {
        return substr($m[1] . 'LB' . $usuf, 0, 8);
    }

    // ─── Inch lengths (tools): '12 Inch', '18 Inch' ───
    if (preg_match('/^(\d+)\s*[Ii]nch\s*$/i', $desc, $m)) {
        return substr($m[1] . $usuf, 0, 8);
    }

    // ─── "size x size" patterns (fittings) ───
    // e.g., '3/8" m x 1/4" m', '1" x 6"', '3/8"m x 1/4"m', with optional grv x thd suffix
    if (preg_match('/^([\d\s\/]+)"?\s*(?:fm|m)?\s*x\s*([\d\s\/]+)"?\s*(?:fm|m)?\s*(?:,.*)?$/i', $desc, $m)) {
        $s1 = sizeToCode(trim($m[1]));
        $s2 = sizeToCode(trim($m[2]));
        if ($s1 && $s2) {
            // Strip X suffix for fitting sizes (X was for disambiguating pipe dims 12/14/16)
            $s1 = str_replace('X', '', $s1);
            $s2 = str_replace('X', '', $s2);
            return substr($s1 . 'x' . $s2 . $usuf, 0, 8);
        }
    }

    // ─── Single pipe/fitting size: '1"', '3/4"', '1 1/4"' ───
    $cleanDesc = trim(preg_replace('/[\s\n\r]+$/', '', $desc));
    $cleanDesc = rtrim($cleanDesc, '"');
    $cleanDesc = trim($cleanDesc);
    $cleanDesc = preg_replace('/,.*$/', '', $cleanDesc);
    $cleanDesc = trim($cleanDesc);
    $singleSize = sizeToCode($cleanDesc);
    if ($singleSize) {
        // Strip X suffix
        $singleSize = str_replace('X', '', $singleSize);
        return substr($singleSize . $usuf, 0, 8);
    }

    // ─── "size, pressure" patterns: '1", 2000 psi', '2", 1000 psi' ───
    if (preg_match('/^([\d\s\/]+)"?\s*(?:npt)?\s*,\s*(\d+)\s*psi\s*$/i', $desc, $m)) {
        $sz = sizeToCode(trim($m[1]));
        $psi = trim($m[2]);
        $psiMap = ['1000'=>'1K', '1500'=>'15H', '2000'=>'2K', '2500'=>'25H', '3000'=>'3K'];
        $psiCode = $psiMap[$psi] ?? $psi;
        if ($sz) {
            $sz = str_replace('X', '', $sz);
            return substr($sz . $psiCode . $usuf, 0, 8);
        }
    }

    // ─── "size, heavy duty" ───
    if (preg_match('/^([\d\s\/]+)"?\s*,\s*heavy\s*duty\s*$/i', $desc, $m)) {
        $sz = sizeToCode(trim($m[1]));
        if ($sz) {
            $sz = str_replace('X', '', $sz);
            return substr($sz . 'HD' . $usuf, 0, 8);
        }
    }
    if (preg_match('/^(\d+),?\s*heavy\s*duty\s*$/i', $desc, $m)) {
        $sz = sizeToCode(trim($m[1]));
        if ($sz) {
            $sz = str_replace('X', '', $sz);
            return substr($sz . 'HD' . $usuf, 0, 8);
        }
    }

    // ─── Autobailer keywords ───
    if (preg_match('/Control\s*Panel\s*w\/\s*Cellular/i', $desc)) return 'PNLM' . $usuf;
    if (preg_match('/Control\s*Panel/i', $desc)) return 'PNL' . $usuf;
    if (preg_match('/Full[\s-]*time/i', $desc)) return 'FT' . $usuf;
    if (preg_match('/Part[\s-]*time/i', $desc)) return 'PT' . $usuf;

    // ─── Model numbers / short descriptors: R6, R8, TW1, LA603, etc. ───
    if (preg_match('/^([A-Z0-9]{2,6})\s*(?:type)?\s*$/i', $desc, $m)) {
        return substr(strtoupper($m[1]) . $usuf, 0, 8);
    }

    // ─── Fuse amps: "1 amp", "3 amp", etc ───
    if (preg_match('/^(\d+)\s*(?:amp|amps|a)\s*$/i', $desc, $m)) {
        return substr($m[1] . 'A' . $usuf, 0, 8);
    }

    // ─── Gauge face sizes: 2 1/2" Face, 4" Face ───
    if (preg_match('/([\d\s\/]+)"?\s*Face/i', $desc, $m)) {
        $sz = sizeToCode(trim($m[1]));
        if ($sz) {
            $sz = str_replace('X', '', $sz);
            return substr($sz . 'F' . $usuf, 0, 8);
        }
    }

    // ─── Color variants ───
    $colorMap = [
        'red' => 'RED', 'blue' => 'BLU', 'black' => 'BLK', 'white' => 'WHT',
        'green' => 'GRN', 'yellow' => 'YEL', 'purple' => 'PUR', 'orange' => 'ORG',
    ];
    $descLower = strtolower($desc);
    foreach ($colorMap as $color => $code) {
        if (strpos($descLower, $color) !== false) {
            return substr($code . $usuf, 0, 8);
        }
    }

    // ─── Seating cup types: "+30", "+45", "+70", "#100" ───
    if (preg_match('/^[+#](\d+)/', $desc, $m)) {
        return substr($m[1] . $usuf, 0, 8);
    }

    // ─── Rod Back Off Tool ───
    if (preg_match('/Rod\s*Back\s*Off/i', $desc)) return 'RBOT' . $usuf;

    // ─── Wellhead specs ───
    if (preg_match('/Model\s+(\w+)/i', $desc, $m)) {
        $model = strtoupper($m[1]);
        if (preg_match('/(\d+)\s*psi/i', $desc, $pm)) {
            return substr($model . $pm[1], 0, 8);
        }
        return substr($model . $usuf, 0, 8);
    }

    // ─── Fallback: try to extract meaningful short text ───
    $clean = preg_replace('/[^A-Za-z0-9\s]/', '', $desc);
    $clean = trim($clean);
    $words = preg_split('/\s+/', $clean);
    $code = '';
    foreach ($words as $w) {
        $w = strtoupper($w);
        if (strlen($code) + strlen($w) <= 8) {
            $code .= $w;
        } else {
            $code .= substr($w, 0, 8 - strlen($code));
            break;
        }
    }
    if ($code === '') $code = 'VAR';
    if ($isUsed && strlen($code) < 8) $code .= 'U';
    return substr($code, 0, 8);
}

// Process variations by parent group to handle collisions
$allVarSkus = [];
$orphanCount = 0;

foreach ($varsByParent as $parentId => $indices) {
    $parentSku = $idToNewSku[$parentId] ?? null;
    if (!$parentSku) {
        $parentSku = getOrphanSku($parentId);
        $orphanCount += count($indices);
    }

    // Generate descriptors for all variations under this parent
    $descriptors = [];
    foreach ($indices as $idx) {
        $row = $varRows[$idx];
        $desc = $row[4] ?? '';
        $name = $row[3] ?? '';
        $descriptor = extractDescriptor($desc, $name);
        $descriptors[$idx] = $descriptor;
    }

    // Check for collisions within this parent
    $descCounts = array_count_values($descriptors);
    $descSeen = [];

    foreach ($indices as $idx) {
        $d = $descriptors[$idx];
        if ($descCounts[$d] > 1) {
            if (!isset($descSeen[$d])) $descSeen[$d] = 0;
            $suffix = chr(65 + $descSeen[$d]); // A, B, C...
            $descSeen[$d]++;
            $finalDesc = substr($d, 0, 7) . $suffix;
        } else {
            $finalDesc = $d;
        }

        $newSku = $parentSku . '-' . $finalDesc;

        // Ensure total max 15 chars
        if (strlen($newSku) > 15) {
            $maxDesc = 15 - strlen($parentSku) - 1;
            $finalDesc = substr($finalDesc, 0, $maxDesc);
            $newSku = $parentSku . '-' . $finalDesc;
        }

        $varRows[$idx][1] = $newSku;
        $allVarSkus[$newSku] = $idx;
    }
}

echo "Orphan variations (parent not in file): $orphanCount\n";

// ─── STEP 5: Write variations file ───

$fh = fopen($variationFile, 'w');
fputcsv($fh, $varHeader);
foreach ($varRows as $row) {
    fputcsv($fh, $row);
}
fclose($fh);
echo "Wrote variations file\n";

// ─── STEP 6: Verify uniqueness ───

$allSkus = [];
$dupes = [];
$empties = 0;

foreach ($parentRows as $row) {
    $sku = $row[1];
    if (empty($sku)) { $empties++; continue; }
    if (isset($allSkus[$sku])) $dupes[] = $sku;
    $allSkus[$sku] = true;
}

foreach ($varRows as $row) {
    $sku = $row[1];
    if (empty($sku)) { $empties++; continue; }
    if (isset($allSkus[$sku])) $dupes[] = $sku;
    $allSkus[$sku] = true;
}

echo "\n=== VERIFICATION ===\n";
echo "Total unique SKUs: " . count($allSkus) . "\n";
echo "Duplicates: " . count($dupes) . "\n";
if (count($dupes) > 0) {
    echo "Duplicate SKUs:\n";
    foreach (array_unique($dupes) as $d) {
        echo "  - $d\n";
    }
}
echo "Empty SKUs: $empties\n";

// Check max lengths
$maxParent = 0;
$maxVar = 0;
foreach ($parentRows as $row) {
    $maxParent = max($maxParent, strlen($row[1]));
}
foreach ($varRows as $row) {
    $maxVar = max($maxVar, strlen($row[1]));
}
echo "Max parent SKU length: $maxParent\n";
echo "Max variation SKU length: $maxVar\n";

// Show some examples
echo "\n=== SAMPLE PARENT SKUs ===\n";
$shown = 0;
foreach ($parentRows as $row) {
    if ($shown >= 20) break;
    echo "  " . str_pad($row[1], 8) . " => " . $row[5] . "\n";
    $shown++;
}

echo "\n=== SAMPLE VARIATION SKUs ===\n";
$shown = 0;
foreach ($varRows as $row) {
    if ($shown >= 40) break;
    echo "  " . str_pad($row[1], 16) . " (" . substr($row[4] ?? '', 0, 50) . ")\n";
    $shown++;
}

// Show barrel examples
echo "\n=== BARREL VARIATION SKUs ===\n";
foreach ($varRows as $row) {
    if (preg_match('/Barrel/', $row[3] ?? '')) {
        echo "  " . str_pad($row[1], 16) . " (" . substr($row[4] ?? '', 0, 50) . ")\n";
    }
}

echo "\nDone!\n";
