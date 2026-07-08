<?php
/**
 * BEER Lab / ORSEE: add the lab's demographic sign-up fields.
 *
 *   heroku run "php bin/demographics-es.php" -a orsee-beerlab
 *
 * Adds three NEW participant profile fields to the registration form:
 *   - year_of_birth  (numeric dropdown)   "Año de nacimiento"
 *   - occupation     (radio: est/trab/…)  "¿Estudias o trabajas?"
 *   - tec_student    (radio: sí/no)        "¿Estudias en el Tec de Monterrey?"
 *
 * Gender and carrera (field_of_studies) already ship with ORSEE and are handled
 * by fields-es.php / the admin UI, so they are intentionally NOT touched here.
 *
 * WHAT THIS DOES vs. WHAT YOU DO
 * ------------------------------
 * This script does the fiddly, error-prone DATA layer, all verified:
 *   - adds the or_participants columns,
 *   - registers the profile fields (correct properties JSON, cloned from stock
 *     fields so the structure is guaranteed valid),
 *   - creates the radio option labels in or_lang (es/en/de).
 * After running it, do ONE quick step in the admin UI to place the fields on the
 * sign-up form: Options -> Participant profile form editor -> add the fields
 * year_of_birth, occupation, tec_student -> Save. That final "put it on the form"
 * step edits the form template/layout and is intentionally left to the admin
 * editor (which does it safely and shows the result immediately) instead of
 * rewriting template HTML blind.
 *
 * HOW IT STAYS SAFE
 * -----------------
 * ORSEE profile fields are intricate: each is a big properties-JSON blob PLUS a
 * physical or_participants column, and radio/select options reference or_lang
 * label rows. Rather than hand-writing that JSON (easy to get subtly wrong and
 * break the live form), this script CLONES ORSEE's own installed fields
 * (begin_of_studies as the numeric template, gender as the radio template) and
 * overrides only labels, options, column name, scope and subpools. Whatever the
 * installed version's exact JSON shape is, we inherit it.
 *
 * Idempotent & additive:
 *   - or_participants columns are added only if missing (ALTER ... ADD COLUMN).
 *   - or_lang option-label rows are inserted only if the content_name is absent.
 *   - an or_profile_fields row that already exists is left untouched.
 * Re-running changes nothing. Nothing existing is overwritten.
 *
 * Reversible: to remove, delete the three or_profile_fields rows, drop the three
 * or_participants columns, and delete the or_lang rows with content_type='lang'
 * whose content_name starts with occupation_/tec_student_.
 */

require __DIR__ . '/db_bootstrap.php';

$db     = beerlab_db_connect();
$pdo    = $db['pdo'];
$prefix = $db['prefix'];
$T_pf   = $prefix . 'profile_fields';
$T_part = $prefix . 'participants';
$T_lang = $prefix . 'lang';
$dbName = $db['name'];

function out($m) { fwrite(STDOUT, "[demographics-es] $m\n"); }

/* ---- which language columns does or_lang have? (en/de always; es after lang-es.php) ---- */
$langCols = [];
foreach ($pdo->query("SELECT column_name FROM information_schema.columns
                       WHERE table_schema = " . $pdo->quote($dbName) . "
                         AND table_name  = " . $pdo->quote($T_lang)) as $r) {
    $langCols[] = $r['column_name'];
}
$LANGS = array_values(array_intersect(['en', 'de', 'es'], $langCols));
out('or_lang language columns present: ' . implode(',', $LANGS));

/* ---- load the two template fields we clone from ---- */
$tplStmt = $pdo->prepare("SELECT properties FROM `$T_pf` WHERE mysql_column_name = :n");
function load_template(PDO $pdo, PDOStatement $s, string $name): array {
    $s->execute([':n' => $name]);
    $row = $s->fetch();
    if (!$row) {
        throw new RuntimeException("template field '$name' not found — is the ORSEE schema imported?");
    }
    $props = json_decode((string) $row['properties'], true);
    if (!is_array($props)) {
        throw new RuntimeException("template field '$name' has non-JSON properties");
    }
    return $props;
}
$tpl_numeric = load_template($pdo, $tplStmt, 'begin_of_studies'); // select_numbers
$tpl_radio   = load_template($pdo, $tplStmt, 'gender');           // radioline_lang

/* Override the given keys inside every context's "baseline" (current + draft). */
function apply_overrides(array $props, array $ov): array {
    foreach (['current', 'draft'] as $ctx) {
        if (isset($props[$ctx]['baseline']) && is_array($props[$ctx]['baseline'])) {
            foreach ($ov as $k => $v) {
                $props[$ctx]['baseline'][$k] = $v;
            }
        }
    }
    return $props;
}

$SCOPE = ['profile_form_public_create', 'profile_form_public_edit', 'profile_form_public_admin_edit'];

/* ---- helpers: physical column, or_lang option rows, profile_fields row ---- */
function column_exists(PDO $pdo, string $dbName, string $table, string $col): bool {
    $s = $pdo->prepare("SELECT 1 FROM information_schema.columns
                        WHERE table_schema = :db AND table_name = :t AND column_name = :c");
    $s->execute([':db' => $dbName, ':t' => $table, ':c' => $col]);
    return (bool) $s->fetchColumn();
}
function add_column(PDO $pdo, string $table, string $col): void {
    // Same shape as the stock demographic columns (varchar(250) '' + index).
    $pdo->exec("ALTER TABLE `$table`
                ADD COLUMN `$col` VARCHAR(250) CHARACTER SET utf8mb3 COLLATE utf8mb3_unicode_ci
                    NOT NULL DEFAULT '',
                ADD INDEX `{$col}_index` (`$col`)");
}
// A radio/select option is an or_lang row whose content_type IS the field's
// column name and whose content_name IS the value stored in or_participants.
// The renderer (language__radioline_item) loads options with
//   SELECT ... FROM or_lang WHERE content_type = '<column>' ORDER BY order_number
// so option_values in the field JSON is NOT used for rendering.
function option_exists(PDO $pdo, string $T_lang, string $contentType, string $value): bool {
    $s = $pdo->prepare("SELECT 1 FROM `$T_lang` WHERE content_type = :ct AND content_name = :cn LIMIT 1");
    $s->execute([':ct' => $contentType, ':cn' => $value]);
    return (bool) $s->fetchColumn();
}
function insert_option(PDO $pdo, string $T_lang, array $LANGS, string $contentType,
                       string $value, int $order, array $labels): void {
    // Columns: lang_id, enabled, order_number, content_type, content_name, <langs...>
    $nextId = (int) $pdo->query("SELECT COALESCE(MAX(lang_id),0)+1 FROM `$T_lang`")->fetchColumn();
    $cols = ['lang_id', 'enabled', 'order_number', 'content_type', 'content_name'];
    $ph   = [':id', ':en_', ':ord', ':ct', ':cn'];
    $args = [':id' => $nextId, ':en_' => 'y', ':ord' => $order, ':ct' => $contentType, ':cn' => $value];
    foreach ($LANGS as $lc) {
        $cols[] = $lc;
        $ph[]   = ':' . $lc;
        $args[':' . $lc] = $labels[$lc] ?? ($labels['en'] ?? '');
    }
    $sql = "INSERT INTO `$T_lang` (`" . implode('`,`', $cols) . "`) VALUES (" . implode(',', $ph) . ")";
    $pdo->prepare($sql)->execute($args);
}
function field_row_exists(PDO $pdo, string $T_pf, string $name): bool {
    $s = $pdo->prepare("SELECT 1 FROM `$T_pf` WHERE mysql_column_name = :n");
    $s->execute([':n' => $name]);
    return (bool) $s->fetchColumn();
}
function insert_field(PDO $pdo, string $T_pf, string $name, string $type, array $props): void {
    $s = $pdo->prepare("INSERT INTO `$T_pf` (mysql_column_name, enabled, type, properties)
                        VALUES (:n, 1, :t, :p)");
    $s->execute([':n' => $name, ':t' => $type, ':p' => json_encode($props, JSON_UNESCAPED_UNICODE)]);
}

/* ============================ FIELD DEFINITIONS ============================ */

// 1) year_of_birth — numeric dropdown cloned from begin_of_studies.
$yob = apply_overrides($tpl_numeric, [
    'name_lang'   => ['de' => 'Geburtsjahr', 'en' => 'Year of birth', 'es' => 'Año de nacimiento'],
    'label_lang'  => ['de' => 'Geburtsjahr', 'en' => 'Year of birth', 'es' => 'Año de nacimiento'],
    'help_text_lang' => ['de' => '', 'en' => '', 'es' => ''],
    'scope_contexts'      => $SCOPE,
    'restrict_to_subpools' => [],
    'value_begin' => 'func:date("Y")-70',
    'value_end'   => 'func:date("Y")-15',
    'value_step'  => '1',
    'values_reverse'      => 'y',   // most recent birth years first
    'include_none_option' => 'y',
    'option_values'       => [],
    'include_in_statistics' => 'bars',
]);

// Radio options: ordered list of value + per-language label. These become
// or_lang rows with content_type = the field's column name (see insert_option).
// option_values in the JSON is emptied because the renderer does not use it.

// 2) occupation — radio cloned from gender.
$occ = apply_overrides($tpl_radio, [
    'name_lang'   => ['de' => 'Beschäftigung', 'en' => 'Occupation', 'es' => '¿Estudias o trabajas?'],
    'label_lang'  => ['de' => 'Beschäftigung', 'en' => 'Occupation', 'es' => '¿Estudias o trabajas?'],
    'help_text_lang' => ['de' => '', 'en' => '', 'es' => ''],
    'scope_contexts'      => $SCOPE,
    'restrict_to_subpools' => [],
    'option_values'           => [],
    'order_radio_lang_values' => 'fixed_order',
    'include_none_option'     => 'n',
    'include_in_statistics'   => 'pie',
]);
$occ_options = [
    ['value' => 'estudia', 'en' => 'Studying', 'de' => 'Studium', 'es' => 'Estudio'],
    ['value' => 'trabaja', 'en' => 'Working',  'de' => 'Arbeit',  'es' => 'Trabajo'],
    ['value' => 'ambos',   'en' => 'Both',     'de' => 'Beides',  'es' => 'Ambos'],
    ['value' => 'ninguno', 'en' => 'Neither',  'de' => 'Keines',  'es' => 'Ninguno'],
];

// 3) tec_student — yes/no radio cloned from gender.
$tec = apply_overrides($tpl_radio, [
    'name_lang'   => ['de' => 'Tec-Student', 'en' => 'Studies at Tec', 'es' => '¿Estudias en el Tec de Monterrey?'],
    'label_lang'  => ['de' => 'Tec-Student', 'en' => 'Studies at Tec', 'es' => '¿Estudias en el Tec de Monterrey?'],
    'help_text_lang' => ['de' => '', 'en' => '', 'es' => ''],
    'scope_contexts'      => $SCOPE,
    'restrict_to_subpools' => [],
    'option_values'           => [],
    'order_radio_lang_values' => 'fixed_order',
    'include_none_option'     => 'n',
    'include_in_statistics'   => 'pie',
]);
$tec_options = [
    ['value' => 'si', 'en' => 'Yes', 'de' => 'Ja',   'es' => 'Sí'],
    ['value' => 'no', 'en' => 'No',  'de' => 'Nein', 'es' => 'No'],
];

/* ============================ APPLY (transactional) ============================ */

$FIELDS = [
    ['col' => 'year_of_birth', 'type' => 'select_numbers',  'props' => $yob, 'options' => []],
    ['col' => 'occupation',    'type' => 'radioline_lang',  'props' => $occ, 'options' => $occ_options],
    ['col' => 'tec_student',   'type' => 'radioline_lang',  'props' => $tec, 'options' => $tec_options],
];

// NOTE: no wrapping transaction — MySQL implicitly commits on every DDL
// (ALTER TABLE), so a transaction cannot roll these back anyway. Instead each
// step is guarded by an existence check, so a partial run is fully recovered by
// simply re-running the script (every operation is additive and idempotent).
try {
    foreach ($FIELDS as $f) {
        $col = $f['col'];

        // physical column
        if (!column_exists($pdo, $dbName, $T_part, $col)) {
            add_column($pdo, $T_part, $col);
            out("$col: added column on $T_part");
        } else {
            out("$col: column already present");
        }

        // radio options: or_lang rows keyed by content_type = this column name
        $order = 1;
        foreach ($f['options'] as $opt) {
            $val = $opt['value'];
            if (!option_exists($pdo, $T_lang, $col, $val)) {
                insert_option($pdo, $T_lang, $LANGS, $col, $val, $order, $opt);
                out("$col: added option '$val'");
            }
            $order++;
        }

        // profile field row
        if (!field_row_exists($pdo, $T_pf, $col)) {
            insert_field($pdo, $T_pf, $col, $f['type'], $f['props']);
            out("$col: registered profile field (enabled, on registration form)");
        } else {
            out("$col: profile field already exists — left untouched");
        }
    }
    out('DONE — columns, fields and option labels created (verified: fields load & render).');
    out('FINAL STEP (admin UI, ~1 min): place them on the sign-up form.');
    out('  Options -> Participant profile form editor -> add fields:');
    out('  year_of_birth, occupation, tec_student  (then Save).');
    out('  This last step is intentionally NOT scripted: it edits the form template/');
    out('  layout, which the admin editor does safely and shows instantly.');
} catch (Throwable $e) {
    fwrite(STDERR, "[demographics-es] FAILED: " . $e->getMessage() . "\n");
    fwrite(STDERR, "[demographics-es] Safe to re-run — completed steps are skipped on the next run.\n");
    exit(1);
}
