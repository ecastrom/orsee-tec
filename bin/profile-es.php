<?php
/**
 * BEER Lab / ORSEE: add Spanish (es) to the registration-form layout text blocks
 * (profile_form_layout in or_objects). Field labels fall back to English via the
 * code change in participant.php; this covers the instruction/section texts.
 *
 *   heroku run "php bin/profile-es.php" -a orsee-beerlab
 *
 * Idempotent. Uses ORSEE's own load/save helpers via cronheader bootstrap.
 */
chdir(__DIR__ . '/../orsee/admin');
include 'cronheader.php';

$MAP = [
    'the following fields are optional. however, the more fields you complete, the higher is the probability that we invite you based on specific characteristics.'
        => 'Los siguientes campos son opcionales. Sin embargo, cuantos más completes, mayor será la probabilidad de que te invitemos con base en características específicas.',
    'optional fields' => 'Campos opcionales',
    'or'              => 'O',
];

$changed_any = false;
function add_es(&$node, $MAP, &$changed) {
    if (!is_array($node)) return;
    if ((isset($node['en']) || isset($node['de'])) &&
        (!isset($node['es']) || trim((string) $node['es']) === '')) {
        $src = '';
        if (isset($node['en']) && trim((string) $node['en']) !== '') $src = trim((string) $node['en']);
        elseif (isset($node['de']) && trim((string) $node['de']) !== '') $src = trim((string) $node['de']);
        if ($src !== '') {
            $key = mb_strtolower($src, 'UTF-8');
            $node['es'] = $MAP[$key] ?? (isset($node['en']) ? (string) $node['en'] : $src);
            $changed = true;
        }
    }
    foreach ($node as $k => &$v) {
        if (is_array($v)) add_es($v, $MAP, $changed);
    }
    unset($v);
}

foreach (['profile_form_public_current', 'profile_form_public_draft'] as $item) {
    $cfg = options__load_json_object('profile_form_layout', $item, array());
    if (!is_array($cfg) || !$cfg) { fwrite(STDOUT, "[profile-es] $item: empty - skipped\n"); continue; }
    $changed = false;
    add_es($cfg, $MAP, $changed);
    if ($changed) {
        options__save_json_object('profile_form_layout', $item, $cfg);
        fwrite(STDOUT, "[profile-es] $item: es added to text blocks\n");
    } else {
        fwrite(STDOUT, "[profile-es] $item: already has es\n");
    }
}
fwrite(STDOUT, "[profile-es] DONE\n");
