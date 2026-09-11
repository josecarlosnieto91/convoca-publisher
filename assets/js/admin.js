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

	document.addEventListener( 'DOMContentLoaded', function () {
		prepararConfirmaciones();
		prepararCalendario();
		prepararMostrarTokens();
		prepararCopiar();
		prepararModeracionPorCanal();
	} );
}() );
