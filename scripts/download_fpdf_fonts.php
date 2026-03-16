<?php
$fonts = ['courier.php','helvetica.php','helveticab.php','helveticabi.php','helveticai.php','symbol.php','times.php','timesb.php','timesbi.php','timesi.php','zapfdingbats.php'];
$dir = __DIR__ . '/../vendor/fpdf/font';
if (!is_dir($dir)) { mkdir($dir, 0755, true); }
$ctx = stream_context_create(['http' => ['timeout' => 10, 'header' => 'User-Agent: Mozilla/5.0']]);
foreach ($fonts as $f) {
    $url = "https://raw.githubusercontent.com/Setasign/FPDF/refs/heads/master/font/{$f}";
    $data = @file_get_contents($url, false, $ctx);
    if ($data) {
        file_put_contents("{$dir}/{$f}", $data);
        echo "{$f} OK\n";
    } else {
        echo "{$f} FAILED\n";
    }
}
