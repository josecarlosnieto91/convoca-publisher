# Changelog — convoca-publisher

## v1.4.1 (2026-08-07)

### ✨ Mejoras
- **Envío diferido a redes**: la publicación se programa vía cron (`convoca_publisher_async_publish`, ~5s después). El guardado del post ya no se bloquea por la latencia de las redes.

## v1.4.0 (2026-06-28)

### ✨ Nuevas funcionalidades
- **verify_connection()**: Verificación real de conexión con cada red social vía API. Botón "🔍 Verificar conexión" en pestaña Canales
- **Guía integrada**: Nueva pestaña "📖 Guía" con instrucciones paso a paso para obtener credenciales de cada red social
- **Programación por post**: Campo "Programar publicación" en el metabox del editor. Elige una fecha/hora futura para publicar en redes
- **Validaciones pre-publicación**: Avisa si falta el título o la imagen destacada antes de enviar a redes
- **Soporte para 7 canales**: Facebook/Instagram, LinkedIn, Twitter/X, TikTok, Google My Business, Telegram, Mastodon

### 🧪 Tests
- 32 tests unitarios (verify_connection, mensajes, imágenes destacadas)
- PHPStan nivel 6, 0 errores

### 🔧 Mejoras
- **get_channel()**: Nuevo método público en Plugin para obtener un canal por ID
- Cron cada 15 minutos para publicaciones programadas
- Warnings visibles en metabox del editor + registrados en historial

---

## v1.3.1 (2026-06-24)

### ✨ Improvements
- Añadido license gating: FREE=3 canales, PRO=7 canales
- Mejoras menores de estabilidad y rendimiento

### 📦 Infrastructure
- Updated release ZIPs on getconvoca.app
- Demo environment synchronized

---
