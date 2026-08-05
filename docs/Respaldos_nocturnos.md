# Respaldos nocturnos de la base de datos (GitHub Actions)

El workflow [`.github/workflows/db-backup.yml`](../.github/workflows/db-backup.yml)
vuelca **toda la base de datos de producción** (JawsDB, la instancia de Heroku)
cada noche a las 02:23 (hora de Monterrey), verifica que el volcado esté completo,
lo **cifra con AES-256** y lo guarda como artefacto del repositorio con **90 días
de retención**. Peor caso ante un desastre: se pierden las últimas 24 horas.

Los artefactos solo son visibles para colaboradores del repositorio y además van
cifrados, así que los datos personales de participantes no quedan expuestos.

## Activación (una sola vez)

1. En GitHub: **Settings → Secrets and variables → Actions → New repository secret**, crear:

   | Secret | Valor |
   |---|---|
   | `JAWSDB_URL` | Salida de `heroku config:get JAWSDB_URL -a orsee-beerlab` |
   | `BACKUP_PASSPHRASE` | Una frase generada con `openssl rand -base64 24` |

2. **Guardar la passphrase en el gestor de contraseñas del laboratorio.**
   Sin ella los respaldos son indescifrables (ese es el punto).

3. Probar: pestaña **Actions → db-backup → Run workflow**. En ~1 minuto debe
   aparecer verde, con un artefacto `orsee-backup-<fecha>`.

## Restaurar un respaldo

1. Descargar el artefacto desde **Actions → (la corrida) → Artifacts** y descomprimir
   el `.zip`; dentro viene `orsee-backup-<fecha>.sql.gz.gpg`.
2. Descifrar y descomprimir:

   ```bash
   gpg --decrypt --passphrase '<BACKUP_PASSPHRASE>' --batch \
       orsee-backup-<fecha>.sql.gz.gpg > respaldo.sql.gz
   gunzip respaldo.sql.gz
   ```

3. Importar sobre la base destino (JawsDB u otra MySQL):

   ```bash
   # obtener credenciales: heroku config:get JAWSDB_URL -a orsee-beerlab
   mysql -h <host> -P <puerto> -u <usuario> -p<contraseña> <base> < respaldo.sql
   ```

   > El volcado incluye `DROP TABLE IF EXISTS`, de modo que restaurar **reemplaza**
   > el contenido actual de la base. Para migrar a otro servidor (p. ej. el de la
   > DTI) es exactamente el mismo procedimiento apuntando al nuevo host.

## Detalles operativos

- **Horario:** cron `23 8 * * *` (UTC) = 02:23 America/Monterrey.
- **Ejecución manual:** Actions → db-backup → *Run workflow* (útil antes de
  cualquier cambio riesgoso en producción).
- **Fallos:** GitHub envía correo automáticamente si una corrida falla. El
  workflow se niega a subir volcados incompletos (verifica número de tablas y la
  marca `Dump completed`).
- **Inactividad:** GitHub pausa los crons si el repositorio pasa 60 días sin
  actividad (avisa por correo; reactivar es un clic en Actions).
- **Alcance:** respalda la base de datos, que es donde vive prácticamente todo el
  estado de ORSEE. Los archivos subidos al módulo de descargas de ORSEE (si se
  usara) viven en el dyno de Heroku y son efímeros por diseño; no dependas de ellos.
