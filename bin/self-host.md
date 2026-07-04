# Autoalojar ORSEE en una mini-PC dedicada (producción del BEER Lab)

Guía para poner ORSEE en línea desde una **caja dedicada siempre encendida**
(mini-PC de negocios reacondicionada), accesible por internet con HTTPS y dominio
propio, **sin abrir puertos del router ni IP fija**, gracias a un Cloudflare Tunnel.

> Resumen del costo: ~$190–290 USD una sola vez (mini-PC + UPS) y ~$30–45 USD/año
> (dominio + electricidad). Ver `DEPLOYMENT.md` para la comparación con Heroku/cPanel.

---

## 1. Hardware a comprar

- **Mini-PC de negocios reacondicionada** (24/7): Lenovo ThinkCentre *Tiny*
  (M710q/M720q/M910q), HP EliteDesk/ProDesk *Mini*, o Dell OptiPlex *Micro*.
  - Procesador **Intel i5 (8ª gen o superior)**, **16 GB RAM**, **SSD 256–512 GB**.
  - Prefiere x86 (no Raspberry Pi) porque el SSD real evita la corrupción típica de
    las tarjetas SD y todas las imágenes Docker corren sin sorpresas de arquitectura.
- **UPS (no-break)** ~600–700 VA: evita que un apagón corrompa MySQL.
- **Cable Ethernet** (usa red cableada, no Wi-Fi).

Coloca la caja en el laboratorio o una oficina cerrada, conectada al UPS y por cable.

---

## 2. Sistema operativo

1. Instala **Ubuntu Server LTS** (24.04) — sin entorno gráfico, headless.
2. Durante la instalación crea un usuario (p. ej. `beerlab`) y habilita **OpenSSH**.
3. Actualiza y habilita actualizaciones de seguridad automáticas:

   ```bash
   sudo apt update && sudo apt -y upgrade
   sudo apt -y install unattended-upgrades
   sudo dpkg-reconfigure -plow unattended-upgrades   # elige "Yes"
   ```

4. (Recomendado) IP local fija por reserva DHCP en tu router, para encontrar la caja
   siempre en la misma dirección dentro de la red.

---

## 3. Docker + arranque en un comando

Clona este repositorio (por ejemplo en `/opt`) y corre el script de arranque:

```bash
sudo mkdir -p /opt && sudo chown $USER /opt
cd /opt
git clone https://github.com/ecastrom/orsee-tec.git
cd orsee-tec
bash bin/bootstrap.sh
```

`bin/bootstrap.sh` es idempotente y hace todo lo tedioso: instala Docker y el
plugin de compose, habilita Docker al encender la caja, crea `.env` con
contraseñas de base de datos aleatorias y fuertes, construye e inicia el stack
(ORSEE + MySQL + cron) e instala el cron de respaldo nocturno. Al terminar
imprime la URL local y los siguientes pasos.

Después solo faltan los secretos que el script no puede adivinar: el dominio
público / token de Cloudflare Tunnel (§5–§6) y el SMTP para enviar correo — se
editan en `.env`. Los pasos manuales equivalentes están abajo por si prefieres
hacerlo a mano.

<details>
<summary>Instalación manual de Docker (equivalente, sin el script)</summary>

```bash
sudo apt -y install docker.io docker-compose-v2 git
sudo systemctl enable --now docker      # arranca solo al encender la caja
sudo usermod -aG docker $USER           # cierra sesión y vuelve a entrar
```
</details>

---

## 4. Configuración

```bash
cp .env.example .env
nano .env
```

Ajusta al menos:

- `MYSQL_PASSWORD`, `MYSQL_ROOT_PASSWORD` — contraseñas fuertes.
- `ORSEE_SERVER_URL` — tu dominio público **sin** protocolo (p. ej. `beerlab.example.org`).
- `ORSEE_SERVER_PROTOCOL=https://`
- Bloque SMTP (`ORSEE_MAIL_TRANSPORT=phpmailer` + credenciales) para enviar correo.
- `CLOUDFLARE_TUNNEL_TOKEN` — lo obtienes en el paso 6.

Levanta el stack (web + MySQL + cron). El esquema se importa solo la 1ª vez:

```bash
docker compose up -d --build
```

Prueba en la red local: `http://IP-DE-LA-CAJA:8080/admin`
(usuario `orsee_install`, contraseña `install` — cámbiala de inmediato, ver
`DEPLOYMENT.md` §5).

`restart: unless-stopped` + `systemctl enable docker` hacen que **todo vuelva a
arrancar solo** tras un corte de luz o reinicio.

---

## 5. Dominio

Compra un dominio (p. ej. `beerlab.org`, ~$12–15 USD/año) **a nombre de un profesor
o de una cuenta del laboratorio**, nunca de un becario (los becarios rotan; el
dominio y el hosting deben sobrevivir a los cambios de cohorte). Agrégalo a una
cuenta gratuita de Cloudflare.

---

## 6. Publicar con Cloudflare Tunnel (HTTPS, sin abrir puertos)

1. Crea una cuenta gratis en **Cloudflare** y agrega tu dominio (plan Free).
2. Entra a **Zero Trust → Networks → Tunnels → Create a tunnel** (tipo *Cloudflared*).
3. Nómbralo (p. ej. `beerlab`) y copia el **token** que te da.
4. Pégalo en `.env` como `CLOUDFLARE_TUNNEL_TOKEN=...`.
5. En **Public Hostnames** del túnel, enruta:
   - *Subdomain/Domain*: tu dominio (p. ej. `beerlab.example.org`)
   - *Service*: `HTTP` → `web:80`
6. Arranca el stack **con** el overlay del túnel:

   ```bash
   docker compose -f docker-compose.yml -f docker-compose.tunnel.yml up -d --build
   ```

Cloudflare sirve tu dominio por HTTPS (certificado automático), oculta la IP de tu
casa/lab y añade protección DDoS. `cloudflared` solo hace conexiones **salientes**,
así que no expones ningún puerto entrante.

---

## 7. Respaldos (¡obligatorio — es PII irremplazable!)

El script `bin/backup.sh` hace un `mysqldump` comprimido y rota los antiguos.
Pruébalo una vez a mano:

```bash
bash bin/backup.sh          # crea backups/orsee-orsee-AAAAMMDD-HHMMSS.sql.gz
```

Prográmalo cada noche en el crontab de tu usuario (`crontab -e`):

```cron
15 3 * * * cd /opt/orsee-tec && bash bin/backup.sh >> /var/log/orsee-backup.log 2>&1
```

**Copia fuera de la caja.** Configura `rclone` con un remoto (Google Drive,
Backblaze B2, Cloudflare R2) y pon `BACKUP_RCLONE_REMOTE=remoto:orsee-backups` en
`.env`; el script subirá cada respaldo automáticamente. Si el SSD muere, pierdes un
día, no el banco de participantes.

Restaurar:

```bash
gunzip -c backups/orsee-orsee-XXXX.sql.gz | docker compose exec -T db \
  mysql -uorsee -p"$MYSQL_PASSWORD" orsee
```

---

## 8. Mantenimiento

- `docker compose pull && docker compose up -d` para actualizar imágenes base.
- `docker compose logs -f web` para ver logs.
- Verifica de vez en cuando que el UPS y los respaldos funcionan (una restauración de
  prueba cada semestre).
- Para actualizar ORSEE a una versión nueva, ver `DEPLOYMENT.md` §8.

---

## 9. Continuidad (crítico para un lab con becarios rotativos)

- **Dominio, cuenta de Cloudflare y credenciales** a nombre de los profesores
  responsables o de una cuenta compartida del laboratorio.
- Documenta contraseñas en un gestor compartido del lab (no en la cabeza de un becario).
- La caja física vive en el laboratorio, etiquetada, en el UPS. Los becarios operan
  ORSEE desde el panel de administración, no administran el servidor.
