# Panel streaming VOD / IPTV (Laravel)

Plataforma web para gestionar **contenido propio o con licencia**, planes, revendedores y clientes finales. Incluye reproducción con **tokens temporales** (no se exponen las URLs reales al catálogo), importador **M3U/M3U8** y comando programado para **vencimientos**.

**No está pensada para listas ilegales ni contenido sin derechos.** Úsala solo con fuentes que puedas distribuir legalmente.

## Requisitos

- PHP 8.2+ (probado con PHP 8.3 en Laragon)
- Composer
- Node.js 20+ y npm
- MySQL 8+ (o SQLite para desarrollo rápido)

## Instalación paso a paso

1. **Clonar / copiar el proyecto** en tu carpeta web (por ejemplo `C:\laragon\www\reproductor_web`).

2. **Instalar dependencias PHP**

   ```bash
   composer install
   ```

3. **Configurar entorno**

   ```bash
   copy .env.example .env
   php artisan key:generate
   ```

   En `.env`:

   - Para **MySQL** (recomendado en producción):

     ```env
     DB_CONNECTION=mysql
     DB_HOST=127.0.0.1
     DB_PORT=3306
     DB_DATABASE=reproductor_web
     DB_USERNAME=root
     DB_PASSWORD=
     ```

   - Para **SQLite** (desarrollo): deja `DB_CONNECTION=sqlite` y asegúrate de que exista `database/database.sqlite` (Laravel la crea al migrar en muchos entornos).

4. **Variables opcionales (rclone / medios)**

   ```env
   RCLONE_BASE_URL=https://media.tudominio.com
   RCLONE_AUTH_USER=
   RCLONE_AUTH_PASS=
   PLAYBACK_TOKEN_TTL_MINUTES=5
   ```

   Si defines `RCLONE_BASE_URL`, las URLs de contenido deberán comenzar por ese prefijo (validación en backend).

5. **Migrar y sembrar datos de prueba**

   ```bash
   php artisan migrate --seed
   ```

6. **Frontend (Vite + Tailwind)**

   ```bash
   npm install
   npm run build
   ```

   En desarrollo:

   ```bash
   npm run dev
   ```

7. **Programador de tareas (vencimientos cada hora)**

   En el servidor, añade una entrada **cron**:

   ```cron
   * * * * * cd /ruta/al/proyecto && php artisan schedule:run >> /dev/null 2>&1
   ```

   El proyecto registra `users:expire` cada hora en `bootstrap/app.php`.

8. **Servir la aplicación**

   Con Laragon / Apache apunta el virtual host a la carpeta `public`. O en local:

   ```bash
   php artisan serve
   ```

## Usuarios de demostración (tras `migrate --seed`)

| Rol        | Email               | Contraseña |
|-----------|---------------------|------------|
| Admin     | admin@example.com   | password   |
| Revendedor | reseller@example.com | password   |
| Cliente activo | customer@example.com | password   |
| Cliente vencido | expired@example.com | password   |

El registro público está **desactivado**; los usuarios los crean admin o revendedor.

## Estructura de rutas

- `/admin/*` — Administrador (planes, revendedores, clientes, categorías, contenido, import M3U).
- `/reseller/*` — Revendedor (sus clientes y créditos).
- `/app/*` — Cliente (catálogo y reproducción si el plan está activo).
- `/player/*` — Reproductor con token.
- `/play/{content}/{token}` — Redirección validada al `stream_url` real (sin exponerlo en el listado).

## Comandos Artisan

```bash
php artisan users:expire
```

Marca clientes con `expires_at` pasado como `expired` y suscripciones activas como `expired`.

## Licencia y uso

Este código es una base técnica. Eres responsable del cumplimiento legal del contenido y de las licencias en tu jurisdicción.
