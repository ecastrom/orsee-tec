<?php
/**
 * BEER Lab / ORSEE: Spanish labels for the participant profile fields, and
 * Mexico as the default phone country.
 *
 *   heroku run "php bin/fields-es.php" -a orsee-beerlab
 *
 * Field labels/help texts live in or_profile_fields.properties as JSON with
 * per-language maps (name_lang / label_lang / help_text_lang / text_*_lang)
 * that ship with only {de, en}. Without an "es" entry the form shows the
 * English fallback in BOTH language views, so switching the interface language
 * appears to do nothing. This script:
 *   1. Adds "es" to every language map in every field (proper Spanish where
 *      mapped below, otherwise a copy of the English value — never German).
 *   2. Sets phone_default_country = "mx" on phone-type fields (was "us").
 *
 * Idempotent: existing non-empty "es" values are left untouched, so labels
 * customized later in the admin UI are never overwritten.
 */

require __DIR__ . '/db_bootstrap.php';

$db  = beerlab_db_connect();
$pdo = $db['pdo'];
$pf  = $db['prefix'] . 'profile_fields';

/* Spanish for the stock ORSEE field labels/help texts, keyed by lowercased English. */
$MAP = [
    'first name'                              => 'Nombre',
    'last name'                               => 'Apellidos',
    'e-mail'                                  => 'Correo electrónico',
    'email'                                   => 'Correo electrónico',
    'phone number'                            => 'Teléfono',
    'gender'                                  => 'Género',
    'main field of studies'                   => 'Carrera (área de estudios)',
    'field of studies'                        => 'Carrera (área de estudios)',
    'begin of studies'                        => 'Inicio de estudios (año)',
    'start of studies'                        => 'Inicio de estudios (año)',
    'profession'                              => 'Ocupación',
    'interface language'                      => 'Idioma de la interfaz',
    'language'                                => 'Idioma de la interfaz',
    'i would like to receive invitations for' => 'Quiero recibir invitaciones para',
    'invitations'                             => 'Invitaciones',
    'providing this allows us to reach you quickly, e.g. when an experiment gets canceled or similar.'
        => 'Proporcionarlo nos permite contactarte rápidamente, por ejemplo, si un experimento debe cancelarse.',
    'this allows us to contact you in urgent cases, e.g. when a session needs to be cancelled.'
        => 'Esto nos permite contactarte en casos urgentes, por ejemplo, si una sesión debe cancelarse.',
    'please enter your e-mail-address!'       => '¡Por favor, ingresa tu correo electrónico!',
    'the e-mail-address seems not to be valid!' => '¡El correo electrónico no parece ser válido!',
    'your email-address is already registered in our system! you should be receiving invitations from us!'
        => '¡Tu correo electrónico ya está registrado en nuestro sistema! ¡Deberías estar recibiendo nuestras invitaciones!',
    'please enter your first name.'           => 'Por favor, ingresa tu nombre.',
    'please enter your last name.'            => 'Por favor, ingresa tus apellidos.',
];

$unmapped = [];

/** Recursively add "es" to any {de,en} language map. */
function fields_add_es(&$node, $MAP, &$unmapped, &$changed) {
    if (!is_array($node)) {
        return;
    }
    // Fill a missing/empty es — or upgrade an es that is still just a copy of the
    // English value and for which we now have a real Spanish mapping. Genuine
    // customizations (es differing from en) are never touched.
    $es_now = isset($node['es']) ? trim((string) $node['es']) : '';
    $en_now = isset($node['en']) ? trim((string) $node['en']) : '';
    $upgradable = ($es_now !== '' && $es_now === $en_now
        && isset($MAP[mb_strtolower($en_now, 'UTF-8')]));
    if ((isset($node['en']) || isset($node['de']))
        && ($es_now === '' || $upgradable)) {
        $en = isset($node['en']) ? trim((string) $node['en']) : '';
        if ($en !== '') {
            $key = mb_strtolower($en, 'UTF-8');
            $node['es'] = $MAP[$key] ?? $en;          // Spanish if known, else English copy
            if (!isset($MAP[$key])) {
                $unmapped[$en] = true;
            }
            $changed = true;
        } elseif (isset($node['de'])) {
            // No English source at all (both empty or de-only): mirror en/'' to keep shape sane.
            $node['es'] = '';
            $changed = true;
        }
    }
    foreach ($node as $k => &$v) {
        if (is_array($v)) {
            fields_add_es($v, $MAP, $unmapped, $changed);
        }
    }
    unset($v);
}

/** Recursively set phone_default_country wherever the key exists. */
function fields_set_phone_country(&$node, $iso2, &$changed) {
    if (!is_array($node)) {
        return;
    }
    if (array_key_exists('phone_default_country', $node) && $node['phone_default_country'] !== $iso2) {
        $node['phone_default_country'] = $iso2;
        $changed = true;
    }
    foreach ($node as $k => &$v) {
        if (is_array($v)) {
            fields_set_phone_country($v, $iso2, $changed);
        }
    }
    unset($v);
}

$rows = $pdo->query("SELECT mysql_column_name, type, properties FROM `$pf`")->fetchAll();
$upd  = $pdo->prepare("UPDATE `$pf` SET properties = :p WHERE mysql_column_name = :n");

foreach ($rows as $row) {
    $props = json_decode((string) $row['properties'], true);
    if (!is_array($props)) {
        fwrite(STDOUT, "[fields-es] {$row['mysql_column_name']}: properties not JSON - skipped\n");
        continue;
    }
    $changed = false;
    fields_add_es($props, $MAP, $unmapped, $changed);
    if ($row['type'] === 'phone') {
        fields_set_phone_country($props, 'mx', $changed);
    }
    if ($changed) {
        $upd->execute([':p' => json_encode($props, JSON_UNESCAPED_UNICODE), ':n' => $row['mysql_column_name']]);
        fwrite(STDOUT, "[fields-es] {$row['mysql_column_name']}: updated"
            . ($row['type'] === 'phone' ? ' (phone default country -> mx)' : '') . "\n");
    } else {
        fwrite(STDOUT, "[fields-es] {$row['mysql_column_name']}: already ok\n");
    }
}

if ($unmapped) {
    fwrite(STDOUT, "[fields-es] copied from English (no Spanish mapping): "
        . implode(' | ', array_keys($unmapped)) . "\n");
}
fwrite(STDOUT, "[fields-es] DONE\n");
