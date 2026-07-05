<?php
/**
 * BEER Lab / ORSEE: add Spanish (es) labels to the public/participant menu config.
 *
 *   heroku run "php bin/menu-es.php" -a orsee-beerlab
 *
 * The menu/pages labels are NOT in or_lang; they live as a JSON menu_config in
 * the or_objects table, where each entry carries a per-language label map
 * (menu_term_lang / page_title_lang => { de:.., en:.. }). With no "es" key the
 * renderer falls back to the first value (German). This adds an "es" entry to
 * every language map: a proper Spanish term when known, otherwise the English
 * value (never German). Idempotent. Uses ORSEE's own load/save helpers.
 *
 * Admin menu is intentionally left untouched (admin UI stays English).
 */

chdir(__DIR__ . '/../orsee/admin');
include 'cronheader.php';   // bootstraps ORSEE: settings, DB, functions

$MAP = [
    'main'              => 'Inicio',
    'mainpage'          => 'Inicio',
    'registration'      => 'Registrarse',
    'register'          => 'Registrarse',
    'sign up'           => 'Registrarse',
    'sign-up'           => 'Registrarse',
    'login'             => 'Iniciar sesión',
    'my data'           => 'Mis datos',
    'my registrations'  => 'Mis inscripciones',
    'my enrolments'     => 'Mis inscripciones',
    'experiments'       => 'Experimentos',
    'logout'            => 'Cerrar sesión',
    'calendar'          => 'Calendario',
    'rules'             => 'Reglas',
    'privacy policy'    => 'Aviso de privacidad',
    'privacy'           => 'Aviso de privacidad',
    'data protection'   => 'Aviso de privacidad',
    'faq'               => 'Preguntas frecuentes',
    'faqs'              => 'Preguntas frecuentes',
    'legal notice'      => 'Aviso legal',
    'imprint'           => 'Aviso legal',
    'contact'           => 'Contacto',
];

$unmapped = [];

/** Recursively add an 'es' entry to any language map (one that has en/de). */
function add_es(&$node, $MAP, &$unmapped) {
    if (!is_array($node)) {
        return;
    }
    $looks_like_langmap = (isset($node['en']) || isset($node['de']));
    if ($looks_like_langmap) {
        $src = '';
        if (isset($node['en']) && trim((string) $node['en']) !== '') {
            $src = trim((string) $node['en']);
        } elseif (isset($node['de']) && trim((string) $node['de']) !== '') {
            $src = trim((string) $node['de']);
        }
        if (!isset($node['es']) || trim((string) $node['es']) === '') {
            if ($src === '') {
                $node['es'] = '';
            } elseif (isset($MAP[mb_strtolower($src, 'UTF-8')])) {
                $node['es'] = $MAP[mb_strtolower($src, 'UTF-8')];
            } else {
                // fall back to English (from the en key if present), never German
                $node['es'] = (isset($node['en']) && trim((string) $node['en']) !== '')
                    ? (string) $node['en'] : $src;
                $unmapped[$src] = true;
            }
        }
    }
    foreach ($node as $k => &$v) {
        if (is_array($v)) {
            add_es($v, $MAP, $unmapped);
        }
    }
    unset($v);
}

foreach (['public', 'participant'] as $area) {
    $cfg = options__load_json_object('menu_config', $area, array());
    if (!is_array($cfg) || count($cfg) === 0) {
        fwrite(STDOUT, "[menu-es] $area: no saved menu_config (uses code defaults) - skipped\n");
        continue;
    }
    add_es($cfg, $MAP, $unmapped);
    options__save_json_object('menu_config', $area, $cfg);
    fwrite(STDOUT, "[menu-es] $area: menu_config updated with es labels\n");
}

if ($unmapped) {
    fwrite(STDOUT, "[menu-es] labels copied from English (no Spanish mapping): "
        . implode(' | ', array_keys($unmapped)) . "\n");
}
fwrite(STDOUT, "[menu-es] DONE\n");
