<?php
/**
 * Shared DB bootstrap helper for the BEER Lab ORSEE deployment.
 *
 * Resolves MySQL connection parameters from the environment using the SAME
 * precedence as orsee/config/settings.php (JawsDB -> ClearDB -> DATABASE_URL
 * -> discrete ORSEE_DB_* vars), and opens a PDO connection.
 *
 * Used by bin/release.php (schema import) and bin/import-db.php (manual import).
 *
 * @return array{pdo: PDO, name: string, prefix: string}
 */

function beerlab_db_params(): array
{
    $url = getenv('JAWSDB_URL')
        ?: getenv('JAWSDB_MARIA_URL')
        ?: getenv('CLEARDB_DATABASE_URL')
        ?: getenv('DATABASE_URL');

    if ($url) {
        $p = parse_url($url);
        return [
            'host' => $p['host'] ?? 'localhost',
            'port' => isset($p['port']) ? (int) $p['port'] : 3306,
            'name' => isset($p['path']) ? ltrim($p['path'], '/') : '',
            'user' => isset($p['user']) ? rawurldecode($p['user']) : '',
            'pass' => isset($p['pass']) ? rawurldecode($p['pass']) : '',
        ];
    }

    return [
        'host' => getenv('ORSEE_DB_HOST') ?: 'localhost',
        'port' => (int) (getenv('ORSEE_DB_PORT') ?: 3306),
        'name' => getenv('ORSEE_DB_NAME') ?: 'orsee_db',
        'user' => getenv('ORSEE_DB_USER') ?: 'orsee_user',
        'pass' => getenv('ORSEE_DB_PASS') ?: 'orsee_pw',
    ];
}

function beerlab_db_connect(): array
{
    $c = beerlab_db_params();
    $dsn = sprintf('mysql:host=%s;port=%d;dbname=%s;charset=utf8mb4', $c['host'], $c['port'], $c['name']);
    $pdo = new PDO($dsn, $c['user'], $c['pass'], [
        PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
    ]);
    return [
        'pdo'    => $pdo,
        'name'   => $c['name'],
        'prefix' => getenv('ORSEE_TABLE_PREFIX') ?: 'or_',
    ];
}

/**
 * True when the ORSEE schema is already present.
 *
 * Checks for the "<prefix>admin" table (the administrators table, e.g. or_admin),
 * which install.sql creates and seeds with the default orsee_install account.
 */
function beerlab_schema_present(PDO $pdo, string $dbName, string $prefix): bool
{
    $stmt = $pdo->prepare(
        'SELECT COUNT(*) AS c FROM information_schema.tables
          WHERE table_schema = :db AND table_name = :t'
    );
    $stmt->execute([':db' => $dbName, ':t' => $prefix . 'admin']);
    return (int) $stmt->fetch()['c'] > 0;
}

/**
 * Imports orsee/install/install.sql into the connected database.
 *
 * The dump uses the default "or_" prefix. If ORSEE_TABLE_PREFIX differs we
 * rewrite the prefix on the fly so a custom prefix still works.
 */
function beerlab_import_schema(PDO $pdo, string $prefix): void
{
    $sqlPath = dirname(__DIR__) . '/orsee/install/install.sql';
    if (!is_readable($sqlPath)) {
        throw new RuntimeException("install.sql not found or unreadable at $sqlPath");
    }

    $sql = file_get_contents($sqlPath);
    if ($prefix !== 'or_') {
        // Table names in the dump appear as `or_xxx` (backtick-quoted).
        $sql = str_replace('`or_', '`' . $prefix, $sql);
    }

    // Execute the dump as one multi-statement batch. mysqldump output is safe
    // to run this way; PDO::exec handles multiple ;-separated statements when
    // emulated prepares are on (default for this connection).
    $pdo->exec($sql);
}
