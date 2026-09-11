/**
 * Convoca Publisher — mejoras del panel.
 *
 * Todo es mejora progresiva: sin JavaScript la pantalla se sigue usando entera (los
 * formularios envían igual y las confirmaciones no bloquean nada crítico). Antes había
 * un `onclick` en el marcado; ahora el comportamiento vive aquí.
 */
( function () {
	'use strict';

	/** Confirmación antes de una acción destructiva: `data-cp-confirm="¿Seguro?"`. */
	function prepararConfirmaciones() {
		document.addEventListener( 'click', function ( evento ) {
			var enlace = evento.target.closest( '[data-cp-confirm]' );

			if ( ! enlace ) {
				return;
			}

			if ( ! window.confirm( enlace.getAttribute( 'data-cp-confirm' ) ) ) {
				evento.preventDefault();
			}
		} );
	}

	/** Mostrar u ocultar un token: `data-cp-toggle="#id-del-campo"`. */
	function prepararMostrarTokens() {
		document.addEventListener( 'click', function ( evento ) {
			var boton = evento.target.closest( '[data-cp-toggle]' );

			if ( ! boton ) {
				return;
			}

			var campo = document.querySelector( boton.getAttribute( 'data-cp-toggle' ) );

			if ( ! campo ) {
				return;
			}

			var oculto = campo.getAttribute( 'type' ) === 'password';

			campo.setAttribute( 'type', oculto ? 'text' : 'password' );
			boton.setAttribute( 'aria-pressed', oculto ? 'true' : 'false' );
			boton.textContent = oculto
				? boton.getAttribute( 'data-cp-hide' )
				: boton.getAttribute( 'data-cp-show' );
		} );
	}

	/** Copiar al portapapeles: `data-cp-copy="#id-del-campo"`. */
	function prepararCopiar() {
		document.addEventListener( 'click', function ( evento ) {
			var boton = evento.target.closest( '[data-cp-copy]' );

			if ( ! boton || ! navigator.clipboard ) {
				return;
			}

			var campo = document.querySelector( boton.getAttribute( 'data-cp-copy' ) );

			if ( ! campo ) {
				return;
			}

			navigator.clipboard.writeText( campo.value || campo.textContent ).then( function () {
				var texto = boton.textContent;

				boton.textContent = boton.getAttribute( 'data-cp-copied' ) || 'Copiado';
				window.setTimeout( function () {
					boton.textContent = texto;
				}, 1500 );
			} );
		} );
	}

	/** Los canales con moderación solo tienen sentido con el modo «solo canales». */
	function prepararModeracionPorCanal() {
		var modo = document.getElementById( 'convoca_publisher_moderation' );

		if ( ! modo ) {
			return;
		}

		var casillas = document.querySelectorAll( '[data-cp-moderation-channel]' );

		function sincronizar() {
			var activo = modo.value === 'canal';

			casillas.forEach( function ( casilla ) {
				casilla.disabled = ! activo;
				casilla.closest( '[data-cp-moderation-group]' ).classList.toggle( 'cp-is-muted', ! activo );
			} );
		}

		modo.addEventListener( 'change', sincronizar );
		sincronizar();
	}

	/**
	 * Arrastrar un envío del calendario a otro día.
	 *
	 * Sin JavaScript el calendario se sigue usando: cada envío se puede cambiar de hora
	 * desde la lista de la cola (formulario normal).
	 */
	function prepararCalendario() {
		var arrastrado = null;

		document.addEventListener( 'dragstart', function ( evento ) {
			var envio = evento.target.closest( '[data-cp-envio]' );

			if ( ! envio ) {
				return;
			}

			arrastrado = envio;
			envio.classList.add( 'cp-cal__envio--arrastrando' );
			evento.dataTransfer.setData( 'text/plain', envio.getAttribute( 'data-cp-envio' ) );
			evento.dataTransfer.effectAllowed = 'move';
		} );

		document.addEventListener( 'dragend', function () {
			if ( arrastrado ) {
				arrastrado.classList.remove( 'cp-cal__envio--arrastrando' );
			}
			document.querySelectorAll( '.cp-cal__drop' ).forEach( function ( celda ) {
				celda.classList.remove( 'cp-cal__drop' );
			} );
			arrastrado = null;
		} );

		document.addEventListener( 'dragover', function ( evento ) {
			var celda = evento.target.closest( '[data-cp-dia]' );

			if ( ! celda || ! arrastrado ) {
				return;
			}

			evento.preventDefault();
			celda.classList.add( 'cp-cal__drop' );
		} );

		document.addEventListener( 'dragleave', function ( evento ) {
			var celda = evento.target.closest( '[data-cp-dia]' );

			if ( celda ) {
				celda.classList.remove( 'cp-cal__drop' );
			}
		} );

		document.addEventListener( 'drop', function ( evento ) {
			var celda = evento.target.closest( '[data-cp-dia]' );

			if ( ! celda || ! arrastrado ) {
				return;
			}

			evento.preventDefault();

			var formulario = document.getElementById( 'cp-cal-form' );

			if ( ! formulario ) {
				return;
			}

			formulario.querySelector( '[name="cp_envio"]' ).value = arrastrado.getAttribute( 'data-cp-envio' );
			formulario.querySelector( '[name="cp_dia"]' ).value = celda.getAttribute( 'data-cp-dia' );
			formulario.submit();
		} );
	}

	/**
	 * Plantillas: insertar variables donde esté el cursor, volver a la de fábrica y ver la
	 * vista previa con el contador de la red.
	 *
	 * El contador y el recorte los calcula el servidor con las reglas de cada red (los
	 * enlaces no ocupan lo mismo que el texto). Hacerlo aquí con un `length` sería tener dos
	 * verdades: la pantalla diría que cabe y el envío lo recortaría.
	 */
	function prepararPlantillas() {
		var entrada = document.getElementById( 'cp-plantilla-entrada' );
		var i18n    = ( window.convocaPublisher && window.convocaPublisher.i18n ) || {};
		var temporizador = null;

		function pintar( panel ) {
			var campo = document.querySelector( panel.getAttribute( 'data-cp-into' ) );

			if ( ! campo || ! window.convocaPublisher ) {
				return;
			}

			var datos = new window.FormData();
			datos.append( 'action', 'cp_preview_template' );
			datos.append( '_wpnonce', window.convocaPublisher.nonce );
			datos.append( 'post_id', entrada ? entrada.value : '0' );
			datos.append( 'network', panel.getAttribute( 'data-cp-network' ) );
			datos.append( 'template', campo.value );

			window.fetch( window.convocaPublisher.ajaxUrl, {
				method: 'POST',
				credentials: 'same-origin',
				body: datos
			} )
				.then( function ( respuesta ) { return respuesta.json(); } )
				.then( function ( datos ) {
					if ( ! datos || datos.error ) {
						panel.textContent = i18n.error || '';
						return;
					}

					var aviso = datos.recortado
						? ( i18n.pasado || '%s' ).replace( '%s', String( datos.count - datos.limit ) )
						: ( i18n.quedan || '%s' ).replace( '%s', String( datos.restante ) );

					panel.classList.toggle( 'cp-preview--pasado', !! datos.recortado );
					panel.textContent = '';

					var texto = document.createElement( 'p' );
					texto.className = 'cp-preview-mensaje';
					texto.textContent = datos.message;

					var cuenta = document.createElement( 'p' );
					cuenta.className = 'cp-preview-cuenta';
					cuenta.textContent = aviso;

					panel.appendChild( texto );
					panel.appendChild( cuenta );
				} )
				.catch( function () {
					panel.textContent = i18n.error || '';
				} );
		}

		function pintarTodo() {
			document.querySelectorAll( '[data-cp-preview]' ).forEach( pintar );
		}

		document.addEventListener( 'click', function ( evento ) {
			var insertar = evento.target.closest( '[data-cp-insert]' );

			if ( insertar ) {
				evento.preventDefault();
				var destino = document.querySelector( insertar.getAttribute( 'data-cp-into' ) );
				var variable = insertar.getAttribute( 'data-cp-insert' );

				if ( destino ) {
					var desde = destino.selectionStart;
					var hasta = destino.selectionEnd;
					destino.value = destino.value.slice( 0, desde ) + variable + destino.value.slice( hasta );
					destino.selectionStart = destino.selectionEnd = desde + variable.length;
					destino.focus();
				}

				return;
			}

			var volver = evento.target.closest( '[data-cp-reset]' );

			if ( volver ) {
				evento.preventDefault();
				var campo = document.querySelector( volver.getAttribute( 'data-cp-reset' ) );

				if ( campo ) {
					campo.value = volver.getAttribute( 'data-cp-factory' );
					campo.focus();
				}
			}
		} );

		document.addEventListener( 'input', function ( evento ) {
			if ( ! evento.target.closest( '.cp-input' ) ) {
				return;
			}

			window.clearTimeout( temporizador );
			temporizador = window.setTimeout( pintarTodo, 400 );
		} );

		if ( entrada ) {
			entrada.addEventListener( 'change', pintarTodo );
		}

		pintarTodo();
	}

	document.addEventListener( 'DOMContentLoaded', function () {
		prepararConfirmaciones();
		prepararCalendario();
		prepararMostrarTokens();
		prepararCopiar();
		prepararModeracionPorCanal();
		prepararPlantillas();
	} );
}() );
