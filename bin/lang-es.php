<?php
/**
 * BEER Lab / ORSEE: install Spanish (es) as the default public language.
 *
 *   heroku run "php bin/lang-es.php" -a orsee-beerlab
 *
 * What it does (idempotent — safe to re-run):
 *   1. Adds an `es` column to or_lang if missing (this is how ORSEE registers a
 *      language: get_languages() reads the table columns dynamically).
 *   2. Seeds es = en so nothing is ever blank (untranslated/admin strings fall
 *      back to English, which is intended — admin UI stays English).
 *   3. Applies the participant-facing Spanish translations from
 *      bin/es_translations.json (keyed by or_lang.lang_id).
 *   4. Sets the language display name to "Español".
 *   5. Updates or_options: support_mail -> lab.economia@servicios.tec.mx, and the enabled
 *      public/participant languages to "es,en" (first entry = default => Spanish).
 *      German (de) stays as data but is no longer offered.
 *
 * It never drops the `de` column, so no data is lost.
 */

require __DIR__ . '/db_bootstrap.php';

$db      = beerlab_db_connect();
$pdo     = $db['pdo'];
$lang    = $db['prefix'] . 'lang';
$options = $db['prefix'] . 'options';

fwrite(STDOUT, "[lang-es] target tables: $lang / $options\n");

/* 1. add es column if missing --------------------------------------------- */
$q = $pdo->prepare(
    "SELECT COUNT(*) c FROM information_schema.columns
      WHERE table_schema = :db AND table_name = :t AND column_name = 'es'"
);
$q->execute([':db' => $db['name'], ':t' => $lang]);
$has_es = (int) $q->fetch()['c'] > 0;

if (!$has_es) {
    $pdo->exec("ALTER TABLE `$lang`
                ADD COLUMN `es` mediumtext CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci AFTER `de`");
    $pdo->exec("UPDATE `$lang` SET `es` = `en`");
    fwrite(STDOUT, "[lang-es] added `es` column and seeded es = en\n");
} else {
    $pdo->exec("UPDATE `$lang` SET `es` = `en` WHERE `es` IS NULL");
    fwrite(STDOUT, "[lang-es] `es` column already present (seeded only NULLs)\n");
}

/* 2. apply participant-facing translations --------------------------------- */
$json = file_get_contents(__DIR__ . '/es_translations.json');
$T = json_decode($json, true);
if (!is_array($T)) {
    fwrite(STDERR, "[lang-es] could not read es_translations.json\n");
    exit(1);
}
// These public_content pages are owned by bin/tec-setup.php (lab-specific text);
// skip them here so the two scripts don't clobber each other regardless of run order.
$owned_by_tec_setup = ['200001', '200003', '200004', '200005', '200006']; // contact, impressum, mainpage_welcome, privacy_policy, rules
$upd = $pdo->prepare("UPDATE `$lang` SET `es` = :v WHERE lang_id = :id");
$n = 0;
foreach ($T as $id => $es) {
    if (in_array((string) $id, $owned_by_tec_setup, true)) {
        continue;
    }
    $upd->execute([':v' => $es, ':id' => (int) $id]);
    $n += $upd->rowCount();
}
fwrite(STDOUT, "[lang-es] applied translations to $n rows (of " . count($T) . " keys)\n");

/* 3. language display name -------------------------------------------------- */
$pdo->exec("UPDATE `$lang` SET `es` = 'Español'
            WHERE content_type = 'lang' AND content_name = 'lang_name'");

/* 4. options: support email + enabled languages (Spanish first = default) --- */
$setopt = $pdo->prepare("UPDATE `$options` SET option_value = :v WHERE option_name = :n");
foreach ([
    // Cuenta institucional del laboratorio (M365). Debe coincidir con el buzon
    // autenticado en SMTP — ver docs/Correo_institucional.md y bin/correo-institucional.php.
    'support_mail'                   => 'lab.economia@servicios.tec.mx',
    'language_enabled_public'        => 'es,en',
    'language_enabled_participants'  => 'es,en',
    'public_standard_language'       => 'es',   // default language for the public site
    // admin_standard_language left as 'en' on purpose: admin UI stays English.
] as $name => $value) {
    $setopt->execute([':v' => $value, ':n' => $name]);
    fwrite(STDOUT, "[lang-es] option $name = $value (" . $setopt->rowCount() . " row)\n");
}

fwrite(STDOUT, "[lang-es] DONE\n");
