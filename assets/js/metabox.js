/**
 * Convoca Publisher — mensaje de esta entrada, en el editor.
 *
 * Mejora progresiva: los contadores y las vistas previas ya vienen calculados del servidor
 * (los pinta PHP), así que si esto no carga se sigue viendo lo que va a salir. Lo que añade
 * este fichero es que se actualicen mientras se escribe, antes de guardar.
 *
 * El recuento de aquí es una estimación para la vista previa: el que manda al enviar es el
 * del servidor (Platform_Rules), que además recorta lo que no cabe.
 */
( function () {
	'use strict';

	var datos = window.convocaPublisherMessage;

	if ( ! datos ) {
		return;
	}

	var campo = document.getElementById( 'convoca-publisher-message' );
	var salidas = document.querySelectorAll( '[data-cp-resumen]' );

	if ( ! campo || ! salidas.length ) {
		return;
	}

	/**
	 * Caracteres que ocupa un enlace en esa red (X cuenta cualquiera como 23).
	 */
	function contar( texto, peso ) {
		var largo = Array.from( texto ).length;

		if ( ! peso ) {
			return largo;
		}

		var enlaces = texto.match( /https?:\/\/[^\s<>"']+/gi ) || [];

		enlaces.forEach( function ( enlace ) {
			largo += peso - Array.from( enlace ).length;
		} );

		return largo;
	}

	function sustituir( plantilla, valores ) {
		return plantilla.replace( /\{(title|excerpt|url|permalink|hashtags|date|author|featured_image)\}/g, function ( todo, clave ) {
			return valores[ clave ] !== undefined ? valores[ clave ] : todo;
		} );
	}

	function actualizar() {
		var texto = campo.value.trim();

		salidas.forEach( function ( salida ) {
			var red = salida.getAttribute( 'data-cp-red' );
			var reglas = datos.redes[ red ] || datos.redes.generico;
			var plantilla = texto !== '' ? texto : salida.getAttribute( 'data-cp-plantilla' );
			var mensaje = sustituir( plantilla, datos.valores );
			var cuenta = contar( mensaje, reglas.url_weight );
			var contador = salida.querySelector( '[data-cp-contador]' );
			var previa = salida.querySelector( '[data-cp-previa]' );
			var aviso = salida.querySelector( '[data-cp-aviso]' );

			if ( previa ) {
				previa.textContent = mensaje;
			}

			if ( contador ) {
				contador.textContent = cuenta + ' / ' + reglas.chars;

				contador.className = cuenta > reglas.chars
					? 'cp-contador cp-contador--pasado'
					: ( cuenta > reglas.chars * 0.9 ? 'cp-contador cp-contador--justo' : 'cp-contador' );
			}

			if ( aviso ) {
				if ( cuenta > reglas.chars ) {
					aviso.textContent = datos.textos.recorta.replace( '%d', cuenta - reglas.chars );
					aviso.hidden = false;
				} else {
					aviso.hidden = true;
				}
			}
		} );
	}

	campo.addEventListener( 'input', actualizar );
	actualizar();

	// Compartir en una cuenta concreta, sin salir del editor.
	document.querySelectorAll( '.cp-compartir' ).forEach( function ( boton ) {
		boton.addEventListener( 'click', function () {
			var original = boton.textContent;

			boton.disabled = true;
			boton.textContent = datos.compartir.enviando || '…';

			var cuerpo = new URLSearchParams();
			cuerpo.set( 'action', 'cp_share' );
			cuerpo.set( 'post_id', boton.getAttribute( 'data-post-id' ) );
			cuerpo.set( 'cuenta', boton.getAttribute( 'data-cuenta' ) );
			cuerpo.set( '_wpnonce', datos.compartir.nonce );

			fetch( datos.compartir.ajax, {
				method: 'POST',
				credentials: 'same-origin',
				headers: { 'Content-Type': 'application/x-www-form-urlencoded' },
				body: cuerpo.toString()
			} )
				.then( function ( respuesta ) {
					return respuesta.json();
				} )
				.then( function ( json ) {
					if ( json && json.success ) {
						boton.textContent = datos.compartir.texto;
						window.setTimeout( function () {
							window.location.reload();
						}, 900 );

						return;
					}

					boton.disabled = false;
					boton.textContent = original;
					window.alert( ( json && json.data && json.data.message ) || 'Error' );
				} )
				.catch( function () {
					boton.disabled = false;
					boton.textContent = original;
				} );
		} );
	} );
}() );
