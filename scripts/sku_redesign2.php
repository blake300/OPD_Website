<?php
/**
 * SKU Redesign Script v2
 * Generates descriptive, human-readable SKUs with category prefix.
 * Format: {CAT}-{GRP}-{ABBR} for parents, {PARENT_SKU}-{VDESC} for variations.
 */

$parentFile = __DIR__ . '/../Plan/products-variable-simple_A.csv';
$variationFile = __DIR__ . '/../Plan/products-variations_a.csv';

// ── Category code mapping ──
$catMap = [
    'aub' => 'A',
    'prt' => 'P',
    'tol' => 'T',
    'srv' => 'V',
    'sup' => 'S',
    'usd' => 'U',
    ''    => 'U',
];

// ── Step 1: Read parents CSV ──
echo "Reading parent CSV...\n";
$parentRows = readCsvFile($parentFile);
$parentHeader = array_shift($parentRows);
echo "  Found " . count($parentRows) . " parent rows\n";

// ── Step 2: Read variations CSV ──
echo "Reading variation CSV...\n";
$varRows = readCsvFile($variationFile);
$varHeader = array_shift($varRows);
echo "  Found " . count($varRows) . " variation rows\n";

// ── Step 3: Build parent SKU map ──
// Track used SKUs per CAT-GRP to avoid collisions
$usedSkus = [];
$parentIdToNewSku = []; // id => new SKU
$parentOldToNewSku = []; // old SKU => new SKU

echo "\nGenerating parent SKUs...\n";

foreach ($parentRows as $idx => &$row) {
    $id = trim($row[0]);
    $oldSku = trim($row[1]);
    $catRaw = strtolower(trim($row[2]));
    $grpRaw = strtolower(trim($row[3]));
    $name = trim($row[5], " \t\n\r\0\x0B\"");

    $cat = isset($catMap[$catRaw]) ? $catMap[$catRaw] : 'USD';
    $grp = strtoupper($grpRaw);
    if (empty($grp)) $grp = 'EQP'; // Default group for items without one

    $abbr = generateParentAbbr($name, $cat, $grp);

    // Build candidate SKU
    $candidate = "$cat-$grp-$abbr";

    // Collision detection
    if (isset($usedSkus[$candidate])) {
        $n = 2;
        while (isset($usedSkus["$cat-$grp-$abbr$n"])) {
            $n++;
        }
        $abbr = "$abbr$n";
        $candidate = "$cat-$grp-$abbr";
    }

    // Length check
    if (strlen($candidate) > 16) {
        // Trim abbr to fit
        $maxAbbr = 16 - strlen("$cat-$grp-");
        $abbr = substr($abbr, 0, $maxAbbr);
        $candidate = "$cat-$grp-$abbr";
        // Re-check collision after trim
        if (isset($usedSkus[$candidate])) {
            $n = 2;
            $base = substr($abbr, 0, $maxAbbr - 1);
            while (isset($usedSkus["$cat-$grp-$base$n"])) {
                $n++;
            }
            $candidate = "$cat-$grp-$base$n";
        }
    }

    $usedSkus[$candidate] = true;
    $parentIdToNewSku[$id] = $candidate;
    $parentOldToNewSku[$oldSku] = $candidate;

    $row[1] = $candidate;
}
unset($row);

echo "  Generated " . count($parentIdToNewSku) . " parent SKUs\n";

// ── Step 3b: Generate synthetic parent SKUs for orphan variations ──
// First pass: discover missing parent IDs and derive a synthetic parent SKU
$orphanParentMap = [
    // id => [cat, grp, name] - derive from first variation referencing it
    '5317' => ['SUP', 'OIL', 'JT8SB'],    // 15/50 Mystic JT8 Synthetic Blend
    '7133' => ['PRT', 'DHP', '20125RWT'],  // 20-125-RWT pump
    '7124' => ['PRT', 'DHP', '20150RWB'],  // 20-150-RWBC pump
    '7130' => ['PRT', 'DHP', '20175THC'],  // 20-175-THC pump
    '6206' => ['PRT', 'VLV', 'BVDIUS'],    // Ball Valve Ductile Iron US Made
    '2346' => ['PRT', 'MSC', 'BRB'],       // Brass Rod Box
    '6201' => ['PRT', 'VLV', 'CHKVBUS'],   // Check Valve Brass US Made
    '352'  => ['USD', 'EQP', 'SHELT'],     // Equipment Shelter
    '9402' => ['USD', 'MSC', 'UMISC'],     // Misc Used
    '9198' => ['SRV', 'SRV', 'PROC'],      // Procurement Service
    '6869' => ['SUP', 'MSC', 'SGATE'],     // Solar Gate Opener
    '309'  => ['TOL', 'WCH', 'TPWRN'],     // Telescopic Pipe Wrench
    '3978' => ['TOL', 'WCH', 'UWRN'],      // Universal Wrench
    '2400' => ['SUP', 'MSC', 'WPINS'],     // Water Proof Pipe Insulation
];

foreach ($orphanParentMap as $orphId => $info) {
    $synthSku = "{$info[0]}-{$info[1]}-{$info[2]}";
    if (!isset($usedSkus[$synthSku])) {
        $usedSkus[$synthSku] = true;
        $parentIdToNewSku[$orphId] = $synthSku;
        echo "  Synthetic parent: id:$orphId => $synthSku\n";
    }
}

// ── Step 4: Generate variation SKUs ──
echo "\nGenerating variation SKUs...\n";
$usedVarSkus = $usedSkus; // include parent SKUs to avoid cross-collision

foreach ($varRows as &$vrow) {
    $varId = trim($vrow[0]);
    $oldVarSku = trim($vrow[1]);
    $varName = trim($vrow[3], " \t\n\r\0\x0B\"");
    $varDesc = trim($vrow[4], " \t\n\r\0\x0B\"");
    // Parent column is index 14
    $parentRef = trim($vrow[14]);

    // Extract parent ID from "id:XXXX"
    $parentId = '';
    if (preg_match('/id:(\d+)/', $parentRef, $m)) {
        $parentId = $m[1];
    }

    $parentSku = isset($parentIdToNewSku[$parentId]) ? $parentIdToNewSku[$parentId] : 'ORPHAN';

    // Generate VDESC from variation description/name
    $vdesc = generateVarDesc($varName, $varDesc);

    $candidate = "$parentSku-$vdesc";

    // Collision
    if (isset($usedVarSkus[$candidate])) {
        $n = 2;
        while (isset($usedVarSkus["$parentSku-$vdesc$n"])) {
            $n++;
        }
        $vdesc = "$vdesc$n";
        $candidate = "$parentSku-$vdesc";
    }

    // Length check (max 25)
    if (strlen($candidate) > 25) {
        $maxVdesc = 25 - strlen("$parentSku-");
        $vdesc = substr($vdesc, 0, $maxVdesc);
        $candidate = "$parentSku-$vdesc";
        if (isset($usedVarSkus[$candidate])) {
            $n = 2;
            $base = substr($vdesc, 0, $maxVdesc - 1);
            while (isset($usedVarSkus["$parentSku-$base$n"])) {
                $n++;
            }
            $candidate = "$parentSku-$base$n";
        }
    }

    $usedVarSkus[$candidate] = true;
    $vrow[1] = $candidate;
}
unset($vrow);

echo "  Generated " . count($varRows) . " variation SKUs\n";

// ── Step 5: Write parent CSV ──
echo "\nWriting parent CSV...\n";
array_unshift($parentRows, $parentHeader);
writeCsvFile($parentFile, $parentRows);
echo "  Written: $parentFile\n";

// ── Step 6: Write variation CSV ──
echo "\nWriting variation CSV...\n";
array_unshift($varRows, $varHeader);
writeCsvFile($variationFile, $varRows);
echo "  Written: $variationFile\n";

// ── Step 7: Verification ──
echo "\n=== VERIFICATION ===\n";
$allSkus = array_keys($usedVarSkus);
$uniqueCount = count($allSkus);
$emptySkus = array_filter($allSkus, fn($s) => empty(trim($s)));
echo "Total unique SKUs (parent+variation): $uniqueCount\n";
echo "Empty SKUs: " . count($emptySkus) . "\n";

// Check for parent SKUs > 16
$overParent = 0;
foreach ($parentIdToNewSku as $sku) {
    if (strlen($sku) > 16) $overParent++;
}
echo "Parent SKUs > 16 chars: $overParent\n";

// Check for variation SKUs > 25
$overVar = 0;
array_shift($varRows); // remove header again for check
// Actually we already added header back, let's just count from usedVarSkus
$varSkuList = [];
foreach ($varRows as $vr) {
    if (isset($vr[1]) && $vr[1] !== $varHeader[1]) {
        $varSkuList[] = $vr[1];
    }
}
foreach ($varSkuList as $vs) {
    if (strlen($vs) > 25) $overVar++;
}
echo "Variation SKUs > 25 chars: $overVar\n";

// ── Step 8: Sample Output ──
echo "\n=== SAMPLE PARENT SKUs (first 20) ===\n";
$count = 0;
$shownParents = [];
// Re-read to get fresh data
array_shift($parentRows); // header
// Wait, we re-added header. Let's just use parentIdToNewSku
$i = 0;
foreach ($parentIdToNewSku as $pid => $psku) {
    if ($i >= 20) break;
    printf("  ID:%-6s => %s\n", $pid, $psku);
    $i++;
}

echo "\n=== SAMPLE VARIATION SKUs (first 30) ===\n";
$i = 0;
foreach ($varRows as $vr) {
    if ($i >= 30) break;
    if (!isset($vr[0]) || $vr[0] === 'ID') continue;
    printf("  ID:%-6s => %s\n", $vr[0], $vr[1]);
    $i++;
}

echo "\nDone!\n";


// ════════════════════════════════════════
// FUNCTIONS
// ════════════════════════════════════════

function readCsvFile(string $path): array {
    $rows = [];
    $fh = fopen($path, 'r');
    if (!$fh) die("Cannot open $path\n");
    while (($line = fgetcsv($fh)) !== false) {
        $rows[] = $line;
    }
    fclose($fh);
    return $rows;
}

function writeCsvFile(string $path, array $rows): void {
    $fh = fopen($path, 'w');
    if (!$fh) die("Cannot write $path\n");
    foreach ($rows as $row) {
        fputcsv($fh, $row);
    }
    fclose($fh);
}

/**
 * Generate a short abbreviation from a parent product name.
 */
function generateParentAbbr(string $name, string $cat, string $grp): string {
    // Normalize
    $n = str_replace(["\n", "\r", '"', "'"], '', $name);
    $n = trim($n);

    // ── Specific known product mappings ──
    $specificMap = [
        // AUB products
        'Autobailer Control Panel' => 'CPNL',
        'Autobailer Proximity Sensor' => 'PROX',
        'Autobailer Solenoid Valve' => 'SOLV',
        'Flow Switch' => 'FLSW',
        'Hand Held Control' => 'HHC',
        'AutoBailer Machine' => 'ABMCH',
        'Mobile Pumping Trailer' => 'MPTRL',
        'Autobailer Installation Service' => 'INST',
        'Autobailer Wireless Service' => 'WLSS',

        // Oils
        '15/40 - Mystic - JT8' => 'JT8',
        '80/90 - Mystic JT7' => 'JT7',
        '15/50 - Mystic - JT8 Synthetic Blend' => 'JT8SB',

        // Pump parts
        'Pump Anode - Complete' => 'PACMP',
        'Pump Anode - Housing' => 'PAHSG',
        'Pump Anode - Insert' => 'PAINS',
        'Polish Rod Clamp' => 'PRC',
        'Polish Rods' => 'PROD',
        'Seating Nipples' => 'STNIP',
        'Ball and Seat' => 'BNS',

        // Clamps
        'Hose Clamp - Worm Gear' => 'HCWG',

        // Tapes
        'Duct Tape' => 'DUCT',
        'Electrical Tape' => 'ELEC',
        'Teflon Tape' => 'TFLN',

        // Safety
        'Hard Hat' => 'HHAT',
        'Rubber Mallet' => 'RMAL',

        // Chemicals
        'Corrosion Inhibitor' => 'CINH',
        'Emulsion Breaker' => 'EBRK',

        // Quick connects
        'Quick Connect - Airline - Brass - Female' => 'QCABF',
        'Quick Connect - Airline - Brass - Male' => 'QCABM',
        'Quick Connect - Airline - Stainless - Female' => 'QCASF',
        'Splice - Airline' => 'SPLC',
        'Hose - Airline' => 'ALHOS',

        // Adapters
        'Adapter - Ell - 90 - Steel - 4,000 psi' => 'EL90S',
        'Adapter - Straight - Steel - 4,000 psi' => 'STRS',

        // Common tools
        'Aluminum Pipe Wrench' => 'APWRN',
        'Steel Pipe Wrench' => 'SPWRN',
        'BOLT CUTTER (INDUSTRIAL)' => 'BCUT',
        'Bolt Cutter (Industrial)' => 'BCUT',

        // Chemicals/compounds
        'Berryman Chemtool B-12' => 'B12',
        'BERRYMAN EZ Doz-It Penetrating Oil' => 'EZDOZ',
        'BESTOLIFE 2000 Copper Thread Compound' => 'BL2K',
        'BESTOLIFE Metal Free Thread Compound' => 'BLMF',
        'Aluminum Paste Leak Detector' => 'APLD',

        // Blue Monster
        'Blue Monster PTFE Thread Seal Tape' => 'BMTST',

        // Fuses
        'Fuse - 600 volt - FLSR' => 'FFLSR',
        'Fuse - 600 volt - FRSR' => 'FFRSR',

        // Air
        'Air Hose - 3/8 - 300 psi' => 'AH38',
        'Air Brake Anti Freeze - CRC - 32 oz' => 'ABAF',

        // Pump stuff
        'Back Pressure Regulator' => 'BPR',
        'Murphy Pressure Kill Switch' => 'MPKS',
        'Relief Valves' => 'RLV',
        'Tank Level Major Type Control' => 'TLMC',

        // Filters
        'PARKER Hydraulic Tank Desiccant Breather' => 'PHDB',
        'PVC Y-Strainer' => 'PVCYS',
        'Y Strainer - Stainless Steel - 316ss - 18 mesh' => 'YS316',
        'Basket Strainer - Ductile Iron' => 'BSDI',
        'Basket Strainer - Stainless - 304ss' => 'BS304',

        // Pipe
        'Poly Pipe - 500\' Roll' => 'PP500',
        'Poly Pipe - Stick - DR7 - 260 psi' => 'PPDR7',
        'Poly Tube - Black - 150 psi' => 'PTBLK',
        'Clear Nipple' => 'CLNIP',

        // Misc
        'Chemical Pump Stand and Cover' => 'CPSC',
        'Pump Jack Fence Panels' => 'PJFNC',
        'Small Parts Shelf' => 'SHELF',
        'Well Sign Kit - DIY Print' => 'WLSGN',
        'Sucker Rod Card' => 'SRCARD',
        'Pipe Tally Book' => 'PTBK',
        'Flowline Scrapers - Dissolvable' => 'FLSCR',

        // Bungee
        'Bungee Strap - Rubber' => 'BNGEE',

        // Blue Huck
        'Blue Huck Towels' => 'BHTWL',

        // Brass Rod Box
        'Brass Rod Box' => 'BRB',

        // Gauges
        'Drum Gauge' => 'DRGAG',

        // Triplex
        'Triplex Pump - GF Series' => 'TPGF',

        // Hose high pressure
        'Chemical Pump' => 'CPUMP',

        // Antifreeze
        'Antifreeze - Concentrate' => 'AFCON',
        'Antifreeze - Prediluted 50/50' => 'AF50',

        // Safety stuff
        'Safety Glasses' => 'SGLAS',
        'Safety Goggles' => 'SGOGL',
        'Ear Plugs' => 'EPLUG',
        'Nitrile Gloves' => 'NGLV',
        'Leather Gloves' => 'LGLV',

        // WD-40
        'WD-40' => 'WD40',
    ];

    // Try exact match first
    foreach ($specificMap as $pattern => $abbr) {
        if (strcasecmp($n, $pattern) === 0) {
            return $abbr;
        }
    }

    // Try starts-with match
    foreach ($specificMap as $pattern => $abbr) {
        if (stripos($n, $pattern) === 0) {
            return $abbr;
        }
    }

    // ── Algorithmic generation for everything else ──
    return algorithmicAbbr($n);
}

function algorithmicAbbr(string $name): string {
    // Remove common noise
    $n = preg_replace('/["""\'()]/', '', $name);
    // Normalize dashes
    $n = str_replace(['–', '—'], '-', $n);

    // Split by " - " to get key parts
    $parts = array_map('trim', explode(' - ', $n));

    // Common abbreviation fragments
    $fragMap = [
        'Barrel' => 'BRL',
        'Bushing' => 'BSH',
        'Cage' => 'CGE',
        'Coupling' => 'CPL',
        'Connector' => 'CON',
        'Plunger' => 'PLG',
        'Extension' => 'EXT',
        'Mandrel' => 'MND',
        'Valve' => 'VLV',
        'Guide' => 'GDE',
        'Sleeve' => 'SLV',
        'Sliding' => 'SLD',
        'Seat' => 'ST',
        'Seating' => 'STG',
        'Double' => 'DBL',
        'Closed' => 'CLS',
        'Open' => 'OPN',
        'Standing' => 'STD',
        'Pin' => 'PIN',
        'Rod' => 'ROD',
        'Brass' => 'BRS',
        'Stainless' => 'SS',
        'Steel' => 'STL',
        'NiCarb' => 'NC',
        'Precision' => 'PRC',
        'Flat' => 'FL',
        'Heavy' => 'HVY',
        'Thin' => 'THN',
        'RH' => 'RH',
        'RW' => 'RW',
        'TH' => 'TH',
        'Ductile' => 'DI',
        'Iron' => 'I',
        'Fullport' => 'FP',
        'Reg Port' => 'RP',
        'Seal Weld' => 'SW',
        'Forged' => 'FRG',
        'Black' => 'BLK',
        'Coated' => 'CTD',
        'Grooved' => 'GRV',
        'Hex' => 'HX',
        'Square' => 'SQ',
        'Plug' => 'PLG',
        'Nipple' => 'NIP',
        'Ell' => 'ELL',
        'Street' => 'STR',
        'Tee' => 'TEE',
        'Swage' => 'SWG',
        'Union' => 'UNI',
        'Flange' => 'FLG',
        'Reducer' => 'RDC',
        'Poly' => 'PLY',
        'Stud' => 'STD',
        'Grade' => 'GR',
        'Zinc' => 'ZN',
        'Oxide' => 'OX',
        'Nut' => 'NUT',
        'Washer' => 'WSH',
        'Lock' => 'LCK',
        'Split' => 'SPL',
        'Bolt' => 'BLT',
        'Hammer' => 'HMR',
        'KC' => 'KC',
        'Bull' => 'BUL',
        'Dump' => 'DMP',
        'Lever' => 'LVR',
        'Weight' => 'WGT',
        'Operated' => 'OP',
        'Angle' => 'ANG',
        'Murphy' => 'MRP',
        'Pressure' => 'PRS',
        'Kill' => 'KIL',
        'Switch' => 'SW',
        'Relief' => 'RLF',
        'Tank' => 'TNK',
        'Level' => 'LVL',
        'Sight' => 'SGT',
        'Gauge' => 'GAG',
        'Glass' => 'GLS',
        'Rubber' => 'RBR',
        'Gasket' => 'GSK',
        'Hydraulic' => 'HYD',
        'Quick' => 'QC',
        'Connect' => '',
        'Female' => 'F',
        'Male' => 'M',
        'Hose' => 'HOS',
        'Suction' => 'SUC',
        'Hi' => 'HI',
        'Pressure' => 'PRS',
        'Air' => 'AIR',
        'Dump' => 'DMP',
        'Carousel' => 'CRSL',
        'Brake' => 'BRK',
        'Band' => 'BND',
        'Jaws' => 'JAW',
        'Tong' => 'TNG',
        'BJ' => 'BJ',
        'Elevator' => 'ELV',
        'Tubing' => 'TBG',
        'Back Off' => 'BKOF',
        'Grease' => 'GRS',
        'Almatek' => 'ALMK',
        'General' => 'GEN',
        'Lubricant' => 'LUB',
        'Inhibitor' => 'INH',
        'Emulsion' => 'EMUL',
        'Breaker' => 'BRK',
        'Corrosion' => 'COR',
        'Thread' => 'THD',
        'Compound' => 'CMP',
        'Tape' => 'TPE',
        'Penetrating' => 'PEN',
        'Solvent' => 'SLV',
        'Cleaner' => 'CLN',
        'Degreaser' => 'DGR',
        'Stripper' => 'STP',
        'Paint' => 'PNT',
        'Spray' => 'SPR',
        'Weld' => 'WLD',
        'Cutting' => 'CUT',
        'Torch' => 'TCH',
        'Wrench' => 'WRN',
        'Pliers' => 'PLR',
        'Screwdriver' => 'SCD',
        'Socket' => 'SKT',
        'Ratchet' => 'RTCH',
        'Hammer' => 'HMR',
        'Mallet' => 'MLT',
        'Cutter' => 'CTR',
        'Saw' => 'SAW',
        'File' => 'FLE',
        'Clamp' => 'CLP',
        'Strainer' => 'STR',
        'Filter' => 'FLT',
        'Desiccant' => 'DES',
        'Breather' => 'BTH',
        'Spring' => 'SPG',
        'Packing' => 'PKG',
        'Stuffing' => 'STF',
        'Box' => 'BX',
        'Check' => 'CHK',
        'Wing' => 'WNG',
        'Treater' => 'TRT',
        'Stellite' => 'STL',
        'Chrome' => 'CHR',
        'Ni Al Bronze' => 'NAB',
        '316ss' => '316',
        '304ss' => '304',
        'J55' => 'J55',
        'Sch 40' => 'S40',
        'Sch 80' => 'S80',
        'DR11' => 'DR11',
        'DR7' => 'DR7',
        'Fig' => 'FIG',
        'Standard' => 'STD',
        'Extra' => 'X',
        'Seamless' => 'SML',
        'Transition' => 'TRN',
        'Socket' => 'SKT',
        'Adapter' => 'ADP',
        'Straight' => 'STR',
    ];

    // Build abbreviation from parts
    $result = '';

    // Strategy: take first part name and key material/type qualifiers
    if (count($parts) >= 1) {
        $mainPart = $parts[0];

        // For barrels: type + material
        if (preg_match('/^Barrel/i', $mainPart)) {
            $type = isset($parts[1]) ? $parts[1] : '';
            $mat = isset($parts[2]) ? $parts[2] : '';
            $result = 'BRL';
            if ($type) $result .= strtoupper(preg_replace('/[^A-Z0-9]/i', '', substr($type, 0, 2)));
            if (stripos($mat, 'Brass') !== false) $result .= 'B';
            elseif (stripos($mat, 'Precision') !== false) $result .= 'P';
            elseif (stripos($mat, 'Steel') !== false) $result .= 'S';
            if (stripos($mat, 'NiCarb') !== false) $result .= 'NC';
            return substr($result, 0, 8);
        }

        // For cages: type + material
        if (preg_match('/^Cage/i', $mainPart)) {
            $result = 'CG';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Closed') !== false) $result .= 'CL';
                if (stripos($p, 'Open') !== false) $result .= 'OP';
                if (stripos($p, 'Double') !== false) $result .= 'DV';
                if (stripos($p, 'Pin End') !== false) $result .= 'PE';
                if (stripos($p, 'Barrel') !== false && stripos($p, 'Closed') === false) $result .= 'BL';
                if (stripos($p, 'Standing') !== false) $result .= 'SV';
                if (stripos($p, 'Sliding') !== false) $result .= 'SL';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (stripos($p, '3 Wing') !== false) $result .= '3W';
                if (stripos($p, 'Open Top') !== false) { $result = 'CGOT'; }
            }
            return substr($result, 0, 8);
        }

        // For bushings: type + material
        if (preg_match('/^Bushing/i', $mainPart)) {
            $result = 'BSH';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Seat') !== false && stripos($p, 'Seating') === false) $result .= 'ST';
                if (stripos($p, 'Seating') !== false) $result .= 'SG';
                if (stripos($p, 'Valve Rod') !== false) $result .= 'VR';
                if (stripos($p, 'Barrel Cage') !== false) $result .= 'BC';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // For connectors: type + material
        if (preg_match('/^Connector/i', $mainPart)) {
            $result = 'CON';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Sliding') !== false) $result .= 'SL';
                if (stripos($p, 'Upper') !== false) $result .= 'UB';
                if (stripos($p, 'Straight') !== false) $result .= 'STR';
                if (stripos($p, 'Poly') !== false) $result .= 'PLY';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (preg_match('/(\d+)\s*psi/i', $p, $m2)) $result .= $m2[1][0] . 'K';
            }
            return substr($result, 0, 8);
        }

        // For couplings: type + material
        if (preg_match('/^Coupling/i', $mainPart)) {
            $result = 'CPL';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Seating Cup') !== false) $result .= 'SC';
                if (stripos($p, 'Valve Rod') !== false) $result .= 'VR';
                if (stripos($p, 'Black Iron') !== false) $result .= 'BI';
                if (stripos($p, 'Forged') !== false) $result .= 'FRG';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false && stripos($p, 'Forged') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Extra Heavy') !== false) $result .= 'XH';
                if (stripos($p, 'Standard') !== false) $result .= 'SD';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
            }
            return substr($result, 0, 8);
        }

        // Ball Valve patterns
        if (preg_match('/^Ball Valve/i', $mainPart)) {
            $result = 'BV';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Ductile') !== false) $result .= 'DI';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (stripos($p, 'Ni Al Bronze') !== false) $result .= 'NAB';
                if (stripos($p, 'Fullport') !== false) $result .= 'FP';
                if (stripos($p, 'Reg Port') !== false) $result .= 'RP';
                if (stripos($p, 'Seal Weld') !== false) $result .= 'SW';
                if (stripos($p, 'US Made') !== false) $result .= 'US';
                if (stripos($p, '316ss') !== false) $result .= '316';
            }
            return substr($result, 0, 8);
        }

        // Check Valve
        if (preg_match('/^Check Valve/i', $n)) {
            $result = 'CV';
            if (stripos($n, 'Swing') !== false) $result .= 'SWG';
            if (stripos($n, 'Spring') !== false) $result .= 'SPG';
            if (stripos($n, 'PVC') !== false) $result .= 'PVC';
            if (stripos($n, 'Stainless') !== false) $result .= 'SS';
            if (stripos($n, 'Steel') !== false && stripos($n, 'Stainless') === false) $result .= 'S';
            if (stripos($n, 'Ductile') !== false) $result .= 'DI';
            if (stripos($n, 'Brass') !== false) $result .= 'B';
            return substr($result, 0, 8);
        }

        // Bull Plug
        if (preg_match('/^Bull Plug/i', $mainPart)) {
            $result = 'BPLG';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Forged') !== false) $result .= 'F';
                if (stripos($p, '316ss') !== false) $result .= 'SS';
                if (stripos($p, 'J55') !== false) $result .= 'J55';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Extra Heavy') !== false) $result .= 'XH';
                if (stripos($p, 'Standard') !== false) $result .= 'SD';
            }
            return substr($result, 0, 8);
        }

        // Adapter Nipple patterns
        if (preg_match('/^Adapter Nipple/i', $mainPart)) {
            $result = 'AN';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Sch 40') !== false) $result .= 'S40';
                if (stripos($p, 'Sch 80') !== false) $result .= 'S80';
                if (preg_match('/(\d),000/i', $p, $m2)) $result .= $m2[1] . 'K';
            }
            return substr($result, 0, 8);
        }

        // Ell patterns
        if (preg_match('/^(Ell|Street Ell)\s*(\d+)/i', $mainPart, $em)) {
            $isStreet = (stripos($mainPart, 'Street') !== false) ? 'S' : '';
            $angle = $em[2];
            $result = "EL{$isStreet}{$angle}";
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Black Iron') !== false) $result .= 'BI';
                if (stripos($p, 'Ductile') !== false) $result .= 'DI';
                if (stripos($p, 'Forged') !== false) $result .= 'FRG';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false && stripos($p, 'Forged') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Grooved') !== false) $result .= 'G';
                if (stripos($p, 'Poly') !== false) $result .= 'PLY';
            }
            return substr($result, 0, 8);
        }

        // Tee patterns
        if (preg_match('/^Tee/i', $mainPart)) {
            $result = 'TEE';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Black Iron') !== false) $result .= 'BI';
                if (stripos($p, 'Ductile') !== false) $result .= 'DI';
                if (stripos($p, 'Forged') !== false) $result .= 'FRG';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false && stripos($p, 'Forged') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Grooved') !== false) $result .= 'G';
                if (stripos($p, 'Poly') !== false) $result .= 'PLY';
                if (stripos($p, 'Extra Heavy') !== false) $result .= 'XH';
            }
            return substr($result, 0, 8);
        }

        // Nipple patterns
        if (preg_match('/^Nipple/i', $mainPart)) {
            $result = 'NIP';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Black Iron') !== false) $result .= 'BI';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Grooved') !== false) $result .= 'G';
                if (stripos($p, 'Seamless') !== false) $result .= 'SL';
                if (stripos($p, 'Sch 40') !== false) $result .= '40';
                if (stripos($p, 'Sch 80') !== false) $result .= '80';
                if (preg_match('/(\d+)\s*psi/i', $p, $m2)) $result .= $m2[1][0] . 'K';
            }
            return substr($result, 0, 8);
        }

        // Swage patterns
        if (preg_match('/^Swage\s*Nipple/i', $mainPart)) {
            $result = 'SWGN';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'J55') !== false) $result .= 'J55';
                if (stripos($p, 'Extra Heavy') !== false) $result .= 'XH';
                if (stripos($p, 'Standard') !== false) $result .= 'SD';
            }
            return substr($result, 0, 8);
        }
        if (preg_match('/^Swage/i', $mainPart)) {
            $result = 'SWG';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Ductile') !== false) $result .= 'DI';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'J55') !== false) $result .= 'J55';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Grooved') !== false) $result .= 'G';
                if (stripos($p, 'Extra Heavy') !== false) $result .= 'XH';
                if (stripos($p, 'Standard') !== false) $result .= 'SD';
                if (stripos($p, 'Seamless') !== false) $result .= 'SL';
                if (preg_match('/(\d+)\s*psi/i', $p, $m2)) $result .= $m2[1][0] . 'K';
            }
            return substr($result, 0, 8);
        }

        // Hex patterns
        if (preg_match('/^(Hex|Square Head)\s*(Bushing|Plug)/i', $mainPart, $hm)) {
            $htype = (stripos($hm[1], 'Square') !== false) ? 'SQ' : 'HX';
            $hpart = (stripos($hm[2], 'Bushing') !== false) ? 'BSH' : 'PLG';
            $result = "$htype$hpart";
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Black Iron') !== false) $result .= 'BI';
                if (stripos($p, 'Forged') !== false) $result .= 'FRG';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false && stripos($p, 'Forged') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Extra Heavy') !== false) $result .= 'XH';
                if (stripos($p, 'Standard') !== false) $result .= 'SD';
            }
            return substr($result, 0, 8);
        }

        // Ground Joint Union
        if (preg_match('/^Ground Joint Union/i', $mainPart)) {
            $result = 'GJU';
            foreach (array_slice($parts, 1) as $p) {
                if (stripos($p, 'Black Iron') !== false) $result .= 'BI';
            }
            return substr($result, 0, 8);
        }

        // Hammer Unions
        if (preg_match('/^Hammer Union/i', $mainPart)) {
            $result = 'HMU';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (preg_match('/Fig\s*(\d+)/i', $p, $m2)) $result .= 'F' . $m2[1];
            }
            return substr($result, 0, 8);
        }

        // KC Nipple
        if (preg_match('/^KC Nipple/i', $mainPart)) {
            $result = 'KCNIP';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Black') !== false) $result .= 'BI';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
            }
            return substr($result, 0, 8);
        }

        // Grooved Coupling
        if (preg_match('/^Grooved Coupling/i', $mainPart)) {
            $result = 'GRVCP';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (preg_match('/Fig\s*(\w+)/i', $p, $m2)) $result .= $m2[1];
                elseif (preg_match('/(\d),(\d+)\s*psi/i', $p, $m2)) $result .= $m2[1] . 'K';
            }
            return substr($result, 0, 8);
        }

        // Poly Transition/Socket Coupling
        if (preg_match('/^Poly (Transition|Socket)/i', $mainPart, $pm)) {
            $ptype = (stripos($pm[1], 'Transition') !== false) ? 'PTRN' : 'PSKT';
            $result = $ptype;
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Stainless') !== false && stripos($p, '304') !== false) $result .= '304';
                elseif (stripos($p, 'Stainless') !== false && stripos($p, '316') !== false) $result .= '316';
                elseif (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
                if (stripos($p, 'Coated') !== false) $result .= 'C';
                if (stripos($p, 'Groove') !== false) $result .= 'G';
                if (stripos($p, 'Thread') !== false) $result .= 'T';
            }
            return substr($result, 0, 8);
        }

        // Bolt patterns
        if (preg_match('/^Bolt/i', $mainPart)) {
            $result = 'BLT';
            foreach ($parts as $p) {
                $p = trim($p);
                if (stripos($p, 'Hex') !== false) $result .= 'HX';
                if (stripos($p, 'Stud') !== false) $result .= 'SD';
                if (preg_match('/Grade\s*(\d+)/i', $p, $m2)) $result .= 'G' . $m2[1];
                if (stripos($p, 'Zinc') !== false) $result .= 'ZN';
                if (stripos($p, 'Black') !== false) $result .= 'BK';
            }
            return substr($result, 0, 8);
        }

        // Nut
        if (preg_match('/^Nut/i', $mainPart)) {
            $result = 'NUT';
            foreach ($parts as $p) {
                if (stripos($p, 'Hex') !== false) $result .= 'HX';
                if (preg_match('/Grade\s*(\d+)/i', $p, $m2)) $result .= 'G' . $m2[1];
                if (stripos($p, 'Zinc') !== false) $result .= 'ZN';
            }
            return substr($result, 0, 8);
        }

        // Washer
        if (preg_match('/^Washer/i', $mainPart)) {
            $result = 'WSH';
            foreach ($parts as $p) {
                if (stripos($p, 'Flat') !== false) $result .= 'FL';
                if (stripos($p, 'Lock') !== false) $result .= 'LK';
                if (stripos($p, 'Split') !== false) $result .= 'SP';
                if (preg_match('/Grade\s*(\d+)/i', $p, $m2)) $result .= 'G' . $m2[1];
                if (stripos($p, 'Zinc') !== false) $result .= 'ZN';
            }
            return substr($result, 0, 8);
        }

        // Flange
        if (preg_match('/^Flange/i', $mainPart)) {
            $result = 'FLG';
            foreach (array_slice($parts, 1) as $p) {
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, '304') !== false) $result .= '304';
            }
            return substr($result, 0, 8);
        }

        // Reducer
        if (preg_match('/^Reducer/i', $mainPart)) {
            $result = 'RDC';
            foreach (array_slice($parts, 1) as $p) {
                if (stripos($p, 'Poly') !== false) $result .= 'PLY';
            }
            return substr($result, 0, 8);
        }

        // Poly Socket Coupling
        if (preg_match('/^Poly Socket/i', $mainPart)) {
            return 'PSKTCP';
        }

        // Dump Valve patterns
        if (preg_match('/^Dump Valve/i', $mainPart)) {
            $result = 'DMPV';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Lever Ball') !== false) $result .= 'LBJ';
                if (stripos($p, 'Lever Operated') !== false) $result .= 'LO';
                if (stripos($p, 'Weight') !== false) $result .= 'WO';
                if (stripos($p, 'Angle') !== false) $result .= 'A';
            }
            return substr($result, 0, 8);
        }

        // Treater Valve
        if (preg_match('/^Treater Valve/i', $mainPart)) {
            $result = 'TRTV';
            if (stripos($n, 'Weight') !== false) $result .= 'WO';
            if (stripos($n, 'Angle') !== false) $result .= 'A';
            return substr($result, 0, 8);
        }

        // Gauge patterns
        if (preg_match('/^Pressure Gauge/i', $mainPart)) {
            if (strpos($n, '2 1/2') !== false) return 'PG25';
            if (strpos($n, '4') !== false) return 'PG4';
            return 'PGAG';
        }
        if (preg_match('/^Sight Gauge/i', $mainPart)) {
            $result = 'SGAG';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Cock') !== false) $result = 'SGCK';
                if (stripos($p, 'Glass') !== false) $result = 'SGGL';
                if (stripos($p, 'Brass Nut') !== false) $result = 'SGBNT';
                if (stripos($p, 'Brass Washer') !== false) $result = 'SGBWS';
                if (stripos($p, 'Rubber') !== false) $result = 'SGRGS';
            }
            return substr($result, 0, 8);
        }

        // Quick Connect patterns
        if (preg_match('/^Quick Connect/i', $mainPart)) {
            $result = 'QC';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Hydraulic') !== false) $result .= 'HYD';
                if (stripos($p, 'Female') !== false) $result .= 'F';
                if (stripos($p, 'Male') !== false) $result .= 'M';
                if (stripos($p, 'O\'ring') !== false || stripos($p, 'Oring') !== false || stripos($p, 'O-ring') !== false || stripos($p, "O'ring") !== false) $result .= 'OR';
            }
            return substr($result, 0, 8);
        }

        // Hose patterns
        if (preg_match('/^Hose/i', $mainPart)) {
            $result = 'HOS';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Hydraulic') !== false) $result .= 'HYD';
                if (stripos($p, 'Hi Pressure') !== false || stripos($p, 'Hi Press') !== false) $result .= 'HP';
                if (stripos($p, 'Suction') !== false) $result .= 'SUC';
                if (preg_match('/(\d+)\s*psi/i', $p, $m2)) $result .= $m2[1][0] . 'K';
                if (preg_match('/(\d+[\/\d]*)"/', $p, $m2)) {
                    $sz = str_replace(['1/4','3/8','1/2','3/4','1','2','3'], ['14','38','12','34','1','2','3'], $m2[1]);
                    $result .= $sz;
                }
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Steel') !== false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Plunger
        if (preg_match('/^Plunger/i', $mainPart)) {
            $result = 'PLG';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Spray Metal') !== false) $result .= 'SM';
                if (stripos($p, 'Chrome') !== false) $result .= 'CHR';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Extension
        if (preg_match('/^Extension/i', $mainPart)) {
            $result = 'EXT';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Mandrel
        if (preg_match('/^Mandrel/i', $mainPart)) {
            $result = 'MND';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Seat Plug
        if (preg_match('/^Seat Plug/i', $mainPart)) {
            $result = 'STPLG';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Strainer Nipple
        if (preg_match('/^Strainer Nipple/i', $mainPart)) {
            $result = 'STNIP';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Valve Rod
        if (preg_match('/^Valve Rod\b/i', $mainPart)) {
            $result = 'VROD';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Monel') !== false) $result .= 'MNL';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Valve Rod Guide
        if (preg_match('/^Valve Rod Guide/i', $mainPart)) {
            $result = 'VRGDE';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Sliding Sleeve
        if (preg_match('/^Sliding Sleeve$/i', $n)) {
            return 'SLDSLV';
        }

        // Rod pump assemblies / complete pumps
        if (preg_match('/^(\d+)-(\d+)-(RW|RH|TH)/i', $n, $rpMatch)) {
            return strtoupper($rpMatch[1] . $rpMatch[2] . $rpMatch[3]);
        }

        // BJ Rod Tong patterns
        if (preg_match('/^BJ Rod Tong/i', $mainPart)) {
            $result = 'BJRT';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Backup') !== false) $result .= 'BU';
                if (stripos($p, 'Brake Band') !== false) $result .= 'BB';
                if (stripos($p, 'Carousel') !== false) $result .= 'CR';
                if (stripos($p, 'Jaw') !== false) $result .= 'JW';
                if (stripos($p, 'Pin') !== false) $result .= 'PN';
            }
            return substr($result, 0, 8);
        }

        // BJ Tubing patterns
        if (preg_match('/^BJ Tubing (Elevator|Tong)/i', $mainPart, $bjm)) {
            $result = 'BJT';
            if (stripos($bjm[1], 'Elevator') !== false) $result .= 'ELV';
            if (stripos($bjm[1], 'Tong') !== false) {
                $result .= 'TG';
                foreach (array_slice($parts, 1) as $p) {
                    $p = trim($p);
                    if (stripos($p, 'Brake Band Pin') !== false) $result .= 'BBP';
                    elseif (stripos($p, 'Brake Band') !== false) $result .= 'BB';
                    if (stripos($p, 'Head Dies') !== false) $result .= 'HD';
                }
            }
            return substr($result, 0, 8);
        }

        // Air Dump Valve / Air Switch
        if (preg_match('/^Air Dump Valve/i', $n)) return 'ADMPV';
        if (preg_match('/^Air Switch/i', $n)) return 'AIRSW';

        // Back Off Tool
        if (preg_match('/^Back Off Tool/i', $n)) return 'BKOFT';

        // Packing
        if (preg_match('/^Packing/i', $mainPart)) {
            $result = 'PKG';
            if (stripos($n, 'Hi-Temp') !== false || stripos($n, 'Hi Temp') !== false) $result .= 'HT';
            if (stripos($n, 'Low-Temp') !== false || stripos($n, 'Low Temp') !== false) $result .= 'LT';
            if (stripos($n, 'Regular') !== false) $result .= 'REG';
            return substr($result, 0, 8);
        }

        // Stuffing Box
        if (preg_match('/^Stuffing Box/i', $n)) return 'STFBX';

        // Polish Rod
        if (preg_match('/^Polish Rod Clamp/i', $n)) return 'PRC';
        if (preg_match('/^Polish Rods/i', $n)) return 'PROD';

        // Sucker Rod patterns
        if (preg_match('/^Sucker Rod Coupling/i', $n)) return 'SRC';
        if (preg_match('/^Sucker Rods/i', $n)) {
            if (stripos($n, 'Carbon') !== false) return 'SRCARB';
            return 'SROD';
        }

        // Check Valve patterns
        if (preg_match('/^Check Valve/i', $mainPart)) {
            $result = 'CHKV';
            foreach (array_slice($parts, 1) as $p) {
                $p = trim($p);
                if (stripos($p, 'Swing') !== false) $result .= 'SW';
                if (stripos($p, 'Spring') !== false) $result .= 'SP';
                if (stripos($p, 'PVC') !== false) $result .= 'PVC';
                if (stripos($p, 'Stainless') !== false) $result .= 'SS';
                if (stripos($p, 'Ductile') !== false) $result .= 'DI';
                if (stripos($p, 'Brass') !== false) $result .= 'B';
                if (stripos($p, 'Steel') !== false && stripos($p, 'Stainless') === false) $result .= 'S';
            }
            return substr($result, 0, 8);
        }

        // Gate Valve
        if (preg_match('/^Gate Valve/i', $mainPart)) {
            $result = 'GATV';
            if (stripos($n, 'Brass') !== false) $result .= 'B';
            return substr($result, 0, 8);
        }

        // Needle Valve
        if (preg_match('/^Needle Valve/i', $mainPart)) {
            $result = 'NDLV';
            if (stripos($n, 'Stainless') !== false) $result .= 'SS';
            if (stripos($n, 'Steel') !== false && stripos($n, 'Stainless') === false) $result .= 'S';
            return substr($result, 0, 8);
        }
    }

    // ── Fallback: take initials of significant words ──
    $words = preg_split('/[\s\-\/]+/', $n);
    $stopWords = ['the', 'a', 'an', 'of', 'for', 'and', 'or', 'in', 'on', 'to', 'with', 'by', 'from', 'is', 'at', 'as', 'it', 'psi', 'lbs', 'oz', 'ft', 'inch', 'we', 'offer', 'high', 'quality', 'affordable'];
    $initials = '';
    foreach ($words as $w) {
        $w = trim($w, '",.\' ');
        if (empty($w)) continue;
        if (in_array(strtolower($w), $stopWords)) continue;
        if (is_numeric($w)) {
            $initials .= $w;
        } else {
            $initials .= strtoupper($w[0]);
        }
        if (strlen($initials) >= 6) break;
    }

    return $initials ?: 'ITEM';
}

/**
 * Generate variation description code from name/description.
 */
function generateVarDesc(string $varName, string $varDesc): string {
    // Use description first as it's usually the variant-specific part
    $desc = trim($varDesc, " \t\n\r\0\x0B\"<p>/");
    $desc = strip_tags($desc);
    $desc = trim($desc);

    // If desc is empty or same as name, extract from name
    if (empty($desc) || strlen($desc) < 2) {
        // Try to extract the variant part from name (after last " - ")
        $dashPos = strrpos($varName, ' - ');
        if ($dashPos !== false) {
            $desc = trim(substr($varName, $dashPos + 3));
        } else {
            $desc = $varName;
        }
    }

    // Clean up
    $desc = str_replace(["\n", "\r", '"'], '', $desc);
    $desc = trim($desc);

    // ── Specific known variation descriptor mappings ──
    $specificVarMap = [
        'Control Panel' => 'PNL',
        'Control Panel w/ Cellular Modem' => 'PNLM',
        'Full-time Autobailer' => 'FT',
        'Part-time Autobailer' => 'PT',
        'Rod Back Off Tool' => 'ROD',
        'BJ Rod Tong Brake Band' => 'BB',
        'BJ Rod Tong Carousel' => 'CRSL',
        'BJ Rod Tong Pin' => 'PIN',
        'BJ Tubing Tong Brake Band' => 'BB',
        'Large' => 'LG',
        'Small' => 'SM',
    ];
    foreach ($specificVarMap as $pattern => $code) {
        if (strcasecmp($desc, $pattern) === 0) return $code;
    }

    // Handle "N <thing>" patterns like "1 Acid Stick" => 1PK
    if (preg_match('/^(\d+)\s+(Acid\s+Stick|Stick|Tube|Can|Roll|Piece|Bag|Sheet|Pair|Set)/i', $desc)) {
        preg_match('/(\d+)/', $desc, $nm);
        return $nm[1] . 'PK';
    }

    // Handle "Pail of N" patterns
    if (preg_match('/Pail\s+of\s+(\d+)/i', $desc, $pm)) {
        return $pm[1] . 'PK';
    }

    // ── Pipe size mappings ──
    $result = $desc;

    // Handle pump specs like "9-3-0-0"
    if (preg_match('/^(\d+)-(\d+)-(\d+)-(\d+)$/', $desc, $pm)) {
        return $pm[1] . $pm[2] . $pm[3] . $pm[4];
    }

    // Handle sizes like 2 1/2" x 1 3/4" x 4' etc (barrel/pump part sizes)
    if (preg_match('/^(\d[\d\s\/]*)[""]\s*x\s*(\d[\d\s\/]*)[""]\s*x\s*(\d+)[\'"]/', $desc, $bm)) {
        $od = compactSize($bm[1]);
        $id = compactSize($bm[2]);
        $len = $bm[3];
        $suffix = '';
        if (stripos($desc, 'chrome') !== false) $suffix .= 'C';
        if (stripos($desc, 'used') !== false) $suffix .= 'U';
        return $od . 'x' . $id . 'x' . $len . $suffix;
    }

    // Handle sizes like 2" x 1 1/2" (no length)
    if (preg_match('/^(\d[\d\s\/]*)[""]\s*x\s*(\d[\d\s\/]*)[""]/', $desc, $bm)) {
        $s1 = compactSize($bm[1]);
        $s2 = compactSize($bm[2]);
        $suffix = '';
        if (stripos($desc, 'npt') !== false) {
            // Extract npt size
            if (preg_match('/(\d[\d\s\/]*)[""]\s*npt/', $desc, $nm)) {
                $suffix .= 'N' . compactSize($nm[1]);
            }
        }
        if (stripos($desc, 'used') !== false) $suffix .= 'U';
        return $s1 . 'x' . $s2 . $suffix;
    }

    // Handle pin x vr sizes (valve rod bushings)
    if (preg_match('/(\d[\d\s\/]*)[""]\s*pin\s*x\s*(\d[\d\s\/]*)[""]\s*vr/i', $desc, $pvr)) {
        $pinSz = compactSize($pvr[1]);
        $vrSz = compactSize($pvr[2]);
        $suffix = '';
        if (stripos($desc, 'used') !== false) $suffix .= 'U';
        return $pinSz . 'P' . $vrSz . 'V' . $suffix;
    }

    // Handle ball and seat sizes "1 1/2" Alloy Seat, 15/16" Alloy Ball"
    if (preg_match('/(\d[\d\s\/]*)[""]\s*(Alloy|Carbide|SS|Stainless)\s*Seat.*?(\d[\d\s\/]*)[""]\s*(Alloy|Carbide|SS|Stainless)\s*Ball/i', $desc, $bsm)) {
        $seatSz = compactSize($bsm[1]);
        $seatMat = strtoupper(substr($bsm[2], 0, 1)); // A, C, S
        $ballSz = compactSize($bsm[3]);
        $ballMat = strtoupper(substr($bsm[4], 0, 1));
        $suffix = '';
        if (stripos($desc, 'used') !== false) $suffix .= 'U';
        return $seatSz . $seatMat . $ballSz . $ballMat . $suffix;
    }

    // Handle sizes with stellite/used qualifiers "1 1/2", stellite lined"
    if (preg_match('/^(\d[\d\s\/]*)[""]/i', $desc, $szm)) {
        $sz = compactSize($szm[1]);
        $suffix = '';
        if (stripos($desc, 'stellite') !== false) $suffix .= 'ST';
        if (stripos($desc, 'used') !== false) $suffix .= 'U';
        if (stripos($desc, 'heavy') !== false) $suffix .= 'HD';
        return $sz . $suffix;
    }

    // Handle simple sizes like "1"", "2"", "3/4"" etc
    if (preg_match('/^(\d[\d\s\/]*)[""]\s*$/i', $desc)) {
        return compactSize(trim($desc, '" '));
    }

    // Handle size + psi "2"", 2000 psi"
    if (preg_match('/^(\d[\d\s\/]*)[""]\s*,?\s*(\d+)\s*psi/i', $desc, $spm)) {
        $sz = compactSize($spm[1]);
        $psi = compactPsi($spm[2]);
        return $sz . $psi;
    }

    // Handle size + psi + qualifiers "2"", 1000 psi\n"
    if (preg_match('/^(\d[\d\s\/]*)[""]\s*[,\s]*(\d+)\s*psi/i', $desc, $spm)) {
        $sz = compactSize($spm[1]);
        $psi = compactPsi($spm[2]);
        $suffix = '';
        if (stripos($desc, 'heavy') !== false) $suffix .= 'HD';
        return $sz . $psi . $suffix;
    }

    // Handle ft/inch lengths "1" x 6", grv x thd"
    if (preg_match('/^(\d[\d\s\/]*)[""]\s*x\s*(\d+)[""]/i', $desc, $lm)) {
        $sz = compactSize($lm[1]);
        $len = $lm[2];
        $suffix = '';
        if (stripos($desc, 'grv') !== false) $suffix .= 'G';
        if (stripos($desc, 'thd') !== false) $suffix .= 'T';
        return $sz . 'x' . $len . $suffix;
    }

    // Volume patterns
    if (preg_match('/^(\d+)\s*(gal|gallon)/i', $desc, $vm)) {
        return $vm[1] . 'G';
    }
    if (preg_match('/(\d+)\s*gal/i', $desc, $vm)) {
        return $vm[1] . 'G';
    }
    if (preg_match('/^(\d+)\s*(qt|quart)/i', $desc, $vm)) {
        return $vm[1] . 'QT';
    }
    if (preg_match('/^(\d+)\s*oz/i', $desc, $vm)) {
        return $vm[1] . 'OZ';
    }

    // Count/pack patterns
    if (preg_match('/^(\d+)\s*(Stick|Pack|Tube|Box|Pail|Case)/i', $desc, $cm)) {
        return $cm[1] . 'PK';
    }
    if (preg_match('/Box\s*of\s*(\d+)/i', $desc, $cm)) {
        return $cm[1] . 'PK';
    }
    if (preg_match('/Case\s*\((\d+)/i', $desc, $cm)) {
        return $cm[1] . 'PK';
    }
    if (preg_match('/(\d+)\s*14oz\s*Tube/i', $desc, $cm)) {
        return $cm[1] . 'PK';
    }
    if (preg_match('/(\d+)\s*14oz\s*Tubes/i', $desc, $cm)) {
        return $cm[1] . 'PK';
    }
    if (preg_match('/^(\d+)\s*12oz\s*Can/i', $desc, $cm)) {
        return $cm[1] . 'PK';
    }

    // Pail/bucket weights
    if (preg_match('/^(\d+)#?\s*pail/i', $desc, $wm)) {
        return $wm[1] . 'LB';
    }
    if (preg_match('/^(\d+)\s*Pound/i', $desc, $wm)) {
        return $wm[1] . 'LB';
    }
    if (preg_match('/^(\d+[\.\d]*)\s*lbs?\s*bag/i', $desc, $wm)) {
        $lb = str_replace('.', '', $wm[1]);
        return $lb . 'LB';
    }

    // Feet lengths "25'", "50'"
    if (preg_match('/^(\d+)[\']\s/', $desc) || preg_match('/^(\d+)\'$/', $desc)) {
        preg_match('/(\d+)/', $desc, $fm);
        return $fm[1] . 'FT';
    }
    if (preg_match('/(\d+)\s*foot|(\d+)\s*feet|(\d+)\'\s/i', $desc, $fm)) {
        $ft = $fm[1] ?: ($fm[2] ?: $fm[3]);
        return $ft . 'FT';
    }

    // Inch lengths "12""", "18"""
    if (preg_match('/^(\d+)\s*(Inch|")/i', $desc, $im)) {
        return $im[1];
    }

    // HP patterns
    if (preg_match('/(\d+)\s*HP/i', $desc, $hm)) {
        return $hm[1] . 'HP';
    }

    // Model patterns like R6, R8, TW1
    if (preg_match('/^([A-Z]+\d+)$/i', $desc)) {
        return strtoupper($desc);
    }

    // Rod sizes "5/8", "3/4", "7/8"
    if (preg_match('/^(\d\/\d)$/', $desc)) {
        return compactSize($desc);
    }

    // Simple descriptors - capitalize and compact
    $clean = preg_replace('/[^A-Za-z0-9\s]/', '', $desc);
    $words = preg_split('/\s+/', $clean);
    $stopWords = ['the', 'a', 'an', 'of', 'for', 'and', 'or', 'in', 'on', 'to', 'with', 'by'];
    $result = '';
    foreach ($words as $w) {
        if (empty($w)) continue;
        if (in_array(strtolower($w), $stopWords)) continue;
        if (strlen($result) + strlen($w) <= 8) {
            $result .= strtoupper($w[0]) . strtolower(substr($w, 1, 2));
        } else {
            $result .= strtoupper($w[0]);
        }
        if (strlen($result) >= 8) break;
    }

    return strtoupper(substr($result, 0, 8)) ?: 'V1';
}

/**
 * Compact a fractional size into a short code.
 */
function compactSize(string $size): string {
    $s = trim($size, '" \' ');

    $map = [
        '1/4' => '14',
        '3/8' => '38',
        '1/2' => '12',
        '3/4' => '34',
        '5/8' => '58',
        '7/8' => '78',
        '1/8' => '18',
        '11/16' => '1116',
        '15/16' => '1516',
        '1 1/8' => '118',
        '1 1/4' => '114',
        '1 1/2' => '112',
        '1 3/4' => '134',
        '1 3/8' => '138',
        '2 1/2' => '212',
        '2 1/4' => '214',
        '2 3/8' => '238',
        '2 7/8' => '278',
        '3 1/2' => '312',
        '4 1/2' => '412',
        '5 1/2' => '512',
        '5/16' => '516',
        '7/16' => '716',
        '9/16' => '916',
    ];

    // Direct match
    if (isset($map[$s])) return $map[$s];

    // Integer
    if (preg_match('/^\d+$/', $s)) return $s;

    // Try "X Y/Z" patterns
    if (preg_match('/^(\d+)\s+(\d+\/\d+)$/', $s, $m)) {
        $whole = $m[1];
        $frac = $m[2];
        $fracMap = ['1/4'=>'14','3/8'=>'38','1/2'=>'12','3/4'=>'34','5/8'=>'58','7/8'=>'78','1/8'=>'18','1/16'=>'116','3/16'=>'316','5/16'=>'516','7/16'=>'716','9/16'=>'916','11/16'=>'1116','13/16'=>'1316','15/16'=>'1516'];
        $fracCode = isset($fracMap[$frac]) ? $fracMap[$frac] : str_replace('/', '', $frac);
        return $whole . $fracCode;
    }

    // Fraction only
    if (preg_match('/^(\d+)\/(\d+)$/', $s, $m)) {
        $fracMap = ['1/4'=>'14','3/8'=>'38','1/2'=>'12','3/4'=>'34','5/8'=>'58','7/8'=>'78','1/8'=>'18','5/16'=>'516','7/16'=>'716','9/16'=>'916','11/16'=>'1116','15/16'=>'1516'];
        return isset($fracMap[$s]) ? $fracMap[$s] : $m[1] . $m[2];
    }

    // Remove non-alphanumeric
    return preg_replace('/[^A-Za-z0-9]/', '', substr($s, 0, 4));
}

/**
 * Compact a PSI value
 */
function compactPsi(string $psi): string {
    $v = intval($psi);
    if ($v >= 1000) return intval($v / 1000) . 'K';
    return $v . 'P';
}
