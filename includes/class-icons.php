<?php

/**
 * Los iconos de las redes, dibujados aquí.
 *
 * Por qué SVG propio y no otra cosa:
 *
 * - **Nada de peticiones externas**: un icono que se descarga de un CDN deja de verse cuando el
 *   servidor no tiene salida, y en el panel de administración eso es una pantalla rota.
 * - **`currentColor`**: el icono hereda el color del texto, así que funciona igual en el modo
 *   claro y en el oscuro sin una sola regla más.
 * - **Marcas simples, no los logotipos oficiales**: representan la red y se reconocen, pero son
 *   dibujos propios. Los logotipos de las marcas tienen sus normas de uso y no hacen falta para
 *   esto.
 *
 * Son decorativos: el nombre de la red va siempre al lado, así que van ocultos a los lectores
 * de pantalla (`aria-hidden`) para no repetir la misma información dos veces.
 */

namespace ConvocaPublisher;

final class Icons
{
    /**
     * El dibujo de cada red, en un lienzo de 24×24.
     *
     * @return array<string, string>
     */
    private static function drawings(): array
    {
        return [
            // Dos barras cruzadas.
            'twitter' => '<path d="M4 4 L20 20 M20 4 L4 20" fill="none" stroke="currentColor" stroke-width="2.6" stroke-linecap="round"/>',

            // Cuadrado redondeado con la efe.
            'facebook' => '<rect x="3" y="3" width="18" height="18" rx="4.5" fill="none" stroke="currentColor" stroke-width="2"/>'
                . '<path d="M14.2 8.2h-1.5a1.6 1.6 0 0 0-1.6 1.6v2h-1.7v2.2h1.7v6h2.4v-6h1.9l.4-2.2h-2.3v-1.5a.6.6 0 0 1 .6-.6h1.7z" fill="currentColor"/>',

            // Cuadro redondeado, círculo y puntito: la cámara.
            'instagram' => '<rect x="3" y="3" width="18" height="18" rx="5" fill="none" stroke="currentColor" stroke-width="2"/>'
                . '<circle cx="12" cy="12" r="4.2" fill="none" stroke="currentColor" stroke-width="2"/>'
                . '<circle cx="17.2" cy="6.9" r="1.2" fill="currentColor"/>',

            // El avión de papel.
            'telegram' => '<path d="M21.6 4.3 2.9 11.4c-.9.3-.9 1.5.1 1.7l4.3 1.2 1.6 4.6c.3.8 1.4.9 1.8.1l2.2-3.9 4.2 3.1c.6.5 1.5.1 1.7-.7l2.7-12.1c.2-.9-.8-1.6-1.7-1.3z" fill="currentColor"/>',

            // Cuadrado con la eñe y el punto.
            'linkedin' => '<rect x="3" y="3" width="18" height="18" rx="3" fill="none" stroke="currentColor" stroke-width="2"/>'
                . '<circle cx="8" cy="8.2" r="1.3" fill="currentColor"/>'
                . '<rect x="6.9" y="10.6" width="2.2" height="6.8" fill="currentColor"/>'
                . '<path d="M11.9 17.4v-6.8h2.2v.9a2.9 2.9 0 0 1 4.9 2v4z" fill="currentColor"/>',

            // La chincheta del mapa.
            'googlemybusiness' => '<path d="M12 2.6a6.8 6.8 0 0 1 6.8 6.8c0 4.9-6.8 12-6.8 12s-6.8-7.1-6.8-12A6.8 6.8 0 0 1 12 2.6z" fill="none" stroke="currentColor" stroke-width="2"/>'
                . '<circle cx="12" cy="9.3" r="2.5" fill="currentColor"/>',

            // La eme de tres arcos.
            'mastodon' => '<path d="M4.6 17.2V9.9a4.6 4.6 0 0 1 4.6-4.6h5.6a4.6 4.6 0 0 1 4.6 4.6v7.3h-2.6V9.9a1.6 1.6 0 0 0-1.6-1.6h-1.9v8.9h-2.6V8.3h-1.9a1.6 1.6 0 0 0-1.6 1.6v7.3z" fill="currentColor"/>',

            // La nota musical.
            'tiktok' => '<path d="M13.6 3.2v9.6a3.3 3.3 0 1 1-2.7-3.2" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>'
                . '<path d="M13.6 3.2c.5 2.7 2.3 4.4 5 4.8" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round"/>',

            // Y por si mañana entra una red nueva: un mundo, que no desentona.
            'generic' => '<circle cx="12" cy="12" r="9" fill="none" stroke="currentColor" stroke-width="2"/>'
                . '<path d="M3.2 12h17.6M12 3.2a14.5 14.5 0 0 1 0 17.6 14.5 14.5 0 0 1 0-17.6" fill="none" stroke="currentColor" stroke-width="2"/>',
        ];
    }

    /**
     * El icono de una red, listo para imprimir.
     *
     * Nunca devuelve vacío: una red sin dibujo propio recibe el genérico, porque un hueco en la
     * pantalla se lee como un fallo del plugin.
     */
    public static function svg(string $network_id, int $size = 22): string
    {
        $dibujos = self::drawings();
        $clave   = isset($dibujos[$network_id]) ? $network_id : 'generic';

        return sprintf(
            '<svg class="cp-red-svg" width="%1$d" height="%1$d" viewBox="0 0 24 24" aria-hidden="true" focusable="false">%2$s</svg>',
            $size,
            $dibujos[$clave]
        );
    }

    /**
     * Las redes con dibujo propio (para poder comprobarlo en las pruebas).
     *
     * @return array<int, string>
     */
    public static function networks(): array
    {
        return array_values(array_diff(array_keys(self::drawings()), ['generic']));
    }
}
