<?php
/**
 * Reset an ORSEE admin password to a random one-time value.
 *
 *   heroku run "php bin/reset-admin-pw.php <adminname>" -a orsee-beerlab
 *
 * Prints the new password to the run log ONLY (never stored in the repo) and
 * sets pw_update_requested=1 so ORSEE forces a change at the next login.
 * Also clears any lockout counters.
 */

require __DIR__ . '/db_bootstrap.php';

$adminname = $argv[1] ?? '';
if ($adminname === '') {
    fwrite(STDERR, "usage: php bin/reset-admin-pw.php <adminname>\n");
    exit(1);
}

$db    = beerlab_db_connect();
$pdo   = $db['pdo'];
$admin = $db['prefix'] . 'admin';

$q = $pdo->prepare("SELECT admin_id FROM `$admin` WHERE adminname = :a");
$q->execute([':a' => $adminname]);
$row = $q->fetch();
if (!$row) {
    fwrite(STDERR, "[reset-admin-pw] admin '$adminname' not found\n");
    exit(1);
}

$password = 'Tmp-' . bin2hex(random_bytes(6));
$upd = $pdo->prepare(
    "UPDATE `$admin`
        SET password_crypt = :pw, pw_update_requested = 1,
            failed_login_attempts = 0, locked = 0
      WHERE adminname = :a"
);
$upd->execute([':pw' => password_hash($password, PASSWORD_DEFAULT), ':a' => $adminname]);

fwrite(STDOUT, "[reset-admin-pw] '$adminname' -> new one-time password: $password\n");
fwrite(STDOUT, "[reset-admin-pw] a password change is forced at next login\n");
