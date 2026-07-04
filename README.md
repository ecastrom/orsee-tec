# orsee-tec

Despliegue de **ORSEE 3.4.0** — el sistema de reclutamiento y gestión del banco de
participantes — para el **BEER Lab** (*Behavioral & Experimental Economics Research
Lab*) del Departamento de Economía, Tecnológico de Monterrey, Campus Monterrey.

ORSEE gestiona el **banco de sujetos** y la **agenda de sesiones** del laboratorio:
registro de participantes, convocatorias por correo, calendario de experimentos,
reputación/no-shows y pagos. Los experimentos en sí corren en **oTree** (ver
[`DEPLOYMENT.md`](DEPLOYMENT.md) §7).

## Puesta en marcha rápida

- **Producción (Heroku):** sigue el [Camino A de `DEPLOYMENT.md`](DEPLOYMENT.md#3-camino-a--despliegue-en-heroku).
- **Prueba local gratuita (Docker):**

  ```bash
  cp .env.example .env      # edita las contraseñas
  docker compose up --build
  # abre http://localhost:8080/admin  (usuario: orsee_install · contraseña: install)
  ```

  > Cambia la contraseña por defecto de inmediato — ver `DEPLOYMENT.md` §5.

## Estructura del repositorio

| Ruta | Qué es |
|---|---|
| [`orsee/`](orsee/) | Código de ORSEE 3.4.0 (raíz web). `config/settings.php` lee toda la configuración del entorno; no contiene secretos. Versión en [`orsee/.orsee-version`](orsee/.orsee-version). |
| [`bin/`](bin/) | Scripts de arranque de base de datos (`release.php`, `import-db.php`, `db_bootstrap.php`) y de cron (`cron.sh`). |
| [`heroku/`](heroku/), `Procfile`, `app.json`, `composer.json` | Configuración de despliegue en Heroku. |
| [`docker/`](docker/), `docker-compose.yml`, `.env.example` | Stack Docker para local / VPS. |
| [`DEPLOYMENT.md`](DEPLOYMENT.md) | **Guía de despliegue completa** (Heroku y Docker), primer acceso, checklist de configuración del BEER Lab, integración con oTree, respaldos y troubleshooting. |
| [`Context/`](Context/) | Anteproyecto, canvas de negocio social y notas de reunión del BEER Lab. Ver [`Context/README.md`](Context/README.md). |

## Configuración: todo por variables de entorno

`orsee/config/settings.php` resuelve la conexión MySQL desde (en orden)
`JAWSDB_URL` → `CLEARDB_DATABASE_URL` → `DATABASE_URL` → variables discretas
`ORSEE_DB_*`, y la URL pública, zona horaria y SMTP desde otras variables `ORSEE_*`.
El catálogo completo está documentado al inicio de ese archivo y en `.env.example`.

## Licencia / cita

ORSEE es *citeware*. Cualquier publicación académica que use el sistema debe citar a
Greiner (2015); ver `DEPLOYMENT.md` §10 y `orsee/install/LICENSE`.
Documentación oficial: <https://www.orsee.org>.
