<?php
/**
 * Heroku release-phase task (runs once per deploy, before the web dyno swaps in).
 *
 * On the FIRST deploy it imports the ORSEE schema. On every later deploy it
 * detects the existing schema and exits without touching data. Safe to re-run.
 *
 * If it cannot reach the database it prints a clear message and exits 0 so a
 * transient DB hiccup does not permanently block deploys; the web dyno will
 * surface a connection error that is easy to diagnose. Flip FAIL_ON_DB_ERROR
 * to '1' (env) if you'd rather have deploys hard-fail when the DB is down.
 */

require __DIR__ . '/db_bootstrap.php';

fwrite(STDOUT, "[release] BEER Lab / ORSEE release task starting...\n");

try {
    $db = beerlab_db_connect();
} catch (Throwable $e) {
    fwrite(STDERR, "[release] Could not connect to MySQL: " . $e->getMessage() . "\n");
    fwrite(STDERR, "[release] Is the JawsDB/ClearDB add-on provisioned? Skipping import.\n");
    exit(getenv('FAIL_ON_DB_ERROR') === '1' ? 1 : 0);
}

try {
    if (beerlab_schema_present($db['pdo'], $db['name'], $db['prefix'])) {
        fwrite(STDOUT, "[release] Schema already present (table {$db['prefix']}admin found). Nothing to do.\n");
        exit(0);
    }

    fwrite(STDOUT, "[release] Empty database detected. Importing orsee/install/install.sql ...\n");
    beerlab_import_schema($db['pdo'], $db['prefix']);
    fwrite(STDOUT, "[release] Schema import complete.\n");
    fwrite(STDOUT, "[release] Default admin login -> user: orsee_install  password: install\n");
    fwrite(STDOUT, "[release] >>> CHANGE THIS PASSWORD IMMEDIATELY after first login. <<<\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[release] Schema import FAILED: " . $e->getMessage() . "\n");
    exit(1);
}
