# Session notes — orsee-tec

Concise context-recovery reference. Full deployment guide lives in `DEPLOYMENT.md`.

## What this repo is
Heroku/Docker deployment of **ORSEE 3.4.0** (subject-pool recruitment + session
scheduling) for the **BEER Lab**, Tec de Monterrey. ORSEE code is vendored in
`orsee/`; all config comes from environment variables (`orsee/config/settings.php`).

## Live deployment (Heroku) — DONE 2026-07-04
- **App name:** `orsee-beerlab`  (Heroku account `beer.tec.mx@gmail.com`)
- **Live URL:** https://orsee-beerlab-d86d8a0b91dc.herokuapp.com
  (Heroku appended the random `-d86d8a0b91dc` suffix; the *app name* is still `orsee-beerlab`)
- **Admin panel:** `/admin/` — first-login creds `orsee_install` / `install`
  → **MUST change password + email immediately** (DEPLOYMENT.md §5, not yet done).
- **Stack:** heroku-24 (upgraded from deprecated heroku-22 on 2026-07-04).
- **Runtime:** PHP **8.5.7** (buildpack picks newest since composer.json is `php >=8.1`,
  unpinned). ORSEE 3.4.0 is old code but admin + public pages render fine. Watch item:
  if PHP 8.5 deprecations bite later, pin `"require": {"php": "8.3.*"}` in composer.json.
- **DB:** JawsDB Kitefin (free MySQL add-on), `JAWSDB_URL` set. Schema auto-imported by
  `bin/release.php` on first deploy (idempotent on redeploys — verified).
- **Web dyno:** Basic (~US$5/mo, billed per second).
- **Config vars set:** ORSEE_SERVER_PROTOCOL=https://, ORSEE_SERVER_URL=<host above>,
  ORSEE_ROOT_DIRECTORY="", ORSEE_TIMEZONE=America/Monterrey, ORSEE_MAIL_TRANSPORT=phpmailer.

## Fixes made this session
- **Unstyled UI / stray modal fragments + broken logos** (v12, commit `d22e869`):
  the `/tagsets/` deny rule blocked the whole dir, but ORSEE serves framework CSS,
  fonts, logos and JS from `tagsets/css|fonts|js`. With bulma.min.css 403ing, the
  `.modal`/`.is-hidden` classes never loaded → popup templates showed at page top and
  logos broke. Changed rule to deny only `tagsets/*.php`; static assets now 200, PHP
  libraries + config/settings.php + install.sql still 403. (Browser hard-refresh needed
  once, since the 403'd CSS was cached.)

- **403 on all pages** → root cause: Heroku PHP buildpack's `DirectoryIndex` is
  `index.html` only; ORSEE entry points are `index.php`. Added
  `DirectoryIndex index.php index.html` to `heroku/apache2.conf` (commit `2cfe9cf`).
  Deployed as release v6. Verified: /public/ 200, /admin/ login renders, /install/ &
  /config/ correctly 403.

## Email (SMTP) — DONE 2026-07-04, verified delivering
- Sending account: **beer.tec.mx@gmail.com** via Gmail SMTP (smtp.gmail.com:587 TLS,
  auth=password). App Password stored in `ORSEE_SMTP_PASS` config var (release v9).
  NOTE: first app password given was from a *personal* gmail; replaced with one from
  the lab account. User must match the authenticated account.
- Verified end-to-end with `bin/test-mail.php` (new diagnostic, uses ORSEE's bundled
  PHPMailer + live config vars, no DB/queue): `heroku run "php bin/test-mail.php <to>"`.
  Result: Gmail `235 Accepted` + `250 OK`, test mail delivered to lab inbox.
- Gmail rewrites the From to beer.tec.mx@gmail.com regardless of ORSEE's setting.
  Free-Gmail limit ~500 recipients/day — fine for testing, not big blasts.
- tec.mx was ruled out for SMTP: it's Microsoft 365 (MX tec-mx.mail.protection.outlook.com),
  basic-auth SMTP is disabled + strict DMARC, so can't send as @tec.mx without Tec IT.

## Cron (mail queue flush) — DONE 2026-07-04, scheduled
- `scheduler:standard` add-on installed (scheduler-polished-94615).
- `bin/cron.sh` verified to run cleanly on a one-off dyno (empty queue, exits 0; only a
  cosmetic `Undefined array key "count"` PHP 8.5 warning from orsee/tagsets/cronjobs.php:251).
- Scheduler job created in dashboard: Command `bash bin/cron.sh`, **Every 10 minutes**,
  Basic dyno. Confirmed via screenshot (first run pending next 10-min tick).

## Pending / next steps
- [ ] First-login hardening: change `orsee_install` password + email (DEPLOYMENT.md §5).
- [ ] In admin UI set Options → General Settings → System support email address to
      beer.tec.mx@gmail.com (matches the Gmail sending account).
- [ ] BEER Lab initial config: labs, subpools, participant fields, Spanish templates
      (DEPLOYMENT.md §6).
- [ ] Push commits to GitHub `origin` too (2cfe9cf DirectoryIndex fix + c2a66ea
      test-mail.php are only on the `heroku` remote). Not done — not yet requested.
- [ ] Ephemeral filesystem: uploads/webalizer don't persist across dyno restarts; nearly
      all ORSEE data is in MySQL so low impact for a teaching lab (DEPLOYMENT.md §3.6).
- [ ] Cosmetic: PHP 8.5 warnings from vendored ORSEE code (e.g. cronjobs.php:251). Not
      fixed to avoid diverging from upstream `orsee/`; suppress or patch later if desired.

## Environment caveat (this machine/network)
- The active network uses a filtered DNS resolver (`sdmclogin.cn` / 192.168.80.1) that
  **intermittently fails to resolve** `git.heroku.com`, `*.logs.heroku.com`,
  `buildpack-registry.heroku.com`. Retrying usually succeeds.
- Run heroku **git push / logs from PowerShell** (host network), not the Bash tool
  (Bash runs in a sandbox that can't resolve those hosts at all). `heroku` API commands
  (create/config/addons) work from either.
