# Guía de despliegue — ORSEE para el BEER Lab (Tec de Monterrey)

Esta guía explica cómo poner en línea **ORSEE 3.4.0** (el banco de participantes y
sistema de reclutamiento del laboratorio) usando este repositorio. Hay dos caminos:

- **Camino A — Heroku** (recomendado para el lanzamiento, ya que el equipo ya
  opera Heroku para las apps de oTree).
- **Camino B — Docker / VPS** (para pruebas locales gratuitas y como alternativa
  autoalojada más económica a largo plazo).

Ambos usan el **mismo código**. La configuración se toma de **variables de
entorno**, por lo que no hay contraseñas escritas en el repositorio.

---

## 1. ¿Qué es cada pieza?

| Pieza | Función |
|---|---|
| **ORSEE** (`orsee/`) | App web en PHP + MySQL. Registra participantes, arma el calendario de sesiones, envía convocatorias por correo, gestiona pagos y reputación. Es el "banco de sujetos" del laboratorio. |
| **oTree** (fuera de este repo) | Corre los experimentos en sí (trust game, double auction, etc.). ORSEE **recluta y agenda**; oTree **ejecuta** la sesión. Ver §7. |
| **Base de datos MySQL** | Donde ORSEE guarda todo. En Heroku es un add-on (JawsDB); en Docker es un contenedor `mysql:8.0`. |
| **Cron** | Tarea periódica (cada 5–10 min) que vacía la cola de correos y dispara recordatorios. Sin esto, ORSEE no envía correos automáticos. |

**Arquitectura de este repositorio**

```
orsee-tec/
├── orsee/                  ← ORSEE 3.4.0 (raíz web); config/settings.php lee del entorno
├── bin/                    ← scripts de arranque de BD y de cron
│   ├── db_bootstrap.php    ← resuelve la conexión MySQL desde el entorno
│   ├── release.php         ← fase release de Heroku: importa el esquema la 1ª vez
│   ├── import-db.php        ← importación manual/idempotente del esquema
│   └── cron.sh             ← ejecuta admin/cron.php (cola de correo, recordatorios)
├── heroku/apache2.conf     ← endurece install/, config/, tagsets/ en Heroku
├── Procfile                ← procesos web + release de Heroku
├── app.json                ← add-ons y config vars para "one-click deploy"
├── composer.json/.lock     ← versión de PHP y extensiones (buildpack de Heroku)
├── docker/                 ← Dockerfile + vhost de Apache (Camino B)
├── docker-compose.yml      ← stack web + db + cron (Camino B)
├── .env.example            ← plantilla de variables para Docker
└── Context/                ← anteproyecto, canvas y notas del BEER Lab
```

---

## 2. Requisitos previos

- Cuenta de **Heroku** (Camino A) con `heroku` CLI instalado y `heroku login` hecho.
  > Ojo: Heroku ya no tiene plan gratuito. Presupuesta ~USD 5–7/mes por el dyno
  > *Basic* + el costo del add-on MySQL (JawsDB *Kitefin* tiene un plan de arranque
  > económico/gratuito según disponibilidad).
- O bien **Docker Desktop / Docker Engine** (Camino B).
- Git y acceso a este repositorio (`ecastrom/orsee-tec`).

---

## 3. Camino A — Despliegue en Heroku

### 3.1. Crear la app y la base de datos

```bash
# desde la raíz del repo
heroku create orsee-beerlab            # elige el nombre que quieras
heroku stack:set heroku-24 -a orsee-beerlab
heroku buildpacks:set heroku/php -a orsee-beerlab

# Base de datos MySQL (add-on JawsDB). Provee la variable JAWSDB_URL automáticamente.
heroku addons:create jawsdb:kitefin -a orsee-beerlab
```

### 3.2. Configurar las variables de entorno (config vars)

```bash
heroku config:set -a orsee-beerlab \
  ORSEE_SERVER_PROTOCOL="https://" \
  ORSEE_SERVER_URL="orsee-beerlab.herokuapp.com" \
  ORSEE_ROOT_DIRECTORY="" \
  ORSEE_TIMEZONE="America/Monterrey" \
  ORSEE_MAIL_TRANSPORT="phpmailer"
```

> `ORSEE_SERVER_URL` es tu dominio **sin** `https://` y **sin** diagonal final.
> Si usarás un dominio propio (p. ej. `lab.tec.mx`), ponlo aquí y configúralo con
> `heroku domains:add`.

**Correo saliente (SMTP).** Los dynos de Heroku no tienen servidor de correo, así
que hay que usar un relay SMTP. Con SendGrid:

```bash
heroku addons:create sendgrid:starter -a orsee-beerlab   # o usa tu relay del Tec
heroku config:set -a orsee-beerlab \
  ORSEE_SMTP_HOST="smtp.sendgrid.net" \
  ORSEE_SMTP_PORT="587" \
  ORSEE_SMTP_SECURE="tls" \
  ORSEE_SMTP_USER="apikey" \
  ORSEE_SMTP_PASS="<TU_API_KEY_DE_SENDGRID>"
```

### 3.3. Desplegar

```bash
git push heroku main
```

En el primer despliegue, la **fase release** (`bin/release.php`) detecta que la base
está vacía e **importa el esquema** `orsee/install/install.sql` automáticamente. En
despliegues posteriores no toca los datos (es idempotente).

Si prefieres importar a mano en lugar de por la fase release:

```bash
heroku run "php bin/import-db.php" -a orsee-beerlab
```

### 3.4. Programar el cron (Heroku Scheduler)

```bash
heroku addons:create scheduler:standard -a orsee-beerlab
heroku addons:open scheduler -a orsee-beerlab
```

En la interfaz del Scheduler, crea un job:
- **Command:** `bash bin/cron.sh`
- **Frequency:** cada 10 minutos (el intervalo mínimo de Heroku Scheduler).

### 3.5. Primer acceso

```bash
heroku open -a orsee-beerlab      # abre la parte pública
```

Entra al panel en `https://<tu-app>.herokuapp.com/admin` con:

- **Usuario:** `orsee_install`
- **Contraseña:** `install`

➡️ Continúa en la **§5 (primer acceso y seguridad)**.

### 3.6. Nota sobre el sistema de archivos efímero

Heroku borra los archivos escritos en disco cada vez que reinicia el dyno. Para
ORSEE esto solo afecta a: (a) las estadísticas de webalizer en `usage/` (opcionales)
y (b) los archivos subidos en el módulo de descargas. **Casi todos los datos de
ORSEE viven en MySQL**, así que para un laboratorio docente el impacto es menor. Si
más adelante necesitas subidas persistentes, migra ese módulo a almacenamiento S3 o
usa el Camino B.

---

## 4. Camino B — Docker (local o VPS)

Ideal para **probar gratis** antes de gastar en Heroku, o para autoalojar en un
servidor del Tec / VPS barato con almacenamiento persistente.

```bash
cp .env.example .env        # edita las contraseñas dentro de .env
docker compose up --build   # construye e inicia web + db + cron
```

Luego abre <http://localhost:8080/admin> (usuario `orsee_install`, contraseña
`install`). El esquema se importa solo en el primer arranque; los datos persisten en
el volumen `orsee_db`.

Para un VPS de producción: pon un proxy inverso con HTTPS (Caddy/Nginx + Let's
Encrypt) delante del puerto 80 del contenedor `web`, y ajusta `ORSEE_SERVER_URL` /
`ORSEE_SERVER_PROTOCOL=https://` en `.env`.

Detener / respaldar:

```bash
docker compose down                                  # detiene (conserva datos)
docker compose exec db mysqldump -uorsee -p orsee > backup_$(date +%F).sql
```

---

## 5. Primer acceso y endurecimiento de seguridad (¡obligatorio!)

Al primer login, **inmediatamente**:

1. **Cambia la contraseña** de `orsee_install` (Options → Edit administrators, o el
   propio menú de contraseña).
2. **Cambia el correo** de ese usuario (por defecto es `installer@orsee.org`).
3. En **Options → General Settings**, fija la *"System support email address"* a un
   correo real del laboratorio (si no, los correos del sistema parecen venir de
   orsee.org o no se envían).
4. Verifica que **Options → Regular Tasks** (los cronjobs) estén habilitados.
5. Crea administradores reales (Edgar Castro, Stanislao Maldonado, Lab Manager) y
   luego considera deshabilitar `orsee_install`.

---

## 6. Lista de configuración inicial para el BEER Lab

Una vez dentro, configura lo mínimo para operar (sección Options y menús de admin):

- **Laboratorios (Laboratories):** crea el *Lab Fijo — Aulas VI* (30 estaciones) y el
  *Lab Móvil*. El nº de asientos alimenta la capacidad de cada sesión.
- **Subject pools (Subpools):** p. ej. *Estudiantes de Economía*, *Otros
  departamentos*, según los segmentos del anteproyecto.
- **Experiment Types / Experiment Classes:** categoriza el portafolio (Teoría de
  juegos, Economía conductual, Mercados/Finanzas, Bienes públicos…).
- **Profile / Participant fields:** matrícula, campus, carrera, semestre, UF inscrita.
  Esto permite reclutar por criterios (p. ej. solo alumnos de cierta UF).
- **Participant / Participation states:** revisa los estados por defecto.
- **Languages:** habilita **Español** como idioma público (ORSEE es multilingüe).
- **Public menu, páginas, FAQs y plantillas de correo:** redacta la convocatoria, el
  consentimiento informado y los recordatorios en español.
- **Estilo:** duplica `orsee/style/orsee` a un estilo propio del BEER Lab y ajusta
  colores/encabezado (Options → Colors).

> El detalle pedagógico (qué experimento por UF, competencias Tec21) está en
> `Context/01_Ante_Proyecto/` y en `Context/README.md`.

---

## 7. Cómo encaja con oTree

ORSEE y oTree son complementarios:

1. **ORSEE** mantiene el banco de participantes y **publica una sesión** en su
   calendario (fecha, laboratorio, cupo, pago).
2. Los estudiantes **se registran** a esa sesión desde el sitio público de ORSEE;
   ORSEE envía confirmaciones y recordatorios por correo.
3. El día de la sesión, los participantes abren la **app de oTree** (tu despliegue de
   oTree, típicamente en Heroku) que corre el experimento.
4. Tras la sesión, el Lab Manager marca asistencia y **pagos** en ORSEE; la
   reputación/no-shows quedan registrados para futuros reclutamientos.

No hay integración técnica obligatoria entre ambos: el enlace a la sala de oTree se
comparte en las instrucciones/estación de la sesión. (Opcionalmente, se puede poner
la URL de oTree en la descripción de la sesión de ORSEE.)

---

## 8. Actualizar ORSEE en el futuro

El código de ORSEE está *vendido* (vendored) en `orsee/` desde la versión 3.4.0
(ver `orsee/.orsee-version`). Para actualizar a una versión futura: reemplaza el
contenido de `orsee/` con la nueva release **conservando** nuestro
`orsee/config/settings.php` y `orsee/.gitignore`, revisa `install/UPGRADE.howto`, y
ejecuta las actualizaciones de BD que ORSEE indique desde el panel. Prueba primero en
el Camino B (Docker) antes de tocar producción.

---

## 9. Solución de problemas

| Síntoma | Causa probable / arreglo |
|---|---|
| `Connection failed` en pantalla | Falta el add-on MySQL o las vars de BD. En Heroku: `heroku config` y confirma `JAWSDB_URL`. |
| La página carga pero los enlaces usan `http://` o el host equivocado | Ajusta `ORSEE_SERVER_URL` y `ORSEE_SERVER_PROTOCOL`. |
| No llegan los correos | `ORSEE_MAIL_TRANSPORT=phpmailer` + credenciales SMTP correctas; y revisa que el cron esté corriendo. |
| Los recordatorios/colas no se procesan | El job del Scheduler (Heroku) o el servicio `cron` (Docker) no está activo. |
| Quiero re-importar el esquema desde cero | `php bin/import-db.php --force` (solo en una BD que quieras reinicializar). |
| Ver logs en Heroku | `heroku logs --tail -a orsee-beerlab` |

---

## 10. Créditos y licencia

ORSEE es *citeware*: cualquier reporte o publicación académica que use este sistema
debe citar:

> Ben Greiner (2015), *Subject Pool Recruitment Procedures: Organizing Experiments
> with ORSEE*, Journal of the Economic Science Association 1(1), 114–125.
> <http://link.springer.com/article/10.1007/s40881-015-0004-4>

Ver `orsee/install/LICENSE`. Documentación oficial: <https://www.orsee.org>.
