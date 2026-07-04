<?php
/**
 * Manual, one-off ORSEE schema importer.
 *
 * Use when you want to import the schema by hand instead of via the release
 * phase, e.g.:
 *
 *   Heroku:  heroku run "php bin/import-db.php" -a your-app
 *   Docker:  docker compose exec web php bin/import-db.php
 *   Local:   php bin/import-db.php
 *
 * Refuses to run if the schema already exists, unless you pass --force
 * (which DROPS nothing itself — it simply re-runs install.sql; only use on a
 * database you intend to (re)initialise).
 */

require __DIR__ . '/db_bootstrap.php';

$force = in_array('--force', $argv, true);

try {
    $db = beerlab_db_connect();
} catch (Throwable $e) {
    fwrite(STDERR, "Could not connect to MySQL: " . $e->getMessage() . "\n");
    exit(1);
}

if (beerlab_schema_present($db['pdo'], $db['name'], $db['prefix']) && !$force) {
    fwrite(STDOUT, "Schema already present ({$db['prefix']}admin exists). Use --force to import anyway.\n");
    exit(0);
}

fwrite(STDOUT, "Importing ORSEE schema into '{$db['name']}' (prefix '{$db['prefix']}') ...\n");
try {
    beerlab_import_schema($db['pdo'], $db['prefix']);
} catch (Throwable $e) {
    fwrite(STDERR, "Import FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
fwrite(STDOUT, "Done. Default login -> orsee_install / install  (change it immediately).\n");
exit(0);
