<?php
/**
 * One-off SMTP diagnostic for the BEER Lab ORSEE deployment.
 *
 *   heroku run "php bin/test-mail.php you@example.com" -a orsee-beerlab
 *
 * Sends a single test message through ORSEE's REAL outgoing-mail path
 * (orsee/tagsets/experimentmail.php): same PHPMailer, same ORSEE_SMTP_* config
 * vars, same From resolution (or_options support_mail), and — when
 * ORSEE_SMTP_AUTH_TYPE=oauth2 — the same token machinery, including refresh
 * tokens stored in or_oauth_tokens by the admin consent page
 * (/admin/options_oauth_tokens.php). A success here proves ORSEE can
 * authenticate and deliver. It does NOT touch the mail queue.
 *
 * Requires DB access (reads support_mail and, with OAuth2, the stored tokens).
 * The SMTP conversation is printed with tokens/passwords redacted.
 */

if (php_sapi_name() !== 'cli') {
    exit(1);
}

$to = $argv[1] ?? '';
if (!filter_var($to, FILTER_VALIDATE_EMAIL)) {
    fwrite(STDERR, "usage: php bin/test-mail.php recipient@example.com\n");
    exit(1);
}

// Bootstrap ORSEE exactly like the cron entry point does (settings, DB, tagsets).
chdir(__DIR__ . '/../orsee/admin');
include 'cronheader.php';

// Force the SMTP conversation into the run log (experimentmail redacts secrets).
$settings__phpmailer_debug = 'y';
ini_set('error_log', '');            // CLI: error_log() -> stderr
ini_set('display_errors', 'stderr');

$auth_type = experimentmail__phpmailer_smtp_auth_type();
fwrite(STDOUT, "[test-mail] transport=" . ($settings__mail_transport ?? 'mail') .
               " host=" . ($settings__phpmailer_host ?? '?') .
               " port=" . ($settings__phpmailer_port ?? '?') .
               " secure=" . (($settings__phpmailer_smtp_secure ?? '') ?: '(none)') .
               " auth=$auth_type\n");
fwrite(STDOUT, "[test-mail] from (support_mail)=" . ($settings['support_mail'] ?? '(unset!)') .
               "  to=$to\n");

if (!experimentmail__use_phpmailer()) {
    fwrite(STDERR, "[test-mail] ORSEE_MAIL_TRANSPORT is not 'phpmailer'; this deployment " .
                   "cannot send via SMTP. Aborting.\n");
    exit(1);
}

$subject = 'ORSEE SMTP test - ' . date('Y-m-d H:i:s T');
$body    = "This is a test from the BEER Lab ORSEE deployment.\n" .
           "Transport: PHPMailer/SMTP, auth type: $auth_type.\n" .
           "If you received it, ORSEE's outgoing mail path works.\n";

$ok = experimentmail__send($to, $subject, $body, "");

if ($ok) {
    fwrite(STDOUT, "[test-mail] SENT OK -> $to\n");
    exit(0);
}
fwrite(STDERR, "[test-mail] SEND FAILED (see SMTP log above; with oauth2, check that the\n" .
               "            consent flow was completed in /admin/options_oauth_tokens.php\n" .
               "            or that ORSEE_SMTP_OAUTH_REFRESH_TOKEN is set)\n");
exit(1);
