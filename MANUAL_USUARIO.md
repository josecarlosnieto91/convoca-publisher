# MANUAL_USUARIO.md — Convoca Publisher v1.4.0

> Guía para administradores: publicar entradas automáticamente en redes sociales.

## 1. Introducción

Convoca Publisher publica automáticamente tus entradas de WordPress en **7 redes sociales**. Sin suscripciones, sin dependencias externas, sin límites artificiales. Los tokens se almacenan cifrados con AES-256-GCM.

**Redes soportadas:** Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon.

**Integración en cualquier sitio:** Perfecto para publicar actividades, talleres y noticias en redes sociales sin esfuerzo adicional.

## 2. Pestañas del panel

El panel de administración (**Convoca Publisher**) tiene 5 pestañas:

| Pestaña | Para qué sirve |
|---------|----------------|
| **Configuración** | Tokens de cada red social, publicación automática, aviso de privacidad |
| **Canales** | Estado de cada red + botón "🔍 Verificar conexión" para probar la integración |
| **Probar** | Selecciona una entrada y publica en todas las redes para probar |
| **Plantillas** | Personaliza el mensaje de cada red con variables |
| **📖 Guía** | Instrucciones paso a paso para obtener credenciales de cada red |

## 3. Configurar canales

### 3.1 Obtener credenciales

Usa la pestaña **📖 Guía** para ver instrucciones detalladas de cada red. Resumen:

| Canal | ¿Qué necesitas? | Dónde obtenerlo |
|-------|----------------|-----------------|
| **Facebook / Instagram** | Token de página (Graph API) + Page ID | developers.facebook.com |
| **LinkedIn** | Access Token (OAuth 2.0) | linkedin.com/developers |
| **Twitter / X** | Bearer Token (API v2) | developer.twitter.com |
| **TikTok** | Access Token + Open ID | developers.tiktok.com |
| **Google My Business** | Token OAuth + Location ID | console.cloud.google.com |
| **Telegram** | Bot Token + Chat ID | @BotFather en Telegram |
| **Mastodon** | Access Token + Instance URL | tu instancia de Mastodon |

### 3.2 Verificar conexión

En la pestaña **Canales**, cada red tiene un botón **"🔍 Verificar conexión"** que:
- Hace una llamada real a la API de la red
- Muestra ✅ si todo funciona (nombre del bot/perfil)
- Muestra ❌ con el mensaje de error si falla

### 3.3 Seguridad

Los tokens se almacenan cifrados con **AES-256-GCM**. La clave de cifrado se deriva de `AUTH_KEY` + `SECURE_AUTH_KEY` de WordPress. Si cambias estas constantes, tendrás que reconfigurar los canales.

## 4. Plantillas por canal

Cada canal puede tener un mensaje diferente. Ve a **Convoca Publisher → Plantillas** para configurarlas.

### Variables disponibles

| Variable | Se sustituye por |
|----------|-----------------|
| `{title}` | Título de la entrada |
| `{excerpt}` | Extracto |
| `{url}` | Enlace permanente |
| `{hashtags}` | Las 5 primeras etiquetas como hashtags |
| `{date}` | Fecha de publicación |
| `{author}` | Nombre del autor |
| `{featured_image}` | URL de la imagen destacada |

### Plantillas por defecto

| Canal | Plantilla por defecto |
|-------|----------------------|
| Facebook | `{title} — {url} {hashtags}` |
| LinkedIn | `{title} — {url} {hashtags}` |
| Twitter/X | `{title} {url} {hashtags}` |
| Telegram | `{title} — {url} {hashtags}` |
| Mastodon | `{title} — {url} {hashtags}` |
| TikTok | `{title}` |
| Google My Business | `{excerpt} — {url}` |

### Ejemplo

```
📢 {title}

{excerpt}

🔗 {url}
{hashtags}
```

## 5. Publicar una entrada

### 5.1 Publicación automática

Al publicar una entrada desde WordPress, Convoca Publisher:

1. Detecta la publicación
2. **Valida** que el título no esté vacío y que haya imagen destacada (avisa si falta)
3. Prepara el mensaje con la plantilla de cada canal activo
4. Publica en cada red social seleccionada
5. Muestra el resultado en el metabox del editor

### 5.2 Metabox del editor

En el editor de entradas, el metabox **Convoca Publisher** (columna derecha) muestra:

- **Canales**: checkboxes para seleccionar en qué redes publicar esta entrada
- **Estado**: publicado, pendiente, error
- **Warnings**: avisos si falta título o imagen destacada
- **Programar**: campo de fecha/hora para publicar en redes más tarde
- **Republicar**: botón para reintentar un envío fallido

### 5.3 Programar publicación en redes

Puedes publicar el post en WordPress ahora pero **enviarlo a redes más tarde**:

1. En el metabox, marca los canales deseados
2. Rellena "Programar publicación" con la fecha/hora deseada
3. Publica el post normalmente
4. El sistema lo enviará a redes automáticamente a la hora indicada

Si no indicas fecha, se publica en redes inmediatamente al publicar el post.

### 5.4 Validaciones

Antes de enviar a redes, el sistema comprueba:

| Condición | Qué ocurre |
|-----------|------------|
| **Título vacío** | ⚠️ Warning en el metabox + se registra en el historial |
| **Sin imagen destacada** | ⚠️ Warning (no bloquea, pero algunas redes como Facebook/Twitter necesitan imagen) |

Los warnings se muestran con icono ⚠️ en el metabox y se registran en el historial con canal `VALIDACIÓN`.

## 6. Cola de reintentos

Si una publicación falla (error de API, rate limit), el sistema:

1. Guarda el intento en la cola de reintentos
2. Reintenta una vez más (máximo 2 intentos por post)
3. Si vuelve a fallar, se marca como error permanente

## 7. Historial

**Convoca Publisher → Historial** muestra las últimas 200 publicaciones con:

- Fecha y hora
- Canal (o `VALIDACIÓN` para warnings)
- Estado (éxito o error)
- Enlace al post en la red social
- Mensaje de error (si falló)

También incluye estadísticas de la cola de reintentos (pendientes/fallidos).

## 8. REST API

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

## 9. Problemas comunes

| Problema | Solución |
|----------|----------|
| **Token caducado** | Reconfigura el canal en Ajustes. Los tokens de Facebook/Twitter caducan periódicamente |
| **Error 401/403** | El token no tiene permisos suficientes. Revisa los scopes al generarlo |
| **No publica en Instagram** | Instagram requiere una página de Facebook vinculada. Publica a través del canal de Facebook |
| **TikTok no funciona** | Requiere Content Posting API (no disponible en todas las cuentas). Usa "Verificar conexión" para diagnosticar |
| **Rate limit** | El sistema reintenta automáticamente una vez |
| **No aparece en el metabox** | Asegúrate de que el post type es "post" (no funciona en páginas ni CPTs) |
| **Warning de validación** | Revisa el título y la imagen destacada del post antes de republicar |
