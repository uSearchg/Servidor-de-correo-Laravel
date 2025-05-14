# SERVIDOR CORREO

Este proyecto es una API desarrollada en Laravel para gestionar el envío de correos electrónicos. Permite almacenar solicitudes de correo en una base de datos y enviarlas automáticamente usando un comando Artisan. Incluye documentación de la API con Swagger.

## Características

- **Endpoint API**: `POST /api/enviar-correo` para almacenar solicitudes de correo.
- **Base de Datos**: Almacena correos en la tabla `solicitudes_correo`.
- **Envío Automático**: Usa el comando `php artisan correos:enviar-pendientes` para enviar correos pendientes cada 5 minutos con `withoutOverlapping`.
- **Límite de Envíos por Hora**: Configurable mediante la variable `MAX_CORREOS_POR_HORA` en el `.env`.
- **Control de Errores**: Manejo robusto de errores con mensajes claros y rollback en caso de fallos.
- **Documentación**: Interfaz Swagger disponible en `/api/documentation`.

## Requisitos

- PHP >= 8.1
- Composer
- MySQL o cualquier base de datos compatible con Laravel
- Servidor SMTP (por ejemplo, Gmail con una contraseña de aplicación)
- Git

## Instalación

Sigue estos pasos para configurar el proyecto localmente:

### 1. Clona el repositorio


### 2. Instala las dependencias de Composer

```bash
composer install
```

### 3. Configura el archivo .env

```bash
cp .env.example .env
```

Configura tu base de datos, token de API y límite de envíos por hora:

```env
DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=bd
DB_USERNAME=tu_usuario
DB_PASSWORD=tu_contraseña

API_TOKEN=prueba

MAX_CORREOS_POR_HORA=100
```

> **Nota**: El `MAX_CORREOS_POR_HORA` define el número máximo de correos que se pueden enviar por hora mediante el cronjob. Ajusta este valor según tus necesidades.

### 4. Genera la clave de la aplicación

```bash
php artisan key:generate
```

### 5. Ejecuta las migraciones

```bash
php artisan migrate
```

### 6. Poblar la tabla `cuentas_correo`

La tabla `cuentas_correo` almacena las credenciales de las cuentas de correo. Usa el seeder para insertar un registro de prueba:

```bash
php artisan db:seed --class=CuentaCorreoSeeder
```

Asegúrate de que el seeder (`database/seeders/CuentaCorreoSeeder.php`) tenga credenciales válidas para tu servidor SMTP, por ejemplo:

```php
CuentaCorreo::create([
    'alias' => 'cuenta1',
    'email' => 'ejemplo@gmail.com',
    'password' => 'tu_contraseña_de_aplicación',
    'host' => 'smtp.gmail.com',
    'port' => 587,
    'encryption' => 'tls',
]);
```

> **Nota**: Si usas Gmail, genera una contraseña de aplicación:
>
> 1. Habilita la verificación en dos pasos en tu cuenta de Google.
> 2. Ve a "Seguridad" > "Contraseñas de aplicaciones" y genera una contraseña para "Mail".
> 3. Usa esa contraseña en el seeder.

### 7. Genera la documentación Swagger

```bash
php artisan l5-swagger:generate
```

### 8. Inicia el servidor

```bash
php artisan serve
```

## Uso

### Enviar un correo mediante el endpoint `POST /api/enviar-correo`

Este endpoint permite registrar una solicitud de correo en la tabla `solicitudes_correo`. El correo puede ser enviado inmediatamente o procesado más tarde por el cronjob.

Ejemplo de solicitud con PowerShell:

```powershell
Invoke-RestMethod -Uri "http://localhost:8000/api/enviar-correo" -Method Post -Headers @{ "Authorization" = "Bearer prueba" } -ContentType "application/json" -Body '{"asunto":"Prueba de Correo","cuerpo":"<p>Este es un correo de prueba</p>","remitente":"ejemplo@gmail.com","destinatario":"ejemplo@gmail.com","alias":"cuenta1","cc":null,"cco":null,"adjunto":null}'
```

#### Respuesta Exitosa (Código 201):

```json
{
    "mensaje": "Correo enviado exitosamente",
    "id": 1
}
```

#### Respuestas de Error:

**401 Unauthorized (Token inválido):**

```json
{
    "error": "No autorizado"
}
```

**404 Not Found (Alias no encontrado):**

```json
{
    "error": "Cuenta de correo no encontrada"
}
```

**400 Bad Request (Archivo adjunto no encontrado):**

```json
{
    "error": "El archivo adjunto no existe o no es accesible."
}
```

**503 Service Unavailable (Problema con el servidor SMTP):**

```json
{
    "error": "No se pudo enviar el correo debido a un problema con el servidor de correo."
}
```

**500 Internal Server Error (Error general):**

```json
{
    "error": "Ocurrió un error inesperado al procesar la solicitud."
}
```

### Probar con Swagger:

1. Visita `http://localhost:8000/api/documentation`.
2. Haz clic en "Authorize" e ingresa el token (por ejemplo, `prueba`).
3. Busca el endpoint `POST /api/enviar-correo` y usa el cuerpo de ejemplo.
4. Haz clic en "Execute" y verifica la respuesta.

### Enviar correos pendientes con el cronjob

El comando `correos:enviar-pendientes` procesa los correos pendientes en la tabla `solicitudes_correo` cada 5 minutos.

#### Probar manualmente:

```bash
php artisan correos:enviar-pendientes
```

#### Insertar un correo pendiente para probar:

```sql
INSERT INTO solicitudes_correo (asunto, cuerpo, remitente, destinatario, fecha_hora_recepcion, alias, cc, cco, adjunto, enviado)
VALUES ('Prueba Cronjob', '<p>Este es un correo de prueba</p>', 'ejemplo@gmail.com', 'ejemplo@gmail.com', NOW(), 'cuenta1', NULL, NULL, NULL, 0);
```

Ejecuta el comando y verifica que el correo se envíe y que el registro se actualice (`enviado = 1`).

## Ver la documentación

Visita `http://localhost:8000/api/documentation`.