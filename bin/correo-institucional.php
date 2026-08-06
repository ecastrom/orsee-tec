<?php
/**
 * BEER Lab / ORSEE: cambia la identidad de envío de correo a la cuenta
 * institucional lab.economia@servicios.tec.mx (Microsoft 365 / Exchange Online).
 *
 *   heroku run "php bin/correo-institucional.php" -a orsee-beerlab
 *   heroku run "php bin/correo-institucional.php otra.cuenta@servicios.tec.mx"
 *
 * Idempotente. Hace dos cosas en la base de datos:
 *   1. or_options: support_mail -> la cuenta institucional. Ese valor es el
 *      From: de TODO el correo saliente de ORSEE; Microsoft 365 rechaza
 *      cualquier From que no sea el buzon autenticado (o uno con Send As).
 *   2. Verifica enable_editing_of_experiment_sender_email = 'n' (asi el correo
 *      de experimentos tambien sale como support_mail); lo corrige si no.
 *
 * Al final imprime las variables ORSEE_SMTP_* vigentes para revisar la
 * configuracion del transporte. El paso a paso completo (contraseña vs OAuth2)
 * esta en docs/Correo_institucional.md.
 *
 * NOTA: bin/lang-es.php tambien escribe support_mail; ya escribe esta misma
 * direccion, asi que el orden de ejecucion no importa.
 */

require __DIR__ . '/db_bootstrap.php';

$address = $argv[1] ?? 'lab.economia@servicios.tec.mx';
if (!filter_var($address, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "[correo] direccion invalida: $address\n");
    exit(1);
}

$db      = beerlab_db_connect();
$pdo     = $db['pdo'];
$options = $db['prefix'] . 'options';

/* 1. support_mail = From: de todo el correo saliente ----------------------- */
$stmt = $pdo->prepare("UPDATE `$options` SET option_value = :v WHERE option_name = 'support_mail'");
$stmt->execute([':v' => $address]);
fwrite(STDOUT, "[correo] support_mail = $address (" . $stmt->rowCount() . " row)\n");

/* 2. el correo de experimentos debe salir tambien como support_mail -------- */
$cur = $pdo->query("SELECT option_value FROM `$options`
                     WHERE option_name = 'enable_editing_of_experiment_sender_email'")->fetchColumn();
if ($cur !== 'n') {
    $pdo->exec("UPDATE `$options` SET option_value = 'n'
                 WHERE option_name = 'enable_editing_of_experiment_sender_email'");
    fwrite(STDOUT, "[correo] enable_editing_of_experiment_sender_email: '$cur' -> 'n'\n");
} else {
    fwrite(STDOUT, "[correo] enable_editing_of_experiment_sender_email ya es 'n' (ok)\n");
}

/* 3. resumen del transporte SMTP configurado en el entorno ----------------- */
fwrite(STDOUT, "\n[correo] variables de entorno vigentes:\n");
foreach ([
    'ORSEE_MAIL_TRANSPORT', 'ORSEE_SMTP_HOST', 'ORSEE_SMTP_PORT', 'ORSEE_SMTP_SECURE',
    'ORSEE_SMTP_AUTH_TYPE', 'ORSEE_SMTP_USER',
    'ORSEE_SMTP_OAUTH_PROVIDER', 'ORSEE_SMTP_OAUTH_IDENTITY', 'ORSEE_SMTP_OAUTH_TENANT',
] as $var) {
    $v = getenv($var);
    fwrite(STDOUT, sprintf("  %-28s = %s\n", $var, ($v === false || $v === '') ? '(sin definir)' : $v));
}
foreach (['ORSEE_SMTP_PASS', 'ORSEE_SMTP_OAUTH_CLIENT_ID', 'ORSEE_SMTP_OAUTH_CLIENT_SECRET',
          'ORSEE_SMTP_OAUTH_REFRESH_TOKEN'] as $var) {
    $v = getenv($var);
    fwrite(STDOUT, sprintf("  %-28s = %s\n", $var, ($v === false || $v === '') ? '(sin definir)' : '(definida, oculta)'));
}

fwrite(STDOUT, "\n[correo] siguiente paso: configurar el transporte hacia smtp.office365.com\n" .
               "         y probar con: php bin/test-mail.php <destinatario>\n" .
               "         Guia completa: docs/Correo_institucional.md\n");
exit(0);
