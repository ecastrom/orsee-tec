<?php
// Throwaway: dump or_profile_fields label maps + phone default country.
require __DIR__ . '/db_bootstrap.php';
$db = beerlab_db_connect();
$pf = $db['prefix'] . 'profile_fields';
$rows = $db['pdo']->query("SELECT mysql_column_name, enabled, type, properties FROM `$pf` ORDER BY mysql_column_name")->fetchAll();
foreach ($rows as $r) {
    if (!in_array($r['mysql_column_name'], ['fname', 'gender', 'phone_number', 'subscriptions', 'field_of_studies'], true)) continue;
    echo "=== {$r['mysql_column_name']} (type={$r['type']}) ===\n";
    echo "  RAW: " . substr((string) $r['properties'], 0, 900) . "\n";
}
