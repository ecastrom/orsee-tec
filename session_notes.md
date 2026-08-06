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

## Lab customization batch (v18, commit e13d2ab)
- Header: removed navy. Colors (or_options, option_type='color', style 'orsee'):
  html_header_top_bar_background & logo_bar_background -> #ffffff (white),
  logo_bar_text -> #0039a6, menu_background -> #0039a6 (Tec blue). Tweak via
  Options -> Colors or re-run bin/tec-setup.php.
- Logo shown once: orsee3_sign.png (the standalone torch) replaced with a 1x1
  transparent PNG; full wordmark orsee3_logo.png remains in the right slot.
- Public content adapted to the lab (bin/tec_content.json + bin/tec-setup.php),
  es+en, in or_lang public_content: mainpage_welcome, rules (participant rules from
  the lab manual: registro único, consentimiento, asistencia/no-show, pagos efectivo
  o transferencia <=3 días, credencial Tec, LFPDPPP), contact (both PIs + address +
  phone), impressum (address/phone/PIs/LFPDPPP), privacy_policy (LFPDPPP + Tec notice).
  Lab address: Av. Eugenio Garza Sada Sur 2501, Col. Tecnológico, C.P. 64700, Monterrey
  NL, Salón A6-101. Tel +52 81 8358 2000.
- Second admin created: adminname 'smaldonado' (Stanislao Maldonado,
  stanislao.maldonado@tec.mx), admin_type 'installer' (full access),
  pw_update_requested=1 (must change on first login). The original hardcoded temp
  password was exposed in git history → invalidated by a reset via
  bin/reset-admin-pw.php (random one-time password, printed to run log only).
- The public menu was already Spanish (v17); user's "still English" was browser cache.

## PENDING - sign-up demographic fields (needs admin UI, NOT scripted)
- Requested fields on registration: sex/gender identity, age, studying-or-working,
  studying at Tec (y/n), major/carrera.
- ORSEE already ships fields: gender, field_of_studies (majors), profession, subjectpool.
  Plan: enable/relabel these for the sign-up form + add age and occupation + Tec-student.
- ORSEE profile-field system is intricate (field specs + subpool applicability +
  ALTER TABLE or_participants). Do via admin Options -> Participant profile fields,
  or drive via browser automation. Scripting blind risks breaking sign-up.

## Branding — Tec logo in header (v14, commit b6f6fee)
- Source art: resources/logo_tec.png (800x211, transparent). Generated with PIL two
  header images (overwrites ORSEE defaults in orsee/tagsets/css/ — re-apply on upgrade):
  - orsee3_logo.png = full "Tecnologico de Monterrey" wordmark (right slot; shown on
    public header at 36px height, and admin header).
  - orsee3_sign.png = torch emblem cropped from the logo (left square slot; admin header).
- Active header is the default template (tagsets/css/orsee_default_header.php); ORSEE
  only uses a style-specific header if style/<style>/orsee_header.php exists (it doesn't).
- Open design choice: logo sits on header logo-bar background #566383 (slate). Tec blue
  on slate is moderate contrast; consider setting the logo-bar background to white
  (Options -> Colors, --orsee-html_header_logo_bar_background) for the standard Tec look.

## Language: Spanish default + English (done, verified) — v15-v17
- Requirement: public site in Spanish (LatAm) by default, English available, German removed,
  admin UI in English. Support email -> ecastrom@tec.mx.
- ORSEE stores languages as COLUMNS in or_lang (shipped en, de). Adding a language =
  add column + translate. Menu/page labels are NOT in or_lang; they live as a JSON
  menu_config in or_objects with per-language label maps.
- Migration scripts (idempotent, run via `heroku run "php bin/<x>"`):
  - `bin/lang-es.php` + `bin/es_translations.json`: adds `es` column, seeds es=en,
    applies 153 participant-facing LatAm-Spanish strings (UI, FAQ, emails, public pages,
    statuses), sets lang_name='Español'. Sets or_options: support_mail=ecastrom@tec.mx,
    language_enabled_public/participants='es,en', **public_standard_language='es'**
    (this last one is what actually makes Spanish the default; admin_standard_language
    left 'en').
  - `bin/menu-es.php`: adds Spanish `es` labels to the or_objects menu_config
    (via ORSEE's own options__load/save_json_object, bootstrapped through
    orsee/admin/cronheader.php). Without it the menu fell back to German.
- To re-apply after data changes: run lang-es.php then menu-es.php. To extend
  translations, edit bin/es_translations.json (keyed by or_lang.lang_id) and re-run.
- German (de) column kept as data (not dropped); simply not offered.
- Verified live: menu Inicio/Registrarse/Iniciar sesión/Calendario/Reglas/Aviso de
  privacidad/Preguntas frecuentes/Aviso legal/Contacto; welcome + rules + FAQ in Spanish;
  English still switchable; footer contact ecastrom@tec.mx.
- Starter content flagged for the user to review (placeholders): FAQ payment amount,
  invitation payment $???, impressum address/phone, privacy ethics wording.

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
- [x] Support email: now lab.economia@servicios.tec.mx (set by
      bin/correo-institucional.php, 2026-08-06 — see institutional email section).
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

## Institutional email account (branch claude/institutional-account-setup-yqgcx6)
- Tec granted the lab **lab.economia@servicios.tec.mx**. Verified 2026-08-06:
  servicios.tec.mx is Exchange Online (MX servicios-tec-mx.mail.protection.outlook.com,
  SPF include:spf.protection.outlook.com -all) → must send via smtp.office365.com
  authenticated as that mailbox; From must equal the authed mailbox.
- Repo changes: bin/correo-institucional.php (sets support_mail -> institutional
  address + forces experiment sender = support_mail, prints env summary);
  bin/test-mail.php rewritten to use ORSEE's real send path (cronheader bootstrap,
  experimentmail__send, supports password AND oauth2 incl. DB-stored tokens,
  secrets redacted in SMTP log); lang-es.php support_mail updated to the
  institutional address (so re-runs don't revert); DEPLOYMENT.md SMTP section
  updated; full runbook in docs/Correo_institucional.md.
- ORSEE 3.4.0 already ships M365 OAuth2 SMTP (XOAUTH2): admin consent page
  /admin/options_oauth_tokens.php stores refresh tokens in or_oauth_tokens
  (table exists — came with install.sql). Provider 'microsoft' auto-derives
  endpoints + scopes (offline_access + outlook.office.com/SMTP.Send).
- LIVE PROGRESS 2026-08-06 (user driving Heroku from PowerShell; agent had no
  Heroku access → next session may run locally WITH heroku CLI + repo access):
  - DONE: branch deployed to Heroku (local clone C:\Users\ecast\Documents\orsee-tec;
    heroku remote re-added via `heroku git:remote -a orsee-beerlab`; pushed
    branch:main). bin/correo-institucional.php ran OK → support_mail switched.
  - DONE: config:set ORSEE_SMTP_HOST/PORT/SECURE/AUTH_TYPE/USER (office365,
    587, tls, password, lab.economia@servicios.tec.mx) → release v34.
  - DONE 2026-08-06: user set the real ORSEE_SMTP_PASS (placeholder replaced).
  - DONE 2026-08-06: end-to-end test passed via
    `heroku run "php bin/test-mail.php ecastrom71@gmail.com" -a orsee-beerlab`:
    smtp.office365.com STARTTLS, AUTH LOGIN → 235 Authentication successful,
    250 2.0.0 OK. **Basic auth works on this mailbox — OAuth2 route NOT needed**
    (docs/Correo_institucional.md keeps the OAuth2/DTI fallback if Tec ever
    disables basic auth: watch for "535 5.7.139" in the cron mail log).
  - DONE 2026-08-06: branch merged into main and pushed to GitHub origin.
  - **PENDING (user, manual):** revoke the old Gmail app password (it was
    exposed in terminal output) in beer.tec.mx@gmail.com → Security →
    App passwords. Gmail SMTP vars were overwritten in place, nothing to unset.

## Payments (Amazon) + Mexican flag (v19-v20)
- Payments: rules + FAQ 60007 now state payment as Amazon gift credits (or equivalent
  electronic voucher). Added payments_type '2' = "Amazon credit"/"Crédito Amazon";
  payments_type '1' es set to 'Efectivo'. Source: bin/tec_content.json (rules),
  bin/es_translations.json (60007), bin/tec-setup.php (payments_type + flag).
- Spanish flag -> Mexico: or_lang lang_flag_iso2 es='mx' (offset -2448px). Set by tec-setup.php.
- BUGFIX: es_translations.json and tec_content.json both wrote public_content pages;
  running lang-es.php after tec-setup.php reverted lab content to generic. Fix:
  lang-es.php now skips ids 200001/200003/200004/200005/200006 (owned by tec-setup.php).
  Run order no longer matters. Both idempotent.

## Form cleanup + captcha + logo (v22-v23)
- German everywhere: root cause was language-map fallback picking first value (de) when
  es missing. Fixed in orsee/tagsets/participant.php (2 resolvers) + html_stuff.php
  (menu_text_from_lang_map) to prefer 'en' before first-value. Now untranslated labels
  show English (never German) in both es and en views. VENDORED EDITS — re-apply on upgrade.
- Registration-form field labels (Vorname/Geschlecht/Studienfach) come from or_profile_fields
  properties (de/en maps); now show English via the fallback. They become Spanish when the
  lab's real demographic fields are configured (still pending).
- Translated (es_translations.json, applied by lang-es.php): page titles (registration_form,
  experiment_calendar, experiments, faq_long, my_registrations, invitations, confirm_registration,
  edit_participant_data, finished_experiments, edit_participant), gender (Hombre/Mujer/No binario),
  subjectpool options. Form instruction blocks via bin/profile-es.php (profile_form_layout).
- Captcha FIX: orsee/public/captcha.php used a relative font path ('../tagsets/fonts/..')
  that broke under Heroku php-fpm CWD, so imagefttext drew no letters (unreadable). Changed to
  __DIR__.'/../tagsets/fonts/Inter-Regular.ttf'. Verified letters now render. GD FreeType is OK.
- Logo: orsee/tagsets/css/orsee_default_header.php rewritten — single Tec logo CENTERED,
  ORSEE taglines removed, torch already blank. VENDORED EDIT.

## Form ES/EN + phone MX + repo cleanup + DTI doc (v26-v28)
- or_profile_fields.properties holds per-field language maps ({de,en}); bin/fields-es.php
  adds es (labels, help texts, validation messages) and sets phone_default_country=mx.
  Idempotent; upgrades es values that are still English copies when a mapping exists;
  never touches customized es. Verified: Spanish view fully Spanish, English view fully
  English, phone widget starts on Mexico.
- es_translations.json now 221 keys (adds experiment types, 38 majors, 12 professions).
- Repo cleanup for IT review: hardcoded temp admin password removed from tec-setup.php
  (now random one-time, printed to run log only); live smaldonado password reset via new
  bin/reset-admin-pw.php (old exposed password invalidated); bin/README.md script
  inventory added; stale branch ref fixed in DEPLOYMENT.md; README structure table updated.
- docs/Propuesta_DTI_ORSEE.tex + .pdf: formal proposal to Tec DTI (Spanish, 5 pp):
  what ORSEE is (Greiner 2015, DOI 10.1007/s40881-015-0004-4), why (anonymity/LFPDPPP +
  subject-pool management), current state, architecture, why Heroku can't be final
  (data residency/ephemeral FS + email deliverability/SPF-DMARC), explicit requests
  (econ-lab@tec.mx, review/approval, DTI deployment, commits to shared repo, two-tier
  support model: lab=first line, DTI=infrastructure).
