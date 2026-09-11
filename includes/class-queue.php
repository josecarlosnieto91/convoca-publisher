<?php

/**
 * Convoca Publisher — la cola: qué sale, cuándo y en qué cuenta.
 *
 * @package    Convoca\Publisher
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

/**
 * La cola de envíos, vista como una sola cosa.
 *
 * Junta las tres fuentes que antes vivían sueltas:
 *  · los posts con hora de envío programada (`_convoca_publisher_schedule_time`), uno por
 *    cada cuenta que los va a publicar;
 *  · la cola de reintentos (tabla propia, estados `pending`, `processing` y `failed`);
 *  · lo que ya salió, con su resultado (el historial).
 *
 * Y aplica el **espaciado**: un intervalo mínimo entre envíos para no soltar cinco
 * publicaciones en el mismo minuto. Los envíos que caen dentro del intervalo se
 * recolocan solos, y la publicación programada también lo respeta (publica el más
 * antiguo y deja el resto para cuando toque).
 */
final class Queue
{
    /** Opción con el intervalo mínimo entre envíos, en segundos. */
    public const INTERVAL_OPTION = 'convoca_publisher_queue_interval';

    /** Opción con la marca del último envío programado. */
    public const LAST_SENT_OPTION = 'convoca_publisher_queue_last_sent';

    /** Intervalo por defecto: media hora. */
    public const DEFAULT_INTERVAL = 1800;

    /** Meta con la hora de envío de un post. */
    public const SCHEDULE_META = '_convoca_publisher_schedule_time';

    /** Cuántos envíos programados se miran como mucho de una vez. */
    private const LIMIT = 200;

    /**
     * Intentos que se le dan a un programado antes de dejar de insistir y pedir una mano.
     */
    public const ATTEMPTS_META = '_convoca_publisher_schedule_attempts';

    public const HELP_META = '_convoca_publisher_needs_help';

    public const MAX_ATTEMPTS = 5;

    /**
     * Intervalo mínimo entre envíos, en segundos (0 = sin espaciado).
     */
    public static function interval(): int
    {
        $valor = (int) get_option(self::INTERVAL_OPTION, self::DEFAULT_INTERVAL);

        return max(0, min(DAY_IN_SECONDS, $valor));
    }

    /**
     * Guardar el intervalo.
     */
    public static function save_interval(int $seconds): int
    {
        $seconds = self::clamp_interval($seconds);
        update_option(self::INTERVAL_OPTION, $seconds, false);

        return $seconds;
    }

    /**
     * El intervalo, dentro de lo razonable (de 0 a un día).
     *
     * Vive aquí y no en el saneador para que el recorte sea el mismo se escriba desde donde se
     * escriba: el saneador de la pantalla y una escritura programática tienen que coincidir.
     */
    public static function clamp_interval(int $seconds): int
    {
        return max(0, min(DAY_IN_SECONDS, $seconds));
    }

    /**
     * Envíos programados entre dos momentos.
     *
     * @return array<int, array{kind: string, key: string, post_id: int, title: string, account: string, account_name: string, network: string, time: int}>
     */
    public static function scheduled_entries(int $from, int $to): array
    {
        $posts = get_posts([
            'post_type'      => 'post',
            'post_status'    => ['publish', 'future', 'draft', 'pending', 'private'],
            'posts_per_page' => self::LIMIT,
            'fields'         => 'ids',
            'meta_query'     => [
                [
                    'key'     => self::SCHEDULE_META,
                    'value'   => [$from, $to],
                    'compare' => 'BETWEEN',
                    'type'    => 'NUMERIC',
                ],
            ],
        ]);

        $entries = [];

        foreach ($posts as $post_id) {
            $post_id = (int) $post_id;
            $cuando  = (int) get_post_meta($post_id, self::SCHEDULE_META, true);

            if ($cuando <= 0) {
                continue;
            }

            foreach (self::accounts_for_post($post_id) as $account) {
                $entries[] = [
                    'kind'         => 'schedule',
                    'key'          => 'schedule-' . $post_id,
                    'post_id'      => $post_id,
                    'title'        => (string) get_the_title($post_id),
                    'account'      => $account->get_id(),
                    'account_name' => $account->get_name(),
                    'network'      => $account instanceof Channel_Profile ? $account->get_channel_id() : $account->get_id(),
                    'time'         => $cuando,
                ];
            }
        }

        return $entries;
    }

    /**
     * Programados que ya deberían haber salido y no han salido.
     *
     * Es el hueco que no cubre el reintento normal: el cron no llegó a intentarlo (WordPress
     * lo dispara el tráfico y en un sitio tranquilo puede tardar), el sitio estuvo caído o el
     * espaciado los dejó atrás. Un programado que ya pasó de hora y sigue sin publicarse
     * aparece aquí, que es donde alguien puede verlo.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function overdue(int $limit = 20, ?int $now = null): array
    {
        $now ??= time();
        $tarde = self::scheduled_entries(0, max(0, $now - MINUTE_IN_SECONDS));
        $vistos = [];

        foreach ($tarde as $entrada) {
            $post_id = (int) $entrada['post_id'];

            // Una fila por entrada (no por cuenta): la decisión de «esto se quedó atrás» es de
            // la entrada, y desde aquí se le da salida a todas sus cuentas de una vez.
            if (isset($vistos[$post_id]) || (int) $entrada['time'] > $now - MINUTE_IN_SECONDS) {
                continue;
            }

            if (get_post_meta($post_id, '_convoca_publisher_published', true)) {
                continue;
            }

            $vistos[$post_id]   = true;
            $entrada['attempts'] = self::attempts($post_id);
            $atrasados[]         = $entrada;
        }

        return array_slice($atrasados ?? [], 0, $limit);
    }

    /**
     * Los que ya agotaron los intentos: no se insiste más, y se pide una mano.
     *
     * @return int[]
     */
    public static function needs_help(): array
    {
        $ids = get_posts([
            'post_type'      => 'post',
            'post_status'    => 'publish',
            'posts_per_page' => 50,
            'fields'         => 'ids',
        ]);

        // Se comprueba aquí y no en la consulta: la marca es lo que decide, y así no depende
        // de cómo la interprete la consulta que se monte en cada sitio.
        return array_values(array_filter(
            array_map('intval', $ids),
            static fn(int $post_id): bool => '' !== (string) get_post_meta($post_id, self::HELP_META, true)
        ));
    }

    public static function attempts(int $post_id): int
    {
        return (int) get_post_meta($post_id, self::ATTEMPTS_META, true);
    }

    /**
     * Un intento más. Devuelve los que lleva.
     */
    public static function count_attempt(int $post_id): int
    {
        $intentos = self::attempts($post_id) + 1;
        update_post_meta($post_id, self::ATTEMPTS_META, $intentos);

        return $intentos;
    }

    /**
     * Dejar de insistir: se quita la marca (para que el cron no vuelva cada cuarto de hora)
     * pero queda anotado para que la cola lo enseñe.
     */
    public static function give_up(int $post_id): void
    {
        delete_post_meta($post_id, self::SCHEDULE_META);
        update_post_meta($post_id, self::HELP_META, time());
    }

    /**
     * Vuelta a la normalidad: sin intentos acumulados y sin marca de auxilio.
     */
    public static function forget_attempts(int $post_id): void
    {
        delete_post_meta($post_id, self::ATTEMPTS_META);
        delete_post_meta($post_id, self::HELP_META);
    }

    /**
     * Cuentas que van a publicar una entrada.
     *
     * Lo usan la cola (para saber qué va a salir) y el publicador (para saber a quién
     * enviar): es la misma regla, en un solo sitio.
     *
     * @param array<string, Channels\ChannelInterface>|null $accounts Cuentas a filtrar.
     *                                                               Por defecto, las del sitio.
     *
     * @return array<string, Channels\ChannelInterface>
     */
    public static function accounts_for_post(int $post_id, ?array $accounts = null): array
    {
        $desactivadas = (array) get_post_meta($post_id, '_convoca_publisher_disabled_channels', true);

        // La misma regla para la cola y para el publicador: desmarcada en el editor o sin
        // credenciales, esa cuenta no recibe la entrada.
        return array_filter(
            $accounts ?? convoca_publisher()->get_channels(),
            static fn(object $account): bool => !in_array($account->get_id(), $desactivadas, true)
                && $account->is_available()
        );
    }

    /**
     * Envíos que esperan turno o que fallaron (cola de reintentos).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function retry_entries(int $limit = 100): array
    {
        global $wpdb;

        $tabla = Retry::table_name();
        $rows  = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$tabla} WHERE status IN (%s, %s, %s) ORDER BY next_attempt ASC, id ASC LIMIT %d",
                Retry::STATUS_PENDING,
                Retry::STATUS_PROCESSING,
                Retry::STATUS_FAILED,
                $limit
            )
        );

        $entries = [];

        foreach ((array) $rows as $row) {
            $when = !empty($row->next_attempt) ? (int) strtotime((string) $row->next_attempt . ' UTC') : 0;
            $account = convoca_publisher()->get_channel((string) $row->channel);

            $entries[] = [
                'kind'         => 'retry',
                'id'           => (int) $row->id,
                'post_id'      => (int) $row->post_id,
                'title'        => (string) get_the_title((int) $row->post_id),
                'account'      => (string) $row->channel,
                'account_name' => $account ? $account->get_name() : (string) $row->channel,
                'network'      => $account instanceof Channel_Profile ? $account->get_channel_id() : (string) $row->channel,
                'status'       => (string) $row->status,
                'attempts'     => (int) $row->attempts,
                'error'        => (string) ($row->error_text ?? ''),
                'time'         => $when,
            ];
        }

        return $entries;
    }

    /**
     * Lo que ya salió (con su resultado), del historial.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function sent_entries(int $limit = 50): array
    {
        $log     = (array) get_option('convoca_publisher_publish_log', []);
        $entries = [];

        foreach (array_reverse($log) as $entry) {
            if (count($entries) >= $limit) {
                break;
            }

            // Las filas de validación no son envíos: son avisos del propio plugin («no hay
            // imagen destacada»). En una lista de «lo último que salió» se leerían como un
            // fallo de la red, y no lo son.
            if ('VALIDACIÓN' === (string) ($entry['channel'] ?? '')) {
                continue;
            }

            $entries[] = [
                'title'   => (string) ($entry['title'] ?? ''),
                'account' => (string) ($entry['channel'] ?? ''),
                'success' => !empty($entry['success']),
                'time'    => (string) ($entry['time'] ?? ''),
                'detail'  => (string) ($entry['response'] ?? ''),
            ];
        }

        return $entries;
    }

    /**
     * Recolocar los envíos para que guarden el intervalo mínimo.
     *
     * Función pura (sin tocar la base de datos): recibe la lista ya ordenada o no, la
     * ordena por hora y adelanta lo que caiga dentro del intervalo del envío anterior.
     * Es la regla que hace que no salgan cinco publicaciones seguidas, y por eso se
     * prueba sola.
     *
     * @param array<int, array<string, mixed>> $entries
     *
     * @return array<int, array<string, mixed>> Las mismas entradas, con `time` recolocado
     *                                          y `moved` a `true` si se movió.
     */
    public static function spaced(array $entries, int $interval): array
    {
        usort($entries, static fn(array $a, array $b): int => ($a['time'] ?? 0) <=> ($b['time'] ?? 0));

        $anterior = null;

        foreach ($entries as $i => $entry) {
            $entry['moved'] = false;
            $when           = (int) ($entry['time'] ?? 0);

            if (null !== $anterior && $interval > 0 && $when < $anterior + $interval) {
                $when           = $anterior + $interval;
                $entry['time']  = $when;
                $entry['moved'] = true;
            }

            $anterior     = $when;
            $entries[$i]  = $entry;
        }

        return $entries;
    }

    /**
     * Recolocar y guardar: los posts movidos cambian su hora, y los reintentos también.
     *
     * @return int Cuántos envíos se han recolocado.
     */
    public static function apply_spacing(): int
    {
        $interval = self::interval();

        if ($interval <= 0) {
            return 0;
        }

        $ahora    = time();
        $entries  = array_merge(
            self::scheduled_entries($ahora - DAY_IN_SECONDS, $ahora + (90 * DAY_IN_SECONDS)),
            self::retry_entries()
        );

        $movidos = 0;

        foreach (self::spaced($entries, $interval) as $entry) {
            if (empty($entry['moved'])) {
                continue;
            }

            ++$movidos;

            if ('schedule' === $entry['kind']) {
                update_post_meta((int) $entry['post_id'], self::SCHEDULE_META, (int) $entry['time']);
                continue;
            }

            self::reschedule_retry((int) $entry['id'], (int) $entry['time']);
        }

        return $movidos;
    }

    /**
     * ¿Se puede publicar algo programado ahora mismo, según el espaciado?
     *
     * Si el último envío programado fue hace menos que el intervalo, toca esperar.
     */
    public static function can_publish_now(?int $now = null): bool
    {
        $interval = self::interval();

        if ($interval <= 0) {
            return true;
        }

        $ultimo = (int) get_option(self::LAST_SENT_OPTION, 0);

        if ($ultimo <= 0) {
            return true;
        }

        return ($now ?? time()) >= $ultimo + $interval;
    }

    /**
     * Anotar que se acaba de publicar algo programado.
     */
    public static function mark_published(?int $when = null): void
    {
        update_option(self::LAST_SENT_OPTION, $when ?? time(), false);
    }

    /**
     * Cambiar la hora de envío de un post.
     */
    public static function reschedule_schedule(int $post_id, int $when): bool
    {
        if ($post_id <= 0 || $when <= 0) {
            return false;
        }

        update_post_meta($post_id, self::SCHEDULE_META, $when);

        return true;
    }

    /**
     * Quitar de la cola el envío programado de un post (la entrada se queda como está).
     */
    public static function cancel_schedule(int $post_id): bool
    {
        return (bool) delete_post_meta($post_id, self::SCHEDULE_META);
    }

    /**
     * Cambiar la hora de un reintento.
     */
    public static function reschedule_retry(int $id, int $when): bool
    {
        global $wpdb;

        return (bool) $wpdb->update(
            Retry::table_name(),
            ['next_attempt' => gmdate('Y-m-d H:i:s', $when)],
            ['id' => $id]
        );
    }

    /**
     * Quitar un reintento de la cola.
     */
    public static function cancel_retry(int $id): bool
    {
        global $wpdb;

        return (bool) $wpdb->delete(Retry::table_name(), ['id' => $id]);
    }
}
