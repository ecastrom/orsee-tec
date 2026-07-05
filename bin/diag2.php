<?php
// Throwaway: dump or_profile_fields label maps + phone default country.
require __DIR__ . '/db_bootstrap.php';
$db = beerlab_db_connect();
$pf = $db['prefix'] . 'profile_fields';
$rows = $db['pdo']->query("SELECT mysql_column_name, enabled, type, properties FROM `$pf` ORDER BY mysql_column_name")->fetchAll();
foreach ($rows as $r) {
    echo "=== {$r['mysql_column_name']} (type={$r['type']}, enabled={$r['enabled']}) ===\n";
    $p = json_decode((string) $r['properties'], true);
    if (!is_array($p)) { echo "  [properties not json]\n"; continue; }
    foreach ($p as $k => $v) {
        if (is_array($v) && (isset($v['en']) || isset($v['de']))) {
            echo "  $k: en=" . json_encode($v['en'] ?? null, JSON_UNESCAPED_UNICODE)
                . " es=" . json_encode($v['es'] ?? null, JSON_UNESCAPED_UNICODE) . "\n";
        } elseif (in_array($k, ['phone_default_country', 'options_source', 'linked_content_type'], true)) {
            echo "  $k=" . json_encode($v) . "\n";
        }
    }
}
