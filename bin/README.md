# `bin/` — scripts de operación y migración

Inventario completo para revisión (todos los scripts son idempotentes: se pueden
re-ejecutar sin dañar datos; ninguno contiene credenciales — la conexión a MySQL
se resuelve desde variables de entorno vía `db_bootstrap.php`).

## Ciclo de despliegue

| Script | Qué hace | Cuándo corre |
|---|---|---|
| `db_bootstrap.php` | Resuelve la conexión MySQL desde el entorno (`JAWSDB_URL` → `DATABASE_URL` → `ORSEE_DB_*`) y abre PDO. Librería compartida, no se ejecuta directo. | (require de los demás) |
| `release.php` | Importa el esquema `orsee/install/install.sql` **solo si la base está vacía**; en despliegues posteriores no toca datos. | Fase *release* de Heroku, automático en cada deploy |
| `import-db.php` | Importación manual del esquema (mismo comportamiento; `--force` para reinicializar). | Manual |
| `cron.sh` | Ejecuta `orsee/admin/cron.php` (cola de correo, recordatorios, tareas periódicas). | Heroku Scheduler cada 10 min / cron del servidor |
| `backup.sh` | Respaldo `mysqldump` con retención configurable (`BACKUP_DIR`, `BACKUP_KEEP_DAYS`). | Camino Docker/VPS |
| `bootstrap.sh` | Arranque inicial del stack Docker. | Camino Docker/VPS |

## Configuración inicial del BEER Lab (migraciones de datos)

Estas migraciones adaptan la instalación genérica de ORSEE al laboratorio. Orden
recomendado: `lang-es.php` → `menu-es.php` → `profile-es.php` → `fields-es.php` →
`tec-setup.php` (aunque el orden no importa: cada una es idempotente y no pisan
el trabajo de las otras).

| Script | Qué hace |
|---|---|
| `lang-es.php` + `es_translations.json` | Instala el idioma **español** como idioma público por defecto (columna `es` en `or_lang`, ~220 cadenas traducidas: interfaz, FAQ, correos, estados, opciones), habilita `es,en`, retira alemán de la oferta, fija `support_mail`. |
| `menu-es.php` | Agrega etiquetas en español al menú público (`menu_config` en `or_objects`). |
| `profile-es.php` | Agrega español a los bloques de instrucciones del formulario de registro (`profile_form_layout`). |
| `fields-es.php` | Agrega español a las etiquetas/ayudas de los campos de perfil (`or_profile_fields`) y fija **México** como país por defecto del teléfono. |
| `tec-setup.php` | Colores institucionales del encabezado, contenido de las páginas públicas (bienvenida, reglas, contacto, aviso legal, privacidad — desde `tec_content.json`), bandera de México para `es`, tipo de pago "Crédito Amazon", y crea la cuenta admin del segundo responsable (contraseña aleatoria de un solo uso, impresa solo en el log de ejecución). |

## Utilidades

| Script | Qué hace |
|---|---|
| `test-mail.php` | Diagnóstico SMTP: envía un correo de prueba con el mismo PHPMailer y las mismas variables `ORSEE_SMTP_*` que usa la aplicación. `php bin/test-mail.php destinatario@dominio`. |
| `reset-admin-pw.php` | Restablece la contraseña de un admin a un valor aleatorio de un solo uso (impreso solo en el log) y fuerza el cambio en el siguiente login. `php bin/reset-admin-pw.php <adminname>`. |
| `self-host.md`, `ubuntu-install.md` | Guías para autoalojamiento (Docker/VPS/Ubuntu). |

## Ejecución en Heroku

```bash
heroku run "php bin/<script>.php" -a orsee-beerlab
```

En Docker/VPS: `docker compose exec web php bin/<script>.php` (o PHP del sistema).
