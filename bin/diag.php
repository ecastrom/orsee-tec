<?php
// Throwaway diagnostic: GD/FreeType + profile_form_layout language maps.
//   heroku run "php bin/diag.php" -a orsee-beerlab
chdir(__DIR__ . '/../orsee/admin');
include 'cronheader.php';

echo "imagefttext exists: " . (function_exists('imagefttext') ? 'YES' : 'NO') . "\n";
$gd = function_exists('gd_info') ? gd_info() : [];
echo "GD FreeType Support: " . (!empty($gd['FreeType Support']) ? 'YES' : 'NO') . "\n";
echo "GD version: " . ($gd['GD Version'] ?? '?') . "\n";

foreach (['profile_form_public_current', 'profile_form_public_draft'] as $item) {
    $cfg = options__load_json_object('profile_form_layout', $item, array());
    echo "=== $item : " . (is_array($cfg) && $cfg ? 'present' : 'EMPTY') . " ===\n";
    $walk = function ($node, $path = '') use (&$walk) {
        if (!is_array($node)) return;
        if (isset($node['en']) || isset($node['de'])) {
            echo "  $path  en=" . json_encode($node['en'] ?? null, JSON_UNESCAPED_UNICODE)
                . "  es=" . json_encode($node['es'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
        }
        foreach ($node as $k => $v) {
            if (is_array($v)) $walk($v, $path . '/' . $k);
        }
    };
    $walk($cfg);
}
