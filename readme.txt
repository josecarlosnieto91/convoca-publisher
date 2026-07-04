=== Convoca Publisher ===
Contributors: josecarlosnietoramos
Tags: social media, publish, facebook, twitter, linkedin, telegram, scheduling
Requires at least: 6.4
Tested up to: 7.0
Requires PHP: 8.1
Stable tag: 1.4.0
License: GPL-2.0-or-later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Publica entradas automáticamente en redes sociales desde WordPress.

== Description ==

Publica automáticamente tus entradas de WordPress en redes sociales. Sin suscripciones, sin dependencias externas. Los tokens se almacenan cifrados con AES-256-GCM.

Redes soportadas: Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon.

* Publicación automática al publicar una entrada
* Metabox con checkboxes por red, estado y programación
* Plantillas de mensaje personalizables por canal
* Variables: {title}, {excerpt}, {url}, {hashtags}, {date}, {author}
* Cola de reintentos automáticos (máx. 2 intentos)
* Historial de las últimas 200 publicaciones
* REST API para integraciones externas
* Tokens cifrados con AES-256-GCM

Funcionalidades PRO (requieren licencia):
* Programación de publicación en redes
* Cola de publicación programada y reintentos avanzados
* 8 canales simultáneos

= Servicios externos =

Este plugin se conecta con las APIs de las redes sociales configuradas (Facebook, Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon) para publicar contenido. Las credenciales se almacenan cifradas localmente. También puede contactar con getconvoca.app para validar licencias PRO.

== Changelog ==

= 1.4.0 =
* Nuevo: Programación de publicación en redes
* Nuevo: Validaciones pre-publicación (título, imagen destacada)
* Nuevo: Canal TikTok
* Nuevo: Canal Google My Business
* Mejora: 42 tests unitarios, 148 aserciones
* Mejora: Guía detallada por canal en el panel de administración
