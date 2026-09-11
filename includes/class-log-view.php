<?php

/**
 * Vista del historial: filtros y qué se puede reintentar.
 *
 * Pieza pura: entra la lista de filas del historial y sale lo que se pinta. Sin consultar
 * opciones ni tocar la base de datos, para poder probarla sin WordPress delante.
 *
 * Una fila del historial es un array con, como poco:
 *   success (bool), channel (id de la cuenta que envió), post_id (int), title (string), time.
 *
 * @package ConvocaPublisher
 */

namespace ConvocaPublisher\Admin;

if (!defined('ABSPATH')) {
    exit;
}

/**
 * Filtros y utilidades del historial de publicaciones.
 */
class Log_View
{
    /**
     * Canales que no son envíos y por tanto no se reintentan (filas del propio plugin:
     * validaciones, avisos internos).
     */
    private const NOT_A_CHANNEL = ['VALIDACIÓN', 'VALIDACION', 'SISTEMA'];

    /**
     * Filtra las filas del historial.
     *
     * Los filtros vacíos no filtran. Un filtro de red que no corresponde a ninguna fila
     * deja la lista vacía, que es lo que se espera al pedir algo que no hay.
     *
     * @param array<int, mixed>     $entries Filas del historial (lo que haya guardado: se comprueba).
     * @param array<string, mixed>  $filters network, account, status, networks.
     * @return array<int, mixed>
     */
    public static function filter(array $entries, array $filters): array
    {
        $network = (string) ($filters['network'] ?? '');
        $account = (string) ($filters['account'] ?? '');
        $status  = (string) ($filters['status'] ?? '');

        $out = [];

        foreach ($entries as $entry) {
            if (!is_array($entry) || !self::is_entry($entry)) {
                continue;
            }

            if ('' !== $status && self::status($entry) !== $status) {
                continue;
            }

            if ('' !== $account && (string) ($entry['channel'] ?? '') !== $account) {
                continue;
            }

            if ('' !== $network && self::network_of((string) ($entry['channel'] ?? ''), $filters) !== $network) {
                continue;
            }

            $out[] = $entry;
        }

        return $out;
    }

    /**
     * ¿Es una fila del historial o basura pegada en la opción?
     *
     * Se exige un canal: sin él no hay envío que enseñar, ni cuenta que resolver, ni nada
     * que reintentar.
     *
     * @param array<string, mixed> $entry Fila del historial.
     */
    public static function is_entry(array $entry): bool
    {
        return '' !== (string) ($entry['channel'] ?? '');
    }

    /**
     * Estado de una fila, en una palabra.
     *
     * @param array<string, mixed> $entry Fila del historial.
     */
    public static function status(array $entry): string
    {
        return !empty($entry['success']) ? 'ok' : 'fail';
    }

    /**
     * Red a la que pertenece una fila.
     *
     * El historial guarda el **id de la cuenta**, no la red. La correspondencia se pasa en
     * `$filters['networks']` (id de cuenta => id de red) porque quien llama es el único que
     * conoce el catálogo de cuentas del sitio. Si no aparece, se usa el propio id: una cuenta
     * borrada sigue teniendo historial, y ese historial no debe desaparecer de la vista.
     *
     * @param string               $channel Id de la cuenta.
     * @param array<string, mixed> $filters Filtros (puede traer 'networks').
     */
    public static function network_of(string $channel, array $filters): string
    {
        $map = (array) ($filters['networks'] ?? []);

        return isset($map[$channel]) ? (string) $map[$channel] : $channel;
    }

    /**
     * Lo que se puede ofrecer en los desplegables: solo lo que hay en el historial.
     *
     * @param array<int, mixed>    $entries Filas del historial.
     * @param array<string, string> $networks id de cuenta => id de red.
     * @return array{networks: list<string>, accounts: array<string, string>, statuses: array<string, int>, total: int}
     */
    public static function facets(array $entries, array $networks = []): array
    {
        $nets     = [];
        $accounts = [];
        $statuses = ['ok' => 0, 'fail' => 0];
        $total    = 0;

        foreach ($entries as $entry) {
            if (!is_array($entry) || !self::is_entry($entry)) {
                continue;
            }

            ++$total;

            $channel = (string) $entry['channel'];
            $estado  = self::status($entry);

            $statuses[$estado] = ($statuses[$estado] ?? 0) + 1;

            if (in_array($channel, self::NOT_A_CHANNEL, true)) {
                continue; // Avisos del propio plugin: cuentan, pero no son red ni cuenta.
            }

            $accounts[$channel] = self::label($channel, $networks);
            $nets[self::network_of($channel, ['networks' => $networks])] = true;
        }

        ksort($nets);

        return [
            'networks' => array_keys($nets),
            'accounts' => $accounts,
            'statuses' => $statuses,
            'total'    => $total,
        ];
    }

    /**
     * ¿Se puede volver a intentar esta fila?
     *
     * Solo los envíos que fallaron, de una entrada que siga existiendo y con una cuenta
     * identificada. Los avisos internos del plugin no son envíos y no se reintentan.
     *
     * @param array<string, mixed> $entry Fila del historial.
     */
    public static function retryable(array $entry): bool
    {
        if (empty($entry['success']) === false) {
            return false; // Ya salió.
        }

        $channel = (string) ($entry['channel'] ?? '');

        if ('' === $channel || in_array($channel, self::NOT_A_CHANNEL, true)) {
            return false;
        }

        return ((int) ($entry['post_id'] ?? 0)) > 0;
    }

    /**
     * Nombre para enseñar de una cuenta, con su red si se conoce.
     *
     * @param string                $channel  Id de la cuenta.
     * @param array<string, string> $networks id de cuenta => id de red.
     * @param array<string, string> $names    id de cuenta => nombre visible.
     */
    public static function label(string $channel, array $networks = [], array $names = []): string
    {
        if ('' === $channel) {
            return '';
        }

        $nombre = $names[$channel] ?? $channel;
        $red    = $networks[$channel] ?? '';

        if ('' === $red || $nombre === $red) {
            return $nombre;
        }

        return $red . ' · ' . $nombre;
    }
}
