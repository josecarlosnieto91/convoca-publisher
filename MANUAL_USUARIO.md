# MANUAL_USUARIO.md — Convoca Publisher v1.3.0

> Guía para administradores: publicar entradas automáticamente en redes sociales.

## 1. Introducción

Convoca Publisher publica automáticamente tus entradas de WordPress en 7 redes sociales. Sin suscripciones, sin dependencias externas, sin límites artificiales. Los tokens se almacenan cifrados con AES-256-GCM.

**Integración con biodevas.org / lugg.biodevas.org:** Perfecto para publicar actividades, talleres y noticias en redes sociales sin esfuerzo adicional.

## 2. Configurar canales

Ve a **Convoca Publisher → Ajustes** y configura cada canal:

| Canal | ¿Qué necesitas? |
|-------|----------------|
| **Facebook / Instagram** | Token de página de Facebook (Graph API) |
| **LinkedIn** | Token OAuth 2.0 |
| **Twitter / X** | API Key + Secret + Access Token |
| **TikTok** | Access Token (Content Posting API) |
| **Google My Business** | Token OAuth 2.0 |
| **Telegram** | Bot Token + Chat ID |
| **Mastodon** | Access Token + Instance URL |

Cada canal tiene su propia sección con instrucciones para obtener las credenciales.

### Seguridad

Los tokens se almacenan cifrados con **AES-256-GCM**. La clave de cifrado se deriva de `AUTH_KEY` + `SECURE_AUTH_KEY` de WordPress. Si cambias estas constantes, tendrás que reconfigurar los canales.

## 3. Plantillas por canal

Cada canal puede tener un mensaje diferente. Usa estas variables:

| Variable | Se sustituye por |
|----------|-----------------|
| `{title}` | Título de la entrada |
| `{excerpt}` | Extracto |
| `{url}` | Enlace permanente |
| `{hashtags}` | Las 5 primeras etiquetas como hashtags |
| `{date}` | Fecha de publicación |
| `{author}` | Nombre del autor |
| `{featured_image}` | URL de la imagen destacada |

### Ejemplo de plantilla

```
📢 {title}

{excerpt}

🔗 {url}
{hashtags}
```

## 4. Publicar una entrada

### Publicación automática

Al publicar una entrada desde WordPress, Convoca Publisher:

1. Detecta la publicación
2. Prepara el mensaje con la plantilla de cada canal activo
3. Publica en cada red social
4. Muestra el resultado en el metabox del editor

### Metabox del editor

En el editor de entradas, el metabox **Convoca Publisher** muestra:

- **Canales activos**: qué redes están configuradas
- **Estado**: publicado, pendiente, error
- **Republicar**: botón para reintentar un envío fallido
- **Resultados**: enlace al post en cada red social

### Programar publicación

Si programas una entrada para el futuro, Convoca Publisher espera a que se publique para enviarla a redes sociales.

## 5. Cola de reintentos

Si una publicación falla (error de API, rate limit), el sistema:

1. Guarda el intento en la cola de reintentos
2. Reintenta con backoff exponencial: 1min, 5min, 15min, 1h, 6h
3. Máximo 5 reintentos
4. Si todos fallan, se marca como error

## 6. Historial

**Convoca Publisher → Historial** muestra las últimas 200 publicaciones con:

- Fecha y hora
- Canal
- Estado (éxito o error)
- Enlace al post en la red social
- Mensaje de error (si falló)

## 7. REST API

Endpoints disponibles (requieren `manage_options`):

```bash
# Estado de todos los canales
GET /wp-json/convoca-publisher/v1/status

# Publicar entrada específica
POST /wp-json/convoca-publisher/v1/publish/{post_id}

# Simular sin enviar (dry run)
POST /wp-json/convoca-publisher/v1/publish/{post_id}?dry_run=true

# Probar un canal específico
POST /wp-json/convoca-publisher/v1/test/{channel}
Body: { "post_id": 123 }
```

## 8. Problemas comunes

| Problema | Solución |
|----------|----------|
| **Token caducado** | Reconfigura el canal en Ajustes. Los tokens de Facebook/Twitter caducan |
| **Error 401/403** | El token no tiene permisos suficientes. Revisa los scopes |
| **No publica en Instagram** | Instagram requiere una página de Facebook vinculada. Publica a través de Facebook |
| **TikTok no funciona** | Requiere Content Posting API (no disponible en todas las cuentas) |
| **Rate limit** | Espera. El sistema reintenta automáticamente |
