# 🍊 Convoca Publisher

**Publica tus entradas de WordPress en redes sociales automáticamente.**

Parte del [ecosistema Convoca](https://github.com/josecarlosnieto91). 7 redes sociales desde tu WordPress. Canales gratuitos y PRO según tus necesidades.

[![CI](https://github.com/josecarlosnieto91/convoca-publisher/actions/workflows/ci.yml/badge.svg)](https://github.com/josecarlosnieto91/convoca-publisher/actions/workflows/ci.yml)
![PHP](https://img.shields.io/badge/PHP-8.0%2B-777bb3)
![WP](https://img.shields.io/badge/WordPress-6.0%2B-21759b)

## ✨ Canales incluidos

| Canal | Estado | Publica |
|-------|--------|---------|
| Facebook / Instagram | ✅ | Mensaje + enlace + imagen |
| LinkedIn | ✅ | Artículo con enlace |
| Twitter / X | ✅ | Tweet con imagen |
| TikTok | ✅ | Video desde URL (Content Posting API) |
| Google My Business | ✅ | Post con CTA |
| Telegram | ✅ | Mensaje + foto |
| Mastodon | ✅ | Toot con imagen adjunta |

## 🚀 Características

- **Publicación automática** al publicar una entrada
- **Entradas programadas** — publica en redes cuando la entrada se publica
- **Plantillas por canal** — mensaje diferente para cada red social
- **Variables en plantillas**: `{title}`, `{excerpt}`, `{url}`, `{hashtags}`, `{date}`, `{author}`, `{featured_image}`
- **Hashtags automáticos** — las primeras 5 etiquetas del post se convierten en hashtags
- **Imagen destacada** incluida automáticamente
- **Metabox en el editor** — estado, resultados, canales activos, republicar
- **Tokens cifrados** con AES-256-GCM
- **Cola de reintentos** con backoff exponencial (5 intentos)
- **Historial** de las últimas 200 publicaciones
- **REST API** — `/convoca-publisher/v1/` (status, test, publish, dry-run)
- **Notificaciones** en el dashboard sobre canales sin configurar y reintentos

## 📦 Instalación

1. Descarga el [último release](https://github.com/josecarlosnieto91/convoca-publisher/releases)
2. Sube la carpeta `convoca-publisher` a `/wp-content/plugins/`
3. Activa el plugin desde el menú Plugins
4. Ve a **Convoca Publisher → Ajustes** y configura los tokens

## 🛠️ Desarrollo

```bash
git clone https://github.com/josecarlosnieto91/convoca-publisher.git
cd convoca-publisher
composer install
composer test
```


### 1.3.1
- docs: add MANUAL_USUARIO.md with 7 social networks admin guide

## 📋 Requisitos

- PHP 8.0+
- WordPress 6.0+
- Extensions: `openssl`, `json`, `mbstring`

## 🏗️ Arquitectura

```
convoca-publisher/
├── convoca-publisher.php      # Entry point + activation hooks
├── uninstall.php              # Clean uninstall
├── includes/
│   ├── class-plugin.php       # Main plugin bootstrap
│   ├── class-admin.php        # Admin UI (settings, channels, test, templates)
│   ├── class-publisher.php    # Core publishing engine + per-channel templates
│   ├── class-scheduler.php    # Cron-based retry scheduler
│   ├── class-retry.php        # Retry queue with exponential backoff
│   ├── class-crypto.php       # AES-256-GCM token encryption
│   ├── class-metabox.php      # Post editor metabox
│   ├── class-notifications.php# Dashboard alerts
│   ├── class-rest.php         # REST API endpoints
│   └── channels/
│       ├── interface-channel.php       # Channel contract
│       ├── class-facebook.php          # Facebook / Instagram
│       ├── class-linkedin.php          # LinkedIn
│       ├── class-twitter.php           # Twitter / X
│       ├── class-tiktok.php            # TikTok
│       ├── class-googlemybusiness.php  # Google My Business
│       ├── class-telegram.php          # Telegram
│       └── class-mastodon.php          # Mastodon
├── assets/                    # Plugin banners, icons, screenshots
├── languages/                 # Translation files
├── composer.json
├── phpstan.neon
├── phpunit.xml.dist
└── readme.txt                 # WordPress.org plugin readme
```

## 🔌 REST API

Todos los endpoints requieren autenticación (usuario con `manage_options`).

```bash
# Status
GET /wp-json/convoca-publisher/v1/status

# Publish a post
POST /wp-json/convoca-publisher/v1/publish/{post_id}

# Dry run (simulate without calling APIs)
POST /wp-json/convoca-publisher/v1/publish/{post_id}?dry_run=true

# Test specific channel
POST /wp-json/convoca-publisher/v1/test/{channel}
Body: { "post_id": 123 }
```

## 🔐 Privacidad

Este plugin envía datos (título, extracto, URL, imagen destacada y etiquetas) a APIs de terceros. Los tokens de acceso se almacenan cifrados con AES-256-GCM.

## 📄 Licencia

GPL v2 o posterior. Ver [LICENSE](LICENSE).

## 👤 Autor

**José Carlos Nieto Ramos** — [josecarlosnietoramos.wordpress.com](https://josecarlosnietoramos.wordpress.com)

---

🍊 Hecho con cariño como parte del ecosistema Convoca.
## 🧪 Demo

Prueba Convoca sin instalar nada:

👉 **[demo.getconvoca.app](https://demo.getconvoca.app)**

## 📸 Capturas

| Socios | Actividades | Turnos | Inscripciones |
|--------|-------------|--------|---------------|
| ![Socios](https://getconvoca.app/wp-content/uploads/2026/06/convoca-miembros-v4.png) | ![Actividades](https://getconvoca.app/wp-content/uploads/2026/06/convoca-actividades-v4.png) | ![Turnos](https://getconvoca.app/wp-content/uploads/2026/06/convoca-turnos-v4.png) | ![Inscripciones](https://getconvoca.app/wp-content/uploads/2026/06/convoca-inscripciones-v4.png) |

## 🔗 Ecosistema

- [Convoca Core](https://github.com/josecarlosnieto91/convoca-core)
- [Convoca Members](https://github.com/josecarlosnieto91/convoca-members)
- [Convoca Enroll](https://github.com/josecarlosnieto91/convoca-enroll)
- [Convoca Gateway](https://github.com/josecarlosnieto91/convoca-gateway)
- [Convoca Shifts](https://github.com/josecarlosnieto91/convoca-shifts)
- [Convoca Publisher](https://github.com/josecarlosnieto91/convoca-publisher)

## 📖 Documentación

La documentación completa (manual de usuario, API REST, hooks, instalación) vive en la wiki:

👉 **[Convoca publisher](https://docs.getconvoca.app/plugins/convoca-publisher/)**
