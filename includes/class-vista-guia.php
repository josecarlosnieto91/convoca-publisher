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

		<!-- Facebook / Instagram -->
		<div class="cp-section" id="canal-facebook">
			<h2>📘 <?php echo esc_html__( 'Facebook / Instagram', 'convoca-publisher' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Página de Facebook, App de Facebook Developer', 'convoca-publisher' ); ?></p>
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
				<li><?php echo esc_html__( 'Crea una app tipo "Business" o "Sin integración"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'En Productos, añade "Facebook Login" y "Instagram Graph API"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Ve a Herramientas → "Generar token de página"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Selecciona tu página y copia el token', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Copia también el ID de página (Page ID)', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Pega ambos en la pantalla del canal: Canales → Facebook / Instagram', 'convoca-publisher' ); ?></li>
			</ol>
			<p><strong><?php echo esc_html__( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Page Access Token, Page ID, Instagram Business ID (opcional)', 'convoca-publisher' ); ?></p>
			<p><a href="https://developers.facebook.com/docs/pages/publishing/" target="_blank">📄 <?php echo esc_html__( 'Documentación oficial de Meta', 'convoca-publisher' ); ?></a></p>
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
			<h2>🏪 <?php echo esc_html__( 'Google My Business', 'convoca-publisher' ); ?></h2>
			<p><strong><?php echo esc_html__( 'Requiere:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Cuenta de Google, Proyecto en Google Cloud Console, Perfil de empresa en Google', 'convoca-publisher' ); ?></p>
			<ol>
				<li>
				<?php
				printf(
					/* translators: %s: URL/link to the developer portal */
					esc_html__( 'Ve a %s', 'convoca-publisher' ),
					'<a href="https://console.cloud.google.com/" target="_blank">console.cloud.google.com</a>'
				);
				?>
					</li>
				<li><?php echo esc_html__( 'Crea un proyecto y habilita la API "Google My Business API"', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Crea credenciales OAuth 2.0 y obtén un token de acceso', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Para obtener el Location ID: usa la API de GMB o la herramienta de administración de Google', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'El formato del Location ID es: accounts/{accountId}/locations/{locationId}', 'convoca-publisher' ); ?></li>
				<li><?php echo esc_html__( 'Pega ambos en la pantalla del canal: Canales → Google My Business', 'convoca-publisher' ); ?></li>
			</ol>
			<p><strong><?php echo esc_html__( 'Campos necesarios:', 'convoca-publisher' ); ?></strong> <?php echo esc_html__( 'Access Token (OAuth 2.0), Location ID', 'convoca-publisher' ); ?></p>
			<p><a href="https://developers.google.com/my-business" target="_blank">📄 <?php echo esc_html__( 'Documentación oficial de GMB API', 'convoca-publisher' ); ?></a></p>
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
