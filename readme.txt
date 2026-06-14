=== Convoca Publisher ===
Contributors: josecarlosnietoramos
Donate link: https://biodevas.org
Tags: social media, facebook, instagram, linkedin, twitter, x, tiktok, google my business, telegram, mastodon, auto-publish, scheduler, social, publish
Requires at least: 6.0
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.3.0
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publica automáticamente tus entradas de WordPress en múltiples redes sociales. Canales: Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram y Mastodon. Con plantillas personalizables por canal y cola de reintentos.

== Description ==

Convoca Publisher te permite publicar automáticamente tus entradas de WordPress en múltiples redes sociales con solo pulsar "Publicar". Forma parte del ecosistema Convoca.

= Canales incluidos =

* **Facebook / Instagram** — Publica en tu página de Facebook con publicación cruzada a Instagram
* **LinkedIn** — Publica en tu perfil o página de empresa
* **Twitter / X** — Publica tweets con enlaces e imágenes
* **TikTok** — Publica contenido en TikTok (requiere Content Posting API)
* **Google My Business** — Publica posts en tu perfil de negocio de Google
* **Telegram** — Publica mensajes en canales o grupos
* **Mastodon** — Publica en cualquier instancia de Mastodon

= Características =

* **Publicación automática** al publicar una entrada
* **Soporte para entradas programadas**
* **Plantillas por canal** — Personaliza el mensaje para cada red con variables como {title}, {excerpt}, {hashtags}, {url}, {date}, {author}
* **Hashtags automáticos** — Las primeras 5 etiquetas del post se convierten en hashtags
* **Imagen destacada** — Se incluye automáticamente cuando el canal lo soporta
* **Tokens cifrados** — Almacenamiento seguro con AES-256-GCM
* **Cola de reintentos** — Reintentos con backoff exponencial si falla la publicación
* **Historial de publicaciones** — Registro completo de todas las publicaciones
* **Panel de administración** — Configuración, estado de canales, pruebas e historial
* **REST API** — Endpoints para integración externa
* **Metabox en el editor** — Estado y controles desde la pantalla de edición
* **Extensible** — Fácil de añadir nuevos canales mediante la interfaz ChannelInterface
* **Sin suscripciones, sin dependencias externas de terceros**

= Privacidad =

Este plugin envía el título, extracto, URL, imagen destacada y etiquetas de tus entradas a APIs de terceros (Meta, LinkedIn, Twitter/X, TikTok, Google, Telegram, Mastodon) cuando se publica contenido. Los tokens de acceso se almacenan cifrados en la base de datos de WordPress.

== Installation ==

1. Sube la carpeta `convoca-publisher` a `/wp-content/plugins/`
2. Activa el plugin desde el menú Plugins
3. Ve a Convoca Publisher → Ajustes
4. Lee y acepta el aviso de privacidad
5. Configura los tokens de las redes que quieras usar
6. (Opcional) Personaliza las plantillas de mensaje en la pestaña Plantillas
7. ¡Listo! Al publicar una entrada se publicará automáticamente

== Frequently Asked Questions ==

= ¿Qué redes sociales soporta? =

Facebook, Instagram (a través de Facebook), LinkedIn, Twitter/X, TikTok, Google My Business, Telegram y Mastodon.

= ¿Necesito una cuenta de desarrollador? =

Sí, cada red requiere que crees una aplicación y configures tokens de acceso. Consulta la documentación de cada plataforma.

= ¿Se publica al programar entradas? =

Sí, si tienes activada la opción "Programación" en los ajustes.

= ¿Puedo desactivar la publicación para una entrada concreta? =

Sí, en el metabox de Convoca Publisher dentro del editor de entradas puedes desmarcar los canales que no quieras usar para esa entrada.

= ¿Qué variables están disponibles en las plantillas? =

{title} — Título de la entrada
{excerpt} — Extracto
{url} — Enlace permanente
{hashtags} — Primeras 5 etiquetas como hashtags
{date} — Fecha de publicación
{author} — Nombre del autor

= ¿Los tokens están seguros? =

Sí, todos los tokens se cifran con AES-256-GCM usando las salts de WordPress antes de almacenarse en la base de datos.

== Screenshots ==

1. Panel de ajustes principales
2. Estado de canales
3. Prueba de publicación
4. Plantillas personalizables por canal
5. Metabox en el editor de entradas
6. Historial de publicaciones

== Changelog ==

= 1.3.0 =
* Migración completa de opciones sp_* a cp_* (con migración automática al activar)
* Nuevo sistema de plantillas por canal con variables {title}, {excerpt}, {url}, {hashtags}, {date}, {author}
* Hashtags automáticos desde las primeras 5 etiquetas del post
* Imagen destacada, título y extracto incluidos automáticamente en cada publicación
* Nueva pestaña "Plantillas" en el panel de administración
* Aviso de privacidad obligatorio
* Página "Acerca de"
* uninstall.php completo (limpia opciones, metadatos y tabla de reintentos)
* Comprobación de requisitos PHP 8.0+ y WordPress 6.0+ al activar
* Cifrado mejorado (detecta automáticamente opciones que terminan en _token)
* Todas las salidas sanitizadas con esc_html()
* Nonces en todos los formularios y acciones AJAX
* README.txt compatible con WordPress.org
* Composer.json actualizado con dependencias de sistema (openssl, json, mbstring)
* CI/CD mejorado

= 1.2.0 =
* Nuevos canales: Telegram, Mastodon
* Cifrado de tokens (AES-256-GCM)
* Cola de reintentos con backoff exponencial
* Metabox en el editor con botón de republicar
* Notificaciones en el dashboard
* REST API (/convoca-publisher/v1/)
* Composer.json con dependencias dev
* GitHub Actions CI
* PHPUnit test suite con 4 tests iniciales
* PHPStan nivel 6 con stubs de WordPress
* Modo dry-run via REST API

= 1.1.0 =
* Añadidos canales: LinkedIn, Twitter/X, TikTok, Google My Business
* Arquitectura de canales extensible mejorada

= 1.0.0 =
* Lanzamiento inicial
* Canal Facebook/Instagram
* Publicación automática al publicar
* Soporte para entradas programadas
* Panel de administración con ajustes e historial
* Prueba de publicación

== Upgrade Notice ==

= 1.3.0 =
Migración de opciones sp_* a cp_*. La migración es automática al activar el plugin. Las opciones antiguas se convierten sin pérdida de datos.

== Additional Info ==

Este plugin es parte del ecosistema Convoca — un conjunto de plugins de código abierto para WordPress enfocados en la gestión de organizaciones, socios y comunicación digital.

Desarrollado por José Carlos Nieto Ramos — https://biodevas.org
