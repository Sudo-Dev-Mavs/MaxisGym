# MaxisGym

Plataforma web de gestión administrativa para gimnasios.
Proyecto final de Programación Web (Laboratorio, grupo 238, FIME – UANL).

## Tecnologías

| Capa | Tecnología |
|---|---|
| Front-end | HTML5, CSS3, JavaScript (fetch/AJAX), Bootstrap 5.3, Bootstrap Icons |
| Back-end | PHP 8 (sin framework) con PDO |
| Reportes PDF | FPDF 1.9 (`app/lib/fpdf`, un solo archivo PHP, sin Composer) |
| Base de datos | MySQL 8 |
| Servidor local | Laragon (Apache + MySQL + PHP) |
| Control de versiones | Git + GitHub |

Bootstrap, los íconos y las tipografías están dentro del proyecto (`assets/vendor`),
así que el sistema funciona sin conexión a internet.

## Instalación en Laragon

1. Clona el repositorio dentro de `C:\laragon\www\`:
   ```
   cd C:\laragon\www
   git clone <url-del-repositorio> maxisgym
   ```
2. Inicia Laragon y presiona **Start All** (Apache y MySQL).
3. Crea la base de datos. En la terminal de Laragon:
   ```
   cd C:\laragon\www\maxisgym
   mysql -u root < database\maxisgym.sql
   mysql -u root maxisgym < database\datos_demo.sql
   ```
   El segundo comando es opcional: carga socios, pagos y avisos de ejemplo.
4. Abre `http://localhost/maxisgym/diagnostico.php` y verifica que todo esté en **OK**.
5. Entra a `http://localhost/maxisgym/`.

Si tu MySQL tiene contraseña u otro usuario, copia `config/config.local.example.php`
como `config/config.local.php` y escribe ahí tus datos.

## Instalación en Linux (MySQL en Docker)

Requiere un contenedor de MySQL llamado `mysql` con la base `maxisgym` cargada
y un `config/config.local.php` con tu usuario y contraseña de MySQL.

**Opción A: PHP instalado en tu equipo**
```
cd MaxisGym
php -S localhost:8000
```
Abre `http://localhost:8000/`. (Este servidor no lee los `.htaccess`, sirve solo para desarrollo).

**Opción B: PHP + Apache en Docker** (igual que Laragon)
```
docker network create maxisgym-net          # una sola vez
docker network connect maxisgym-net mysql   # una sola vez
docker compose up -d                         # encender  → http://localhost:8080/
docker compose down                          # apagar
```
Tarea de vencimientos a mano: `docker compose exec -u www-data web php tareas/notificar_vencimientos.php`

### Si ya tenías la base de datos cargada

Cuando una versión nueva agrega algo al catálogo, viene un script en
`database/actualizaciones/` que se aplica sin borrar tus datos:
```
mysql -u root maxisgym < database\actualizaciones\001_permiso_mensajes.sql
```
(Una instalación nueva con `database/maxisgym.sql` ya lo incluye.)
`diagnostico.php` avisa si falta alguno.

## Diseño de la interfaz

- Estilos propios en `assets/css/app.css` (sobre Bootstrap 5.3), organizados por secciones:
  variables, botones, sitio público, páginas internas y paneles. Los colores de la marca están
  en `:root` (por ejemplo, `--seguridad` es el amarillo de las acciones).
- Piezas de interfaz reutilizables en `app/componentes.php`: `foto()` (imagen con versión para
  celular), `tarjeta_plan()`, `disco_progreso()` (anillo de la membresía) y `encabezado_pagina()`.
- `assets/js/sitio.js`: barra que se vuelve sólida al bajar, menú del celular, la frase que se
  enciende al hacer scroll y las fotos que se revelan al entrar en pantalla.
- `assets/js/panel.js`: en celular, cada fila de las tablas de los paneles se muestra como tarjeta.
- Con "reducir movimiento" activado en el sistema, las animaciones se quitan y todo se ve completo.

## Correos

En modo prueba (`'mail' => ['driver' => 'archivo']` en `config/config.php`) los correos
no se envían: cada uno se guarda como página HTML en `storage/correos/`.
Ábrelos con doble clic para ver, por ejemplo, el enlace para restablecer la contraseña.
Para enviarlos de verdad, configura el SMTP de Laragon y cambia el driver a `'mail'`.

## Tarea programada (avisos de vencimiento)

`tareas/notificar_vencimientos.php` debe ejecutarse una vez al día. Cada vez:

1. Marca como **vencidas** las membresías cuya fecha de fin ya pasó.
2. Registra el aviso de **7, 3 o 1 día** de cada membresía por vencer
   (recepción lo ve en su panel y lo marca como atendido).
3. Envía el **correo** al socio (en modo prueba queda en `storage/correos/`).

Se puede ejecutar varias veces el mismo día: no repite avisos ni correos.
Si un día no corre (computadora apagada), al día siguiente envía el aviso que
quedó pendiente. Cada ejecución se anota en `storage/logs/tareas.log`.

**Probarla a mano** (terminal de Laragon):
```
cd C:\laragon\www\maxisgym
php tareas\notificar_vencimientos.php
```

**Programarla en Windows** (todos los días a las 6:00). En una terminal `cmd`:
```
schtasks /create /tn "MaxisGym vencimientos" /sc daily /st 06:00 /tr "C:\laragon\bin\php\<carpeta-php>\php.exe C:\laragon\www\maxisgym\tareas\notificar_vencimientos.php"
```
Cambia `<carpeta-php>` por la que tengas dentro de `C:\laragon\bin\php\`.
También se puede crear desde *Programador de tareas → Crear tarea básica*.
MySQL debe estar encendido a esa hora (Laragon abierto).

**En Linux** (cron): `0 6 * * * php /ruta/a/maxisgym/tareas/notificar_vencimientos.php`

Si la tarea no ha corrido en las últimas 26 horas, el panel del administrador y
el de recepción muestran una advertencia.

## Usuarios de prueba

Contraseña de todos: `password`

| Rol | Correo |
|---|---|
| Administrador | admin@maxisgym.com |
| Empleado (Recepción) | laura@example.com |
| Socio | juan@example.com |

## Seguridad

- Contraseñas guardadas con `password_hash()` (bcrypt); nunca en texto plano.
- Todas las consultas usan sentencias preparadas de PDO (sin inyección SQL).
- Todo lo que se imprime pasa por `e()` (`htmlspecialchars`) para evitar XSS.
- Token CSRF en todos los formularios POST y en las peticiones AJAX.
- Control de acceso por **permisos** leídos de la base de datos
  (`roles` → `rol_permiso` → `permisos`). Si alguien entra a una página que no
  le corresponde, el servidor lo regresa a su propio panel con un aviso.
- El registro público siempre crea usuarios con rol *socio*.
- "Recordarme" usa un token aleatorio; en la base sólo se guarda su hash.

## Estructura de carpetas

```
maxisgym/
├── admin/          Panel del administrador (socios, empleados, planes, reportes)
├── empleado/       Panel de recepción (pagos, alta de socios, consultas, avisos)
├── socio/          Panel del socio (membresía, historial de pagos)
├── cuenta/         Páginas de cualquier usuario con sesión (perfil, avisos)
├── app/            Código compartido (conexión, seguridad, permisos, vistas)
│   └── lib/fpdf/   Librería para generar el reporte en PDF
├── assets/         CSS, JavaScript, imágenes y librerías locales
├── config/         Configuración (base de datos, zona horaria, correo)
├── database/       Scripts SQL (estructura y datos de demostración)
├── tareas/         Tareas programadas que se ejecutan desde la terminal
├── storage/        Correos de prueba y bitácoras (no se sube a GitHub)
└── *.php           Sitio público, login, registro y cierre de sesión
```
