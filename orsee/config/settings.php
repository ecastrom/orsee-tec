<?php
// part of orsee. see orsee.org
//
// ---------------------------------------------------------------------------
// BEER Lab (Tec de Monterrey) deployment settings.
//
// This file is ENVIRONMENT-DRIVEN: it contains NO secrets. Every credential
// is read from environment variables at runtime, so the same file works on
// Heroku, on Docker, and on a plain VPS without editing.
//
// It replaces the upstream config/settings-dist.php. The original template is
// preserved at install/settings-dist.php for reference.
//
// Recognised environment variables (see .env.example / DEPLOYMENT.md):
//   Database (any ONE of, checked in this order):
//     JAWSDB_URL / JAWSDB_MARIA_URL   (Heroku JawsDB add-on)
//     CLEARDB_DATABASE_URL            (Heroku ClearDB add-on)
//     DATABASE_URL                    (generic / Docker)
//   ...or the discrete vars:
//     ORSEE_DB_HOST, ORSEE_DB_PORT, ORSEE_DB_NAME, ORSEE_DB_USER, ORSEE_DB_PASS
//   Public URL:
//     ORSEE_SERVER_URL     (host only, no protocol, no trailing slash)
//     ORSEE_SERVER_PROTOCOL ("https://" default, or "http://")
//     ORSEE_ROOT_DIRECTORY (URL sub-path; "" when ORSEE is at the web root)
//   Mail (optional, for real email sending via SMTP / PHPMailer):
//     ORSEE_MAIL_TRANSPORT ("mail" default, or "phpmailer")
//     ORSEE_SMTP_HOST, ORSEE_SMTP_PORT, ORSEE_SMTP_SECURE ("tls"/"ssl"/""),
//     ORSEE_SMTP_USER, ORSEE_SMTP_PASS
//   Misc:
//     ORSEE_TIMEZONE       (default "America/Monterrey")
//     ORSEE_TABLE_PREFIX   (default "or_")
//     ORSEE_STOP_ADMIN     ("y"/"n")
// ---------------------------------------------------------------------------

error_reporting(E_ALL & ~E_NOTICE);

// Small helper: env var with default.
if (!function_exists('orsee_env')) {
    function orsee_env($key, $default = null) {
        $v = getenv($key);
        if ($v === false || $v === '') {
            return $default;
        }
        return $v;
    }
}

// ---------------------------------------------------------------------------
// DATABASE CONFIGURATION
// ---------------------------------------------------------------------------
// Heroku MySQL add-ons expose a single URL of the form:
//   mysql://user:password@host:port/databasename?reconnect=true
// We parse whichever one is present; otherwise fall back to discrete vars.

$__db_url =
    orsee_env('JAWSDB_URL',
    orsee_env('JAWSDB_MARIA_URL',
    orsee_env('CLEARDB_DATABASE_URL',
    orsee_env('DATABASE_URL'))));

if ($__db_url) {
    $__db = parse_url($__db_url);
    $site__database_host     = isset($__db['host']) ? $__db['host'] : 'localhost';
    $site__database_port     = isset($__db['port']) ? (string)$__db['port'] : '';
    $site__database_admin_username = isset($__db['user']) ? rawurldecode($__db['user']) : '';
    $site__database_admin_password = isset($__db['pass']) ? rawurldecode($__db['pass']) : '';
    // path is "/dbname" -> strip leading slash; ignore any query string.
    $site__database_database = isset($__db['path']) ? ltrim($__db['path'], '/') : '';
} else {
    $site__database_host     = orsee_env('ORSEE_DB_HOST', 'localhost');
    $site__database_port     = orsee_env('ORSEE_DB_PORT', '');
    $site__database_database = orsee_env('ORSEE_DB_NAME', 'orsee_db');
    $site__database_admin_username = orsee_env('ORSEE_DB_USER', 'orsee_user');
    $site__database_admin_password = orsee_env('ORSEE_DB_PASS', 'orsee_pw');
}

$site__database_type         = "mysql";
$site__database_table_prefix = orsee_env('ORSEE_TABLE_PREFIX', 'or_');

// SSL MySQL connection (only needed for a remote DB reached over SSL).
$site__database_use_ssl  = (orsee_env('ORSEE_DB_USE_SSL', 'n') === 'y');
$site__database_ssl_key  = orsee_env('ORSEE_DB_SSL_KEY',  '');
$site__database_ssl_cert = orsee_env('ORSEE_DB_SSL_CERT', '');
$site__database_ssl_ca   = orsee_env('ORSEE_DB_SSL_CA',   '');

// ---------------------------------------------------------------------------
// SERVER SETTINGS
// ---------------------------------------------------------------------------
// Web server document root (filesystem path, no trailing slash).
// On Heroku the repo lives at /app and the ORSEE web root is /app/orsee.
// We derive it from this file's location so it is correct everywhere.
$settings__root_to_server = dirname(__DIR__); // .../orsee

// URL sub-path where ORSEE is mounted. Empty string = served at the web root
// (this is our default: the web server document root points at orsee/).
$settings__root_directory = orsee_env('ORSEE_ROOT_DIRECTORY', '');

// Public host (no protocol, no trailing slash). On Heroku this is
// <app>.herokuapp.com (or your custom domain). We try, in order:
//   1) ORSEE_SERVER_URL (explicit, recommended once known)
//   2) the request Host header (works for browser traffic on any domain)
//   3) localhost (CLI/cron fallback; overridden by ORSEE_SERVER_URL in prod)
$settings__server_url = orsee_env(
    'ORSEE_SERVER_URL',
    isset($_SERVER['HTTP_HOST']) && $_SERVER['HTTP_HOST'] ? $_SERVER['HTTP_HOST'] : '127.0.0.1'
);

// Protocol. Default https:// (Heroku terminates TLS at the router). Detect a
// forwarded-proto header when present so links are correct behind the proxy.
$__proto_default = 'https://';
if (isset($_SERVER['HTTP_X_FORWARDED_PROTO'])) {
    $__proto_default = ($_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https') ? 'https://' : 'http://';
}
$settings__server_protocol = orsee_env('ORSEE_SERVER_PROTOCOL', $__proto_default);

// ---------------------------------------------------------------------------
// TIMEZONE
// ---------------------------------------------------------------------------
date_default_timezone_set(orsee_env('ORSEE_TIMEZONE', 'America/Monterrey'));

// ---------------------------------------------------------------------------
// INCOMING EMAIL MODULE (optional; only used if you enable the email module)
// ---------------------------------------------------------------------------
$settings__email_server_type = orsee_env('ORSEE_INCOMING_MAIL_TYPE', 'pop3'); // pop3 or imap
$settings__email_server_name = orsee_env('ORSEE_INCOMING_MAIL_HOST', 'mail.foobar.edu');
$settings__email_server_port = orsee_env('ORSEE_INCOMING_MAIL_PORT', '');
$settings__email_username    = orsee_env('ORSEE_INCOMING_MAIL_USER', 'orsee@foobar.edu');
$settings__email_password    = orsee_env('ORSEE_INCOMING_MAIL_PASS', '');
$settings__email_ssl         = (orsee_env('ORSEE_INCOMING_MAIL_SSL', 'n') === 'y');

// ---------------------------------------------------------------------------
// SECURITY: session cookie. Use "secure" cookies whenever we serve over https.
// ---------------------------------------------------------------------------
if (strpos($settings__server_protocol, 'https') === 0) {
    session_set_cookie_params(array('secure' => true, 'httponly' => true, 'samesite' => 'Strict'));
} else {
    session_set_cookie_params(array('httponly' => true, 'samesite' => 'Strict'));
}

// ---------------------------------------------------------------------------
// STOP SITE / TRACKING / DEBUGGING
// ---------------------------------------------------------------------------
$settings__stop_admin_site        = orsee_env('ORSEE_STOP_ADMIN', 'n');
$settings__disable_orsee_tracking = orsee_env('ORSEE_DISABLE_TRACKING', 'n');
$settings__time_debugging_enabled  = orsee_env('ORSEE_DEBUG_TIME', 'n');
$settings__query_debugging_enabled = orsee_env('ORSEE_DEBUG_QUERY', 'n');

// Include path for tagsets. Leave as is.
ini_set("include_path", ini_get("include_path") . ":./tagsets:./../tagsets:./../../tagsets");

// ---------------------------------------------------------------------------
// OUTGOING MAIL TRANSPORT
// ---------------------------------------------------------------------------
// "mail":      PHP mail() (works only if the host has a configured MTA;
//              Heroku dynos do NOT, so use "phpmailer" with an SMTP relay).
// "phpmailer": PHPMailer + SMTP settings below (recommended in the cloud).
$settings__mail_transport = orsee_env('ORSEE_MAIL_TRANSPORT', 'mail');
$settings__sendmail_path  = orsee_env('ORSEE_SENDMAIL_PATH', '/usr/sbin/sendmail');

$settings__phpmailer_host        = orsee_env('ORSEE_SMTP_HOST', 'smtp.sendgrid.net');
$settings__phpmailer_port        = (int) orsee_env('ORSEE_SMTP_PORT', '587');
$settings__phpmailer_smtp_secure = orsee_env('ORSEE_SMTP_SECURE', 'tls'); // "", "tls", or "ssl"
$settings__phpmailer_smtp_auth_type = orsee_env('ORSEE_SMTP_AUTH_TYPE', 'password'); // none/password/oauth2
$settings__phpmailer_username    = orsee_env('ORSEE_SMTP_USER', '');
$settings__phpmailer_password    = orsee_env('ORSEE_SMTP_PASS', '');
$settings__phpmailer_timeout     = (int) orsee_env('ORSEE_SMTP_TIMEOUT', '15');
$settings__phpmailer_debug       = orsee_env('ORSEE_SMTP_DEBUG', 'n'); // y/n
$settings__phpmailer_from_name   = orsee_env('ORSEE_MAIL_FROM_NAME', ''); // display name for the From header

// OAuth2 identities (only used when ORSEE_SMTP_AUTH_TYPE=oauth2).
$settings__phpmailer_smtp_oauth_identities = array(
    "*" => array(
        "provider"       => orsee_env('ORSEE_SMTP_OAUTH_PROVIDER', 'google'),
        "identity"       => orsee_env('ORSEE_SMTP_OAUTH_IDENTITY', ''),
        "client_id"      => orsee_env('ORSEE_SMTP_OAUTH_CLIENT_ID', ''),
        "client_secret"  => orsee_env('ORSEE_SMTP_OAUTH_CLIENT_SECRET', ''),
        "refresh_token"  => orsee_env('ORSEE_SMTP_OAUTH_REFRESH_TOKEN', ''),
        "token_endpoint" => orsee_env('ORSEE_SMTP_OAUTH_TOKEN_ENDPOINT', ''),
        "scopes"         => orsee_env('ORSEE_SMTP_OAUTH_SCOPES', ''),
        "tenant"         => orsee_env('ORSEE_SMTP_OAUTH_TENANT', 'common')
    )
);

?>
