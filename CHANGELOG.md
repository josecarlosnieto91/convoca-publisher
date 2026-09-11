# Changelog — convoca-publisher

## v1.21.1 (2026-09-11)

### 🐛 Correcciones
- Telegram no activaba el modo HTML cuando la opción estaba vacía y el `<b>` se veía como etiqueta; una opción vacía vuelve a significar HTML.
- Mastodon no acepta HTML al publicar (su API recibe texto plano), así que el título en negrita queda solo para Telegram.
- En las redes que interpretan HTML, los valores que vienen de la entrada se escapan (una sola vez) para que un `&` o un `<` no rompan el mensaje.

## v1.21.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- Nueva variable `{titulo_negrita}`: el título en negrita en las redes que admiten formato (Telegram en modo HTML y Mastodon) y tal cual en las demás, para que una misma plantilla valga en todas.

### 🐛 Correcciones
- El enlace ya no sale dos veces: Telegram, Mastodon y X solo lo añaden si el mensaje no lo lleva ya.

## v1.20.2 (2026-09-11)

### 🐛 Correcciones
- Los hashtags repetidos salen una sola vez: dos etiquetas que normalizan al mismo hashtag ya no lo duplican, y el tope de 5 cuenta hashtags distintos.

## v1.20.1 (2026-09-11)

### 🐛 Correcciones
- Guardar una plantilla daba un error 500 (bucle infinito en el saneador del intervalo de la cola).
- La sección del editor pasa a llamarse «Publicar en RRSS» y su texto ya no miente: dice la verdad según el ajuste de auto-publicación.

## v1.20.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Iconos de red propios** en SVG (sin peticiones externas), en la lista de canales y en la cabecera de cada cuenta.

### 🐛 Correcciones
- Una clase nueva no se cargaba en producción (el mapa de autoload desplegado estaba desactualizado); ahora las clases se cargan directamente, sin depender del paso de construcción.

## v1.19.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- Dos variables nuevas: `{categorias_hashtags}` (primeras 5 categorías como hashtags) y `{entradilla}` (el texto anterior a «seguir leyendo», o el extracto si no lo lleva).

### 🐛 Correcciones
- Las variables se sustituyen sin distinguir mayúsculas: un `{Title}` ya no se publica literal.
- `{categorias}` se comía el prefijo de `{categorias_hashtags}`; ahora se sustituye de la variable más larga a la más corta.

## v1.18.1 (2026-09-11)

### 🌍 Traducciones
- Catálogo regenerado y al día (453 cadenas), con las traducciones difusas corregidas a mano.

### 🐛 Correcciones
- La verificación de Instagram decía que la publicación por API no estaba implementada (desde la 1.17.0 sí lo está); corregido en el origen.

## v1.18.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Canal de pruebas**: en la pestaña Probar se puede elegir un único canal de destino (recomendado: un Telegram propio) para probar sin publicar en las redes reales. Sin canal elegido, se comporta como antes y avisa de que publica en las redes configuradas.

## v1.17.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Instagram se publica de verdad**: el canal de Facebook publica ahora también en Instagram con el flujo oficial de Meta (contenedor de medios y publicación). Sin imagen destacada no se intenta; si Instagram falla, el muro de Facebook ya publicado no se duplica.

## v1.16.2 (2026-09-11)

### 🔧 Mejoras
- Instagram deja de anunciarse como si publicara: la verificación pregunta ahora a Meta de verdad y confirma el ID, el permiso de publicación y la cuota restante de 24 h.

## v1.16.1 (2026-09-11)

### 🐛 Correcciones
- El aviso de caducidad de Facebook era falso: los tokens de página de larga duración no caducan, así que Facebook pasa a «no caduca».
- La pantalla de un canal mostraba un campo de plantilla vacío que no hacía nada; ahora no aparece.

## v1.16.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- Cuatro variables nuevas: `{categorias}`, `{etiquetas}`, `{sitio}` y `{autor_url}`. La categoría por defecto del sitio se ignora para no publicar «Uncategorized» en cada envío.

## v1.15.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Insertar variables con botones**: cada campo de plantilla trae botones para insertar las siete variables y volver a la plantilla de fábrica de esa red.
- **Contador y vista previa por red**: panel de vista previa con la entrada que se elija; el contador usa las reglas reales de cada red (en X muestra exactamente los caracteres que se enviarán).

## v1.14.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Plantillas multilínea**: los tres niveles (global, red y cuenta) pasan de campo de una línea a área de texto.
- **Plantilla de fábrica visible**: cada red muestra bajo su campo cuál es su plantilla por defecto y si ahora mismo usa esa o la global.

### 🔧 Mejoras
- La plantilla de fábrica de X pasa a ser corta y sin hashtags (no caben con el enlace).

## v1.13.1 (2026-09-11)

### 🐛 Correcciones
- La prueba de publicación no listaba las entradas (el desplegable salía vacío); ahora lista las entradas publicadas de verdad.

## v1.13.0 (2026-09-11)

### 🌍 Traducciones
- Traducción completa del plugin al inglés (en_US).
- Las traducciones al inglés viajan en el repositorio, de modo que un paquete hecho desde el repo ya no sale sin inglés.

## v1.12.2 (2026-09-11)

### 🧹 Mantenimiento
- Refactor interno del historial (sin cambios de comportamiento).

## v1.12.1 (2026-09-11)

### 🐛 Correcciones
- Los avisos de validación (falta de imagen destacada, título vacío o recortado) ya no aparecen como envíos fallidos en el historial.

## v1.12.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Historial con filtros** por red, cuenta y resultado.
- **Reintento individual**: se puede reintentar un envío concreto desde el historial, sin tocar las demás cuentas.

## v1.11.1 (2026-09-11)

### 🔧 Mejoras
- La pestaña Guía explica primero cómo funciona el plugin (varias cuentas por red, de dónde sale el mensaje, la cola y qué pasa cuando algo falla) antes de los pasos de cada red.

## v1.11.0 (2026-09-11)

### 🧹 Mantenimiento
- Formateo del código base (sin cambios de comportamiento).

## v1.10.2 (2026-09-11)

### 🐛 Correcciones
- El aviso de envíos parados contaba solo los que cabían en la lista (decía «5» con ocho parados); ahora cuenta el total real.

## v1.10.1 (2026-09-11)

### 🐛 Correcciones
- El widget mostraba la misma entrada dos veces (programada y con reintento vivo) y pintaba como fallidos los avisos del propio plugin.

## v1.10.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Widget de escritorio**: de un vistazo, lo que salió, lo que se quedó atascado y lo último. Si hay algo parado, avisa y lleva a la cola.

## v1.9.3 (2026-09-11)

### 🐛 Correcciones
- El plazo de caducidad ahora refleja el token real de cada red: TikTok (~24 h) y Google My Business (~1 h) caducan de verdad y ya no figuran como «no caduca».

## v1.9.2 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Aviso de caducidad de credenciales**: el canal avisa antes de que su token caduque («caduca pronto», «puede haber caducado») en vez de enterarse por un envío perdido. No inventa fechas, y las redes cuyo token no caduca (Telegram, Mastodon) no dan avisos falsos.

## v1.9.1 (2026-09-11)

### 🐛 Correcciones
- Un envío que fallaba se perdía: ahora se reintenta hasta cinco veces y, agotadas, queda a la vista para darle salida.
- La cola muestra lo que se quedó atrasado (cron sin correr, sitio caído, espaciado).

### ✨ Nuevas funcionalidades
- Aviso por correo (configurable) cuando un envío no sale tras varios intentos.

## v1.9.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Compartir por cuenta**: botón «compartir ahora en esta cuenta» en el editor y acción en el listado de entradas, con un único camino de publicación.
- **Mensaje propio de la entrada** con vista previa real y contador por red; manda sobre la plantilla de la cuenta.
- **Reglas por red**: límites (duros y recomendados), peso de los enlaces según la red y recorte que conserva el enlace; lo que no cabe ya no falla en silencio.

## v1.8.1 (2026-09-11)

### 🐛 Correcciones
- La vista de semana de la cola no avanzaba de semana (volvía a la primera del mes).
- El aviso de privacidad y el enlace de ajustes mandaban a una pestaña con otro nombre; ahora llevan a «Configuración».

## v1.8.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Calendario de cola**: rejilla de mes y semana con los envíos de cada día, lo que espera turno, lo atascado y lo último que salió.
- **Reprogramar arrastrando**: se puede mover un envío a otro día (o con formulario de fecha/hora), quitarlo de la cola y recolocar ahora.
- El espaciado entre envíos se configura en la pestaña Configuración.

## v1.7.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Cola unificada**: junta los envíos programados, los reintentos y lo ya enviado en una sola vista.
- **Espaciado entre envíos**: los envíos programados se reparten en el tiempo (sin espaciado, 15 o 30 min, 1 o 2 horas) en lugar de salir todos a la vez.

### 🐛 Correcciones
- La pestaña de plantillas listaba por cuenta y guardaba opciones que el publicador no leía; ahora lista por red y explica los tres niveles.

## v1.6.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Varias cuentas por red**: cada cuenta tiene su nombre visible, sus credenciales y su plantilla. El editor, la cola y el historial trabajan por cuenta (hasta cinco por red).
- La configuración previa de «un token por red» se migra automáticamente a una cuenta por red.

### 🔧 Mejoras
- Tokens cifrados por cuenta (AES-256-GCM), con el mismo formato que antes.

## v1.5.0 (2026-09-11)

### ✨ Nuevas funcionalidades
- **Pantalla por canal**: «Canales» pasa a ser la pantalla principal, con una tarjeta por red que muestra su estado (✅/❌/⚠️/🔑) y, al entrar, todo lo del canal junto: credenciales, plantilla, «Verificar conexión» y el enlace a su guía.
- **Asistente de inicio** que guía la primera configuración (Telegram → Mastodon → el resto) cuando no hay nada configurado.

### 🔧 Mejoras
- El estado de cada canal sale de la última verificación guardada y se ignora si cambió la configuración (no se muestra un error viejo con un token nuevo).
- CSS y JS en ficheros propios, cargados solo donde tocan; modo oscuro y foco visible.

## v1.4.5 (2026-09-11)

### 🐛 Correcciones
- El registro de canales dependía del mapa de clases de Composer: un canal nuevo sin regenerar el mapa desaparecía. Ahora se carga el fichero y se usa la clase declarada.

## v1.4.4 (2026-09-11)

### 🐛 Correcciones
- El registro encontraba cero canales y el plugin no publicaba en ninguna red; la pantalla de configuración tampoco mostraba los campos de token.

## v1.4.3 (2026-09-10)

### ✨ Nuevas funcionalidades
- **Reintentos con backoff real**: cuando una red falla, el envío se reintenta automáticamente con esperas crecientes (1 h, 4 h, 12 h, 24 h, 72 h) hasta 5 intentos; agotados, se marca como fallido y se avisa al administrador por correo.
- **Moderación previa configurable**: nueva pestaña «Moderación» con el listado de pendientes y acciones Aprobar/Rechazar. La publicación pendiente no se envía hasta aprobarla.

### 🐛 Correcciones
- El checkbox del aviso de privacidad no se podía marcar: el estado nunca se guardaba y el aviso no desaparecía.
- Dos crons solapados podían publicar el mismo post dos veces; ahora cada envío se reclama de forma atómica y solo un proceso lo gana.
- Al desactivar el plugin saltaba un error fatal (el hook no llevaba el namespace).
- La desinstalación dejaba tablas, opciones y eventos de cron huérfanos; ahora se limpian, con opción de conservar los datos.
- La constante de versión estaba congelada y el CDN servía JS/CSS antiguos; ahora sigue al header del plugin.

### 📦 Infraestructura
- Preparación para WordPress.org: Plugin Check 0 errores, textdomain duplicado eliminado, «Tested up to 7.1» y CI con plugin-check.
- Email de contacto unificado (hola@mg.getconvoca.app), fuera del dominio inexistente convoca.org.

## v1.4.2 (2026-09-05)

### 🔐 Security
- Cifrado de tokens solo para opciones propias (`convoca_publisher_*`)

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

*Las entradas desde la v1.4.3 se reconstruyeron desde el historial de git: estas versiones no tienen tag, así que la fuente son los mensajes de commit y las subidas de versión del header del plugin.*
