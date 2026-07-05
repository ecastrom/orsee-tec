<?php
/**
 * One-off SMTP diagnostic for the BEER Lab ORSEE deployment.
 *
 *   heroku run "php bin/test-mail.php you@example.com" -a orsee-beerlab
 *
 * Sends a single test message through the SAME bundled PHPMailer and the SAME
 * ORSEE_SMTP_* config vars that orsee/tagsets/experimentmail.php uses, so a
 * success here proves ORSEE's outgoing mail path can authenticate and deliver.
 * It does NOT touch the database or the mail queue. Not wired into the app.
 */

$tagsets = dirname(__DIR__) . '/orsee/tagsets';
require_once "$tagsets/classException.php";
require_once "$tagsets/classPHPMailer.php";
require_once "$tagsets/classSMTP.php";

use PHPMailer\PHPMailer\PHPMailer;

$host   = getenv('ORSEE_SMTP_HOST') ?: 'smtp.gmail.com';
$port   = (int) (getenv('ORSEE_SMTP_PORT') ?: 587);
$secure = getenv('ORSEE_SMTP_SECURE');           // "tls" / "ssl" / ""
$user   = getenv('ORSEE_SMTP_USER') ?: '';
$pass   = getenv('ORSEE_SMTP_PASS') ?: '';
$from   = $user;                                  // Gmail rewrites From to the authed account anyway
$to     = $argv[1] ?? $user;

fwrite(STDOUT, "[test-mail] host=$host port=$port secure=" . ($secure ?: '(none)') .
               " user=$user from=$from to=$to\n");

$m = new PHPMailer(true);
$m->CharSet     = 'UTF-8';
$m->isSMTP();
$m->Host        = $host;
$m->Port        = $port;
$m->SMTPSecure  = $secure;
$m->SMTPAuth    = true;
$m->Username    = $user;
$m->Password    = $pass;
$m->Timeout     = 20;
$m->SMTPDebug   = 2;                               // print the SMTP conversation
$m->Debugoutput = function ($str, $level) { fwrite(STDOUT, "  smtp> " . rtrim($str) . "\n"); };

try {
    $m->setFrom($from, 'ORSEE BEER Lab (test)');
    $m->addAddress($to);
    $m->Subject = 'ORSEE SMTP test — ' . date('Y-m-d H:i:s T');
    $m->Body    = "This is a test from the BEER Lab ORSEE deployment on Heroku.\n" .
                  "If you received it, Gmail SMTP delivery works.\n";
    $m->isHTML(false);
    $m->send();
    fwrite(STDOUT, "[test-mail] SENT OK -> $to\n");
    exit(0);
} catch (Throwable $e) {
    fwrite(STDERR, "[test-mail] SEND FAILED: " . $m->ErrorInfo . "\n");
    fwrite(STDERR, "[test-mail] exception: " . $e->getMessage() . "\n");
    exit(1);
}
