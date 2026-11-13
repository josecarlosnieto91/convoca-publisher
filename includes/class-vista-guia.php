<?php
/**
 * La guía de configuración del panel, aparte.
 *
 * Era el método más largo de `Admin` que no tocaba nada: solo pinta texto. Sacarlo deja la clase
 * como controlador y permite comparar el HTML antes y después sin ruido.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher;

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * Guía de configuración.
 */
final class Vista_Guia {

	/**
	 * Pinta la guía.
	 */
	public static function render(): void {
		?>
		<div class="cp-section">
			<h2><?php echo esc_html__( '📖 Cómo funciona esto', 'convoca-publisher' ); ?></h2>
			<ul class="cp-steps">
				<li><strong><?php echo esc_html__( 'Una red puede tener varias cuentas.', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'La página y el grupo de Facebook son dos cuentas, cada una con sus credenciales y su plantilla. Se les puede poner nombre para reconocerlas (hasta 5 por red).', 'convoca-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'El mensaje se decide de lo concreto a lo general:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'lo que escribas para un envío, el mensaje de esa entrada, la plantilla de la cuenta, la de la red y la global. En el editor ves cómo queda y cuánto ocupa para cada red.', 'convoca-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Lo que admite cada red se comprueba antes de enviar:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'si el mensaje no cabe, se recorta conservando el enlace y se te dice; no falla al enviar en silencio.', 'convoca-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'La pestaña Cola es donde se ve todo lo que va a salir:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'calendario de mes y semana (arrastra un envío para reprogramarlo), lo que espera turno, lo que se quedó atrás y lo último que salió.', 'convoca-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Puedes compartir a mano en una sola cuenta', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'desde el editor (un botón por cuenta) o desde el listado de entradas, sin tocar las demás.', 'convoca-publisher' ); ?></li>
				<li><strong><?php echo esc_html__( 'Si algo falla, no se pierde en silencio:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'se reintenta con espera creciente, y si se agotan los intentos se te avisa por correo y queda a la vista en la cola para darle salida a mano.', 'convoca-publisher' ); ?></li>
			</ul>
			<h2><?php echo esc_html__( '📖 Guía de configuración', 'convoca-publisher' ); ?></h2>
			<p><?php echo esc_html__( 'Sigue estos pasos para configurar cada red social. Necesitarás una cuenta de desarrollador en cada plataforma para obtener los tokens de acceso.', 'convoca-publisher' ); ?></p>
		</div>

		<!-- Caducidad y límites -->
		<div class="cp-section" id="caducidad-redes">
			<h2>⏱️ <?php esc_html_e( 'Caducidad y límites que conviene saber', 'convoca-publisher' ); ?></h2>
			<p><?php esc_html_e( 'El plugin guarda el token que se pega a mano; no lo refresca solo. Estos son los plazos reales de cada red:', 'convoca-publisher' ); ?></p>
			<table class="widefat striped">
				<thead><tr><th><?php esc_html_e( 'Red', 'convoca-publisher' ); ?></th><th><?php esc_html_e( 'Duración de la credencial', 'convoca-publisher' ); ?></th><th><?php esc_html_e( 'Límite o requisito', 'convoca-publisher' ); ?></th></tr></thead>
				<tbody>
					<tr><td><?php esc_html_e( 'Facebook', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'No caduca si es token de página', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'Un token de usuario caduca a los 60 días; por eso el campo pide el de página.', 'convoca-publisher' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Instagram', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'La del token de página', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'Imagen JPEG, HTTPS y proporción 4:5; no se puede editar el pie después.', 'convoca-publisher' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'LinkedIn', 'convoca-publisher' ); ?></td><td><?php esc_html_e( '60 días', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'El token de refresco de 365 días solo lo tienen apps partner; aquí se pega el de acceso.', 'convoca-publisher' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'TikTok', 'convoca-publisher' ); ?></td><td><?php esc_html_e( '1 día (24 h)', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'Exige vídeo; el token de acceso se renueva a mano.', 'convoca-publisher' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Google My Business', 'convoca-publisher' ); ?></td><td><?php esc_html_e( '1 hora', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'El aviso del plugin usa 1 día de margen; el token real dura 60 minutos.', 'convoca-publisher' ); ?></td></tr>
					<tr><td><?php esc_html_e( 'Twitter/X, Telegram y Mastodon', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'No caducan por su cuenta', 'convoca-publisher' ); ?></td><td><?php esc_html_e( 'Telegram: el bot debe ser administrador del grupo y el Chat ID es negativo. Los pies con imagen se limitan a 1024 caracteres.', 'convoca-publisher' ); ?></td></tr>
				</tbody>
			</table>
		</div>

		<!-- Facebook / Instagram -->
		<div class="cp-section" id="canal-facebook">
			<h2>📘 <?php esc_html_e( 'Facebook / Instagram', 'convoca-publisher' ); ?></h2>
			<p><strong><?php esc_html_e( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php esc_html_e( 'Página de Facebook, cuenta de Instagram profesional vinculada a esa página y una app de Meta for Developers.', 'convoca-publisher' ); ?></p>

			<h3><?php esc_html_e( '1. Comprueba el vínculo Instagram ↔ página', 'convoca-publisher' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Convierte la cuenta de Instagram en profesional (Business o Creator). Una cuenta personal no publica por API.', 'convoca-publisher' ); ?></li>
				<li><?php esc_html_e( 'En la página de Facebook: Configuración → Cuentas vinculadas → Instagram. La cuenta debe aparecer ahí; si no, el canal publicará en Facebook y se saltará Instagram.', 'convoca-publisher' ); ?></li>
			</ol>

			<h3><?php esc_html_e( '2. Crea la app y autoriza solo lo necesario', 'convoca-publisher' ); ?></h3>
			<ol>
				<li>
				<?php
				printf(
					/* translators: %s: URL/link to the developer portal */
					esc_html__( 'Ve a %s', 'convoca-publisher' ),
					'<a href="https://developers.facebook.com/apps/" target="_blank">developers.facebook.com/apps/</a>'
				);
				?>
				</li>
				<li><?php esc_html_e( 'Crea una app tipo "Business".', 'convoca-publisher' ); ?></li>
				<li><?php esc_html_e( 'En Productos, añade "Facebook Login" e "Instagram Graph API".', 'convoca-publisher' ); ?></li>
				<li><?php esc_html_e( 'Permisos exactos: pages_show_list, pages_read_engagement, pages_manage_posts, instagram_basic e instagram_content_publish.', 'convoca-publisher' ); ?></li>
				<li><?php esc_html_e( 'En la ventana de consentimiento elige "solo las cuentas actuales" y marca únicamente la página y la cuenta de Instagram que se van a usar. No marques "todas las actuales y futuras": eso da acceso a páginas que no tocan.', 'convoca-publisher' ); ?></li>
				<li><?php esc_html_e( 'Mientras la app esté en modo desarrollo funciona para administradores de la app, sin revisión de Meta. No pidas revisión hasta que la integración esté probada.', 'convoca-publisher' ); ?></li>
			</ol>

			<h3><?php esc_html_e( '3. Obtén el token de página y los dos ID', 'convoca-publisher' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Genera un token de PÁGINA, no de usuario. El de página derivado de un usuario de larga duración no caduca; el de usuario caduca en 60 días.', 'convoca-publisher' ); ?></li>
				<li><?php esc_html_e( 'Copia el Page ID de la página de Facebook.', 'convoca-publisher' ); ?></li>
				<li><?php esc_html_e( 'El Instagram Business ID no se teclea de memoria. Léelo con el token de página:', 'convoca-publisher' ); ?><br><code>GET https://graph.facebook.com/v21.0/{page-id}?fields=instagram_business_account&amp;access_token={page-token}</code></li>
				<li><?php esc_html_e( 'Pega el Page Access Token, el Page ID y el Instagram Business ID en Canales → Facebook / Instagram.', 'convoca-publisher' ); ?></li>
			</ol>
			<div class="notice notice-warning inline"><p><strong><?php esc_html_e( 'Imagen obligatoria en Instagram:', 'convoca-publisher' ); ?></strong> <?php esc_html_e( 'sin imagen destacada la publicación se salta y solo queda el aviso en el historial. Meta necesita una URL pública HTTPS que responda 200, en JPEG (WebP no sirve) y con proporción dentro de 4:5; un cartel A4 vertical se rechaza o se recorta.', 'convoca-publisher' ); ?></p></div>
			<p class="description"><?php esc_html_e( 'Instagram no permite editar el pie ni borrar un medio publicado con estos permisos. Revisa el texto antes de enviar.', 'convoca-publisher' ); ?></p>
			<p><strong><?php esc_html_e( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php esc_html_e( 'Page Access Token, Page ID, Instagram Business ID (opcional, pero necesario para publicar en Instagram).', 'convoca-publisher' ); ?></p>
			<p><a href="https://developers.facebook.com/docs/pages/publishing/" target="_blank">📄 <?php esc_html_e( 'Documentación oficial de Meta', 'convoca-publisher' ); ?></a></p>
		</div>

		<!-- LinkedIn -->
		<div class="cp-section" id="canal-linkedin">
			<h2>💼 <?php echo esc_html__( 'LinkedIn', 'convoca-publisher' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Cuenta de LinkedIn, App de LinkedIn Developer', 'convoca-publisher' ); ?></p>
			<ol>
				<li>
				<?php
				printf(
					/* translators: %s: URL/link to the developer portal */
					esc_html__( 'Ve a %s', 'convoca-publisher' ),
					'<a href="https://www.linkedin.com/developers/apps" target="_blank">linkedin.com/developers/apps</a>'
				);
				?>
					</li>
				<li><?php echo esc_html__( 'Crea una nueva app', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Solicita los permisos (products): "Share on LinkedIn" y "Sign In with LinkedIn"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Ve a la pestaña Auth y genera un Access Token de prueba (OAuth 2.0)', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Copia el token y pégalo en la pantalla del canal: Canales → LinkedIn', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Para la URN: usa tu perfil (urn:li:person:...) o página de empresa (urn:li:organization:...)', 'convoca-publisher' ); ?></li>
			</ol>
			<p><strong><?php echo esc_html__( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Access Token (OAuth 2.0), URN de perfil/página', 'convoca-publisher' ); ?></p>
			<p><a href="https://learn.microsoft.com/en-us/linkedin/marketing/" target="_blank">📄 <?php echo esc_html__( 'Documentación oficial de LinkedIn', 'convoca-publisher' ); ?></a></p>
		</div>

		<!-- Twitter / X -->
		<div class="cp-section" id="canal-twitter">
			<h2>🐦 <?php echo esc_html__( 'Twitter / X', 'convoca-publisher' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Cuenta de desarrollador de X (antes Twitter), Proyecto en Developer Portal', 'convoca-publisher' ); ?></p>
			<ol>
				<li>
				<?php
				printf(
					/* translators: %s: URL/link to the developer portal */
					esc_html__( 'Ve a %s', 'convoca-publisher' ),
					'<a href="https://developer.twitter.com/en/portal/dashboard" target="_blank">developer.twitter.com</a>'
				);
				?>
					</li>
				<li><?php echo esc_html__( 'Crea un proyecto y una app (OAuth 2.0)', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'En "Keys and Tokens", genera un Bearer Token (OAuth 2.0)', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Asegúrate de que la app tenga permisos tweet.read y tweet.write', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Copia el Bearer Token y pégalo en la pantalla del canal: Canales → Twitter / X', 'convoca-publisher' ); ?></li>
			</ol>
			<p><strong><?php echo esc_html__( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Bearer Token (OAuth 2.0)', 'convoca-publisher' ); ?></p>
			<p><a href="https://developer.twitter.com/en/docs/twitter-api" target="_blank">📄 <?php echo esc_html__( 'Documentación oficial de X API', 'convoca-publisher' ); ?></a></p>
		</div>

		<!-- TikTok -->
		<div class="cp-section" id="canal-tiktok">
			<h2>🎵 <?php echo esc_html__( 'TikTok', 'convoca-publisher' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Cuenta de desarrollador de TikTok, App en TikTok Developer Portal', 'convoca-publisher' ); ?></p>
			<ol>
				<li>
				<?php
				printf(
					/* translators: %s: URL/link to the developer portal */
					esc_html__( 'Ve a %s', 'convoca-publisher' ),
					'<a href="https://developers.tiktok.com/" target="_blank">developers.tiktok.com</a>'
				);
				?>
					</li>
				<li><?php echo esc_html__( 'Crea una app y selecciona los permisos "video.publish" y "user.info.basic"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Completa el flujo OAuth para obtener un Access Token', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Anota el Open ID (identificador único del usuario)', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Pega ambos en la pantalla del canal: Canales → TikTok', 'convoca-publisher' ); ?></li>
			</ol>
			<p><strong><?php echo esc_html__( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Access Token, Open ID', 'convoca-publisher' ); ?></p>
			<p><a href="https://developers.tiktok.com/documentation" target="_blank">📄 <?php echo esc_html__( 'Documentación oficial de TikTok', 'convoca-publisher' ); ?></a></p>
		</div>

		<!-- Google My Business -->
		<div class="cp-section" id="canal-googlemybusiness">
			<h2>🏪 <?php esc_html_e( 'Google My Business', 'convoca-publisher' ); ?></h2>
			<p><strong><?php esc_html_e( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php esc_html_e( 'Cuenta de Google, proyecto en Google Cloud Console, perfil de empresa y acceso aprobado a las APIs de Google Business Profile.', 'convoca-publisher' ); ?></p>

			<h3><?php esc_html_e( '1. Pide el acceso (allowlist) ANTES de crear credenciales', 'convoca-publisher' ); ?></h3>
			<p><?php esc_html_e( 'La API v4 no es de autoservicio. Con un proyecto nuevo responde 403 "Permission denied to get service". Hay que solicitar el acceso básico desde una cuenta que administre el perfil, indicando el número de proyecto, en:', 'convoca-publisher' ); ?> <a href="https://support.google.com/business/workflow/16726127" target="_blank"><?php esc_html_e( 'Solicitud de acceso básico a las APIs', 'convoca-publisher' ); ?></a>.</p> <!-- convoca-hygiene-ignore: dominio público de Google. -->
			<p><?php esc_html_e( 'La revisión tarda 7–10 días laborables y solo se admite una solicitud por proyecto. No la reenvíes: si está en revisión, espera.', 'convoca-publisher' ); ?></p>

			<h3><?php esc_html_e( '2. Habilita las tres APIs, no solo una', 'convoca-publisher' ); ?></h3>
			<ul>
				<li><code>mybusiness.googleapis.com</code> — <?php esc_html_e( 'publicaciones (API v4).', 'convoca-publisher' ); ?></li>
				<li><code>mybusinessaccountmanagement.googleapis.com</code> — <?php esc_html_e( 'listar cuentas.', 'convoca-publisher' ); ?></li>
				<li><code>mybusinessbusinessinformation.googleapis.com</code> — <?php esc_html_e( 'leer ubicaciones.', 'convoca-publisher' ); ?></li>
			</ul>
			<p><?php esc_html_e( 'Se pueden habilitar por autoservicio, pero mientras el proyecto no esté aprobado responden 429 con quota_limit_value: 0. La API está encendida y la cuota es cero: ese 429 es el bloqueo real, no un token mal copiado.', 'convoca-publisher' ); ?></p>

			<h3><?php esc_html_e( '3. Credenciales OAuth y scope', 'convoca-publisher' ); ?></h3>
			<p><?php esc_html_e( 'Crea credenciales OAuth 2.0 y autoriza el scope exacto:', 'convoca-publisher' ); ?> <code>https://www.googleapis.com/auth/business.manage</code>.</p>

			<h3><?php esc_html_e( '4. Obtén el Location ID después de la aprobación', 'convoca-publisher' ); ?></h3>
			<ol>
				<li><?php esc_html_e( 'Lista las cuentas a las que tiene acceso el usuario:', 'convoca-publisher' ); ?><br><code>GET https://mybusinessaccountmanagement.googleapis.com/v1/accounts</code><!-- convoca-hygiene-ignore: API pública de Google. --></li>
				<li><?php esc_html_e( 'Lista las ubicaciones de la cuenta y copia el name completo:', 'convoca-publisher' ); ?><br><code>GET https://mybusinessbusinessinformation.googleapis.com/v1/{account}/locations</code></li>
				<li><?php esc_html_e( 'El valor que se pega tiene la forma accounts/{accountId}/locations/{locationId}.', 'convoca-publisher' ); ?></li>
			</ol>

			<h3><?php esc_html_e( '5. El token dura una hora', 'convoca-publisher' ); ?></h3>
			<p><?php esc_html_e( 'El campo pide un Access Token OAuth 2.0 pegado a mano. Google lo caduca a los 60 minutos, así que el canal sirve para una publicación puntual, no para automatizar. Si el token se pega una hora antes, la publicación fallará al día siguiente.', 'convoca-publisher' ); ?></p>
			<p class="description"><?php esc_html_e( 'Para automatizar de verdad haría falta un refresh token (client_id, client_secret y refresh_token) y refrescar en cada envío. Hoy el plugin no lo hace; no es un fallo del token.', 'convoca-publisher' ); ?></p>
			<p><strong><?php esc_html_e( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php esc_html_e( 'Access Token (OAuth 2.0), Location ID.', 'convoca-publisher' ); ?></p>
			<p><a href="https://developers.google.com/my-business" target="_blank">📄 <?php esc_html_e( 'Documentación oficial de Google Business Profile API', 'convoca-publisher' ); ?></a></p>
		</div>

		<!-- Telegram -->
		<div class="cp-section" id="canal-telegram">
			<h2>💬 <?php echo esc_html__( 'Telegram', 'convoca-publisher' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Un bot de Telegram', 'convoca-publisher' ); ?></p>
			<ol>
				<li><?php echo esc_html__( 'Abre Telegram y busca @BotFather', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Envía /newbot y sigue las instrucciones', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Copia el token HTTP API que te da BotFather', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Envía /setprivacy y selecciona Disabled', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Para obtener el Chat ID: envía un mensaje a tu bot, luego visita:', 'convoca-publisher' ); ?>
					<br><code>https://api.telegram.org/bot&lt;TU_TOKEN&gt;/getUpdates</code></li>
				<li><?php echo esc_html__( 'Copia el "chat":{"id":...} que aparece en la respuesta', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Pega token y chat ID en la pantalla del canal: Canales → Telegram', 'convoca-publisher' ); ?></li>
			</ol>
			<p><strong><?php echo esc_html__( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Token del Bot, Chat ID', 'convoca-publisher' ); ?></p>
			<p><a href="https://core.telegram.org/bots/api" target="_blank">📄 <?php echo esc_html__( 'Documentación oficial de Telegram Bot API', 'convoca-publisher' ); ?></a></p>
		</div>

		<!-- Mastodon -->
		<div class="cp-section" id="canal-mastodon">
			<h2>🐘 <?php echo esc_html__( 'Mastodon', 'convoca-publisher' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Cuenta en un servidor de Mastodon', 'convoca-publisher' ); ?></p>
			<ol>
				<li><?php echo esc_html__( 'Inicia sesión en tu instancia de Mastodon (ej: mastodon.social)', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Ve a Preferencias → Desarrollo', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Haz clic en "Nueva aplicación"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Asigna un nombre (ej: Convoca Publisher) y marca el permiso "write:statuses"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Copia el "Access Token" que se genera', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Anota también la URL base de tu servidor (ej: https://mastodon.social)', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Pega ambos en la pantalla del canal: Canales → Mastodon', 'convoca-publisher' ); ?></li>
			</ol>
			<p><strong><?php echo esc_html__( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Servidor (URL base), Access Token, Visibilidad (opcional)', 'convoca-publisher' ); ?></p>
			<p><a href="https://docs.joinmastodon.org/api/" target="_blank">📄 <?php echo esc_html__( 'Documentación oficial de Mastodon API', 'convoca-publisher' ); ?></a></p>
		</div>
		<?php
	}
}
