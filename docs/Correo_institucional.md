# Correo institucional: `lab.economia@servicios.tec.mx`

Guía para migrar el correo saliente de ORSEE de la cuenta provisional de Gmail
(`beer.tec.mx@gmail.com`) a la cuenta institucional del laboratorio,
**lab.economia@servicios.tec.mx**.

## Hechos verificados (2026-08-06)

- `servicios.tec.mx` está en **Microsoft 365 / Exchange Online**:
  - MX: `servicios-tec-mx.mail.protection.outlook.com`
  - SPF: `v=spf1 include:spf.protection.outlook.com … -all` (estricto)
- Consecuencia: el correo **debe** salir por `smtp.office365.com` autenticado
  como ese buzón. Cualquier otro relay (Gmail, SendGrid) que ponga
  `From: lab.economia@servicios.tec.mx` **fallará SPF/DMARC** y será rechazado
  o marcado como spam.
- En Microsoft 365 el `From:` debe ser el buzón autenticado (o uno con permiso
  *Send As*). ORSEE usa `support_mail` (or_options) como `From:` de todo el
  correo saliente, así que ese valor debe ser la cuenta institucional.
- Microsoft está retirando la autenticación básica de SMTP AUTH (usuario +
  contraseña): bloqueada por defecto a partir de finales de **diciembre 2026**
  y con retiro definitivo posterior. Muchos tenants (incluido `tec.mx`) ya la
  tienen deshabilitada. La ruta con futuro es **OAuth2**, que ORSEE ya trae
  integrado (página de admin `/admin/options_oauth_tokens.php` + tabla
  `or_oauth_tokens`).

## Paso 1 — Cambiar la identidad de envío en ORSEE

```bash
heroku run "php bin/correo-institucional.php" -a orsee-beerlab
```

Idempotente. Fija `support_mail = lab.economia@servicios.tec.mx` y asegura que
el correo de experimentos también salga con esa dirección
(`enable_editing_of_experiment_sender_email = n`). Al final imprime las
variables `ORSEE_SMTP_*` vigentes.

## Paso 2 — Configurar el transporte SMTP

### Ruta A: contraseña (probar primero; puede estar deshabilitada)

Funciona solo si el tenant/buzón tiene habilitado *Authenticated SMTP* (SMTP
AUTH básico). Vale la pena probarla porque no requiere nada de la DTI más que
la contraseña del buzón:

```bash
heroku config:set -a orsee-beerlab \
  ORSEE_SMTP_HOST="smtp.office365.com" \
  ORSEE_SMTP_PORT="587" \
  ORSEE_SMTP_SECURE="tls" \
  ORSEE_SMTP_AUTH_TYPE="password" \
  ORSEE_SMTP_USER="lab.economia@servicios.tec.mx" \
  ORSEE_SMTP_PASS="<contraseña del buzón>" \
  ORSEE_MAIL_FROM_NAME="Laboratorio de Economía Conductual y Experimental"
```

`ORSEE_MAIL_FROM_NAME` es el nombre visible del remitente (encabezado `From:`);
sin él los clientes de correo muestran solo `lab.economia`.

Probar (Paso 3). Si el servidor responde algo como
`535 5.7.139 Authentication unsuccessful, basic authentication is disabled`,
la Ruta A no está disponible: pasar a la Ruta B.

### Ruta B: OAuth2 (recomendada; requiere un registro de aplicación)

**Qué pedir a la DTI / TI** (copiar-pegar):

> Para que nuestra plataforma de reclutamiento (ORSEE) envíe correo como
> `lab.economia@servicios.tec.mx` vía SMTP con autenticación moderna (OAuth2),
> solicitamos:
>
> 1. Un **registro de aplicación en Microsoft Entra ID** con permiso
>    **delegado** `SMTP.Send` (Office 365 Exchange Online) y `offline_access`,
>    tipo de cliente *Web*, con URI de redirección:
>    `https://orsee-beerlab-d86d8a0b91dc.herokuapp.com/admin/options_oauth_tokens.php`
>    Necesitamos el **Application (client) ID**, un **client secret** y el
>    **Directory (tenant) ID**.
> 2. Que el buzón `lab.economia@servicios.tec.mx` tenga habilitado
>    **Authenticated SMTP** (`Set-CASMailbox -SmtpClientAuthenticationDisabled
>    $false`). Nota: esto es necesario aun usando OAuth2; la autenticación
>    básica del tenant puede seguir deshabilitada.

Con esos datos:

```bash
heroku config:set -a orsee-beerlab \
  ORSEE_SMTP_HOST="smtp.office365.com" \
  ORSEE_SMTP_PORT="587" \
  ORSEE_SMTP_SECURE="tls" \
  ORSEE_SMTP_AUTH_TYPE="oauth2" \
  ORSEE_SMTP_OAUTH_PROVIDER="microsoft" \
  ORSEE_SMTP_OAUTH_IDENTITY="lab.economia@servicios.tec.mx" \
  ORSEE_SMTP_OAUTH_CLIENT_ID="<Application (client) ID>" \
  ORSEE_SMTP_OAUTH_CLIENT_SECRET="<client secret>" \
  ORSEE_SMTP_OAUTH_TENANT="<Directory (tenant) ID>"
```

(Con el proveedor `microsoft`, ORSEE deriva solo los endpoints y los scopes
`offline_access https://outlook.office.com/SMTP.Send`; no hay que configurarlos.)

Luego, **una sola vez**, autorizar la cuenta:

1. Entrar a `/admin/` y abrir **Options → OAuth tokens**
   (`/admin/options_oauth_tokens.php`).
2. Pulsar el botón de generar/abrir la URL de autorización e **iniciar sesión
   con `lab.economia@servicios.tec.mx`** (no con la cuenta personal).
3. Aceptar los permisos. El *refresh token* queda guardado en la base de datos
   (`or_oauth_tokens`) y se renueva solo; no se necesita repetir el paso salvo
   que se revoque o caduque por política.

## Paso 3 — Probar

```bash
heroku run "php bin/test-mail.php tu-correo@tec.mx" -a orsee-beerlab
```

`bin/test-mail.php` usa la ruta real de envío de ORSEE (mismo PHPMailer, mismo
`support_mail` como `From:`, mismos tokens OAuth de la BD) e imprime la
conversación SMTP con secretos censurados. Revisar que el mensaje llegue y que
el remitente sea `lab.economia@servicios.tec.mx`.

## Paso 4 — Limpieza (solo después de que la prueba pase)

- Quitar las variables de Gmail que ya no aplican (p. ej. si quedó
  `ORSEE_SMTP_USER`/`ORSEE_SMTP_PASS` de Gmail en la Ruta B):
  `heroku config:unset -a orsee-beerlab <VAR>`.
- En `/admin/`: Options → General Settings → verificar que el *support email*
  muestre la cuenta institucional (el script del Paso 1 ya la fijó).
- Actualizar el contacto público si se desea que los participantes escriban a
  la cuenta institucional (páginas de contacto en `bin/tec_content.json` +
  `bin/tec-setup.php`).

## Notas

- **No borrar la configuración de Gmail antes de que la prueba pase**; mientras
  tanto ORSEE sigue enviando por Gmail y el sistema queda operativo.
- Límites de envío de Exchange Online (estándar): ~30 mensajes/minuto y
  10,000 destinatarios/día — muy por encima del límite de ~500/día de Gmail
  gratuito. La cola de ORSEE (cron cada 10 min) respeta ritmos razonables.
- Si la DTI prefiere otra vía (relay interno autorizado, *High Volume Email*),
  el transporte se cambia solo con config vars; el código no cambia.
