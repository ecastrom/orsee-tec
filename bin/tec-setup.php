<?php
/**
 * BEER Lab / ORSEE: brand colors, lab-specific public content, and second admin.
 *
 *   heroku run "php bin/tec-setup.php" -a orsee-beerlab
 *
 * Idempotent. Does three things:
 *   1. Header colors (style 'orsee'): remove the navy bars -> white header with
 *      Tec-blue accents so the Tecnologico logo reads cleanly.
 *   2. Public content pages (or_lang public_content, es + en) replaced with
 *      BEER Lab / Tec de Monterrey text from bin/tec_content.json.
 *   3. Creates a full admin account for Stanislao Maldonado (temp password,
 *      forced change on first login) if it does not already exist.
 */

require __DIR__ . '/db_bootstrap.php';

$db      = beerlab_db_connect();
$pdo     = $db['pdo'];
$lang    = $db['prefix'] . 'lang';
$options = $db['prefix'] . 'options';
$admin   = $db['prefix'] . 'admin';

/* 1. header colors (active style = 'orsee') -------------------------------- */
$colors = [
    'html_header_top_bar_background'  => '#ffffff',
    'html_header_logo_bar_background' => '#ffffff',
    'html_header_logo_bar_text'       => '#0039a6',
    'html_header_menu_background'     => '#0039a6',
];
$cstmt = $pdo->prepare("UPDATE `$options` SET option_value = :v
                         WHERE option_type = 'color' AND option_style = 'orsee' AND option_name = :n");
foreach ($colors as $name => $val) {
    $cstmt->execute([':v' => $val, ':n' => $name]);
    fwrite(STDOUT, "[colors] $name = $val (" . $cstmt->rowCount() . " row)\n");
}

/* 2. public content pages (es + en) ---------------------------------------- */
$content = json_decode(file_get_contents(__DIR__ . '/tec_content.json'), true);
if (!is_array($content)) {
    fwrite(STDERR, "[content] could not read tec_content.json\n");
    exit(1);
}
$ustmt = $pdo->prepare("UPDATE `$lang` SET `es` = :es, `en` = :en
                         WHERE content_type = 'public_content' AND content_name = :n");
foreach ($content as $name => $tr) {
    $ustmt->execute([':es' => $tr['es'], ':en' => $tr['en'], ':n' => $name]);
    fwrite(STDOUT, "[content] page '$name' updated (" . $ustmt->rowCount() . " row)\n");
}

/* 3. second admin: Stanislao Maldonado ------------------------------------- */
$exists = $pdo->prepare("SELECT COUNT(*) c FROM `$admin` WHERE adminname = :a");
$exists->execute([':a' => 'smaldonado']);
if ((int) $exists->fetch()['c'] > 0) {
    fwrite(STDOUT, "[admin] smaldonado already exists - left unchanged\n");
} else {
    $nextId = (int) $pdo->query("SELECT COALESCE(MAX(admin_id),0)+1 AS n FROM `$admin`")->fetch()['n'];
    $tempPassword = 'BeerLab-Temporal-2026';
    $hash = password_hash($tempPassword, PASSWORD_DEFAULT);   // bcrypt; ORSEE verifies with password_verify()
    $ins = $pdo->prepare(
        "INSERT INTO `$admin`
         (admin_id, fname, lname, email, adminname, admin_type, password_crypt,
          experimenter_list, disabled, language, get_calendar_mail, get_statistics_mail,
          last_login_attempt, failed_login_attempts, locked, pw_update_requested)
         VALUES (:id,'Stanislao','Maldonado','stanislao.maldonado@tec.mx','smaldonado','installer',:pw,
                 'y','n','en','y','y',NULL,0,0,1)"
    );
    $ins->execute([':id' => $nextId, ':pw' => $hash]);
    fwrite(STDOUT, "[admin] created 'smaldonado' (id $nextId), type=installer (full access)\n");
    fwrite(STDOUT, "[admin] temp password: $tempPassword  -> change required on first login\n");
}

fwrite(STDOUT, "[tec-setup] DONE\n");
