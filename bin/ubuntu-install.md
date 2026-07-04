# Instalar Ubuntu Server en la mini-PC (Lenovo ThinkCentre M910q)

Guía paso a paso para dejar la caja lista con **Ubuntu Server 24.04 LTS**, hasta el
punto de poder entrar por SSH y correr `bin/bootstrap.sh`. Es la única parte que
requiere estar físicamente frente a la máquina (~15–20 min, una sola vez).

> ⚠️ Esto **borra Windows 11** del mini-PC. Es lo que queremos (ver `bin/self-host.md`
> §"¿por qué Linux?"). No pierdes nada útil: no usaremos Windows.

---

## 0. Qué necesitas

- La mini-PC M910q + su cargador.
- Una **memoria USB de 8 GB o más** (se borra su contenido).
- **Otra computadora** (tu laptop) para descargar y "flashear" la USB.
- Un **monitor** (la M910q tiene HDMI y DisplayPort) y un **teclado USB**, solo
  durante la instalación.
- Un **cable de red Ethernet** conectado a tu router/switch (mismo LAN).

---

## 1. Descargar Ubuntu Server

En tu laptop, descarga la imagen ISO de **Ubuntu Server 24.04 LTS** desde:

- <https://ubuntu.com/download/server>

(Elige "Ubuntu Server", *no* "Ubuntu Desktop". Es un archivo `.iso` de ~2–3 GB.)

---

## 2. Crear la USB de instalación

Instala **balenaEtcher** (funciona en Windows/Mac/Linux, muy simple):
<https://etcher.balena.io>

1. Abre balenaEtcher.
2. *Flash from file* → selecciona la ISO de Ubuntu Server.
3. *Select target* → elige tu memoria USB (¡verifica que sea la correcta!).
4. *Flash!* y espera a que termine.

> Alternativa en Windows: **Rufus** (<https://rufus.ie>), modo "DD"/imagen.

---

## 3. Arrancar la mini-PC desde la USB

1. Con la M910q **apagada**, conéctale monitor, teclado, cable Ethernet y la USB.
2. Enciéndela y, apenas aparezca el logo **Lenovo**, presiona **F12** repetidamente
   para abrir el *menú de arranque* (Boot Menu).
   - Si F12 no funciona, entra al BIOS con **F1**, y en *Startup* activa
     "USB Boot"; si hay problemas para arrancar, desactiva **Secure Boot**
     (Ubuntu suele arrancar con Secure Boot activo, pero desactivarlo evita
     sorpresas).
3. En el menú, elige la memoria **USB** (aparece como "USB HDD" o el nombre de la
   marca de tu memoria).

---

## 4. Instalador de Ubuntu Server (Subiquity)

Se navega con **flechas, Tab y Enter** (no necesita mouse).

1. **Language:** English (recomendado para que los mensajes coincidan con las guías)
   o Español.
2. **Keyboard:** elige *Spanish (Latin American)* si tu teclado es en español.
3. **Type of install:** *Ubuntu Server* (la opción normal, no "minimized").
4. **Network:** debe detectar la conexión **Ethernet** y mostrar una **IP**
   (algo como `192.168.x.x`). **Anota esa IP** — la usarás para el SSH.
   - Si no aparece IP, revisa el cable; deja que tome DHCP.
5. **Proxy / Mirror:** deja en blanco / valores por defecto → *Done*.
6. **Storage (¡ojo aquí!):** elige *Use an entire disk* sobre el **SSD de 256 GB**.
   Esto **borra Windows**. Acepta el resumen y confirma el aviso de *destructive
   action* (*Continue*).
7. **Profile setup:**
   - *Your name:* p. ej. `BEER Lab`
   - *Your server's name (hostname):* `beerlab`
   - *Username:* `beerlab`
   - *Password:* una contraseña fuerte (¡anótala!).
8. **Upgrade to Ubuntu Pro:** *Skip for now*.
9. **SSH Setup:** ✅ **marca "Install OpenSSH server"** — esto es lo que te permite
   entrar desde tu laptop. (Puedes dejar vacío lo de importar llaves.)
10. **Featured server snaps:** no selecciones nada → *Done*.
11. Empieza la instalación. Cuando diga ***Reboot Now***, acéptalo y **quita la USB**
    cuando la pantalla lo pida (si se atora en un mensaje, quita la USB y pulsa Enter).

---

## 5. Primer arranque y acceso por SSH

Tras reiniciar, la caja arranca en una pantalla negra con un texto de login. **Ya no
necesitas el monitor ni el teclado**: a partir de aquí trabajas desde tu laptop.

Desde tu laptop (Terminal en Mac/Linux, o PowerShell/Windows Terminal en Windows):

```bash
ssh beerlab@<IP-que-anotaste>
```

- Acepta la huella (*fingerprint*) la primera vez escribiendo `yes`.
- Escribe la contraseña que pusiste en el paso 7.

Si entras y ves un *prompt* como `beerlab@beerlab:~$`, ¡listo! Ya puedes desconectar
monitor y teclado y dejar la caja en su lugar (en el UPS, con el cable de red).

> Consejo: reserva la IP de la caja en tu router (DHCP reservation) para que sea
> siempre la misma. Así el SSH y el acceso no cambian.

---

## 6. Siguiente paso

Ya en la sesión SSH, continúa con el arranque en un comando:

```bash
sudo mkdir -p /opt && sudo chown $USER /opt
cd /opt
git clone <URL-del-repo> orsee-tec
cd orsee-tec
bash bin/bootstrap.sh
```

Luego sigue `bin/self-host.md` desde §4 (configuración, dominio, Cloudflare Tunnel,
respaldos). Cuando llegues aquí, avísame con la IP y lo hacemos juntos paso a paso.

---

## Solución de problemas

| Síntoma | Qué hacer |
|---|---|
| La caja no arranca desde la USB | Entra al BIOS (**F1**), activa *USB Boot* y sube la USB en el orden de arranque; prueba desactivar *Secure Boot*. Reflashea la USB si sigue sin verse. |
| No aparece IP en el paso *Network* | Revisa el cable Ethernet y el puerto del router; el instalador toma IP por DHCP automáticamente. |
| `ssh` dice *Connection refused* | No marcaste *Install OpenSSH server* (paso 9). Conecta monitor/teclado y ejecuta `sudo apt install -y openssh-server`. |
| No sé la IP de la caja | Conéctale monitor y escribe `ip a` tras iniciar sesión, o revisa la lista de dispositivos DHCP en tu router. |
| Olvidé la contraseña del usuario | Reinstala (son 15 min) o usa recuperación de GRUB. Más fácil: reinstalar. |
