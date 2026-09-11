<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 */

namespace ConvocaPublisher;

defined('ABSPATH') || exit;

/**
 * El widget del escritorio: lo que va a salir, lo que se ha atascado y lo último que salió.
 *
 * Es lo que se ve sin entrar a buscar nada. Si hay algo parado, se nota aquí; si todo va bien,
 * se ve en una línea y no estorba.
 */
class Dashboard
{
    public const WIDGET_ID = 'convoca_publisher_dashboard';

    public static function init(): void
    {
        add_action('wp_dashboard_setup', [self::class, 'register']);
    }

    public static function register(): void
    {
        if (!current_user_can('manage_options')) {
            return;
        }

        wp_add_dashboard_widget(
            self::WIDGET_ID,
            __('Convoca Publisher', 'convoca-publisher'),
            [self::class, 'render']
        );
    }

    /**
     * Los siguientes envíos programados (lo que saldrá en los próximos días).
     *
     * @return array<int, array<string, mixed>>
     */
    public static function next_up(int $limit = 5, ?int $now = null): array
    {
        $ahora = $now ?? time();
        $futuros = Queue::scheduled_entries($ahora, $ahora + (7 * DAY_IN_SECONDS));

        foreach (Queue::retry_entries() as $reintento) {
            if (($reintento['time'] ?? 0) >= $ahora) {
                $futuros[] = $reintento;
            }
        }

        usort($futuros, static fn(array $a, array $b): int => ($a['time'] ?? 0) <=> ($b['time'] ?? 0));

        // Un mismo envío puede estar programado y además tener un reintento vivo (la red falló
        // y el reintento sigue pendiente): es el mismo envío y no puede salir dos veces.
        $vistos = [];
        $unicos = [];

        foreach ($futuros as $entrada) {
            $clave = ($entrada['post_id'] ?? 0) . '|' . ($entrada['account'] ?? '');

            if (isset($vistos[$clave])) {
                continue;
            }

            $vistos[$clave] = true;
            $unicos[]       = $entrada;
        }

        return array_slice($unicos, 0, $limit);
    }

    /**
     * Lo que necesita una mano: lo atrasado y lo que ya agotó los intentos.
     *
     * @return array<int, array<string, mixed>>
     */
    public static function stuck(int $limit = 5): array
    {
        $parados = Queue::overdue();

        foreach (Queue::needs_help() as $post_id) {
            $parados[] = [
                'post_id' => $post_id,
                'time'    => (int) get_post_meta($post_id, Queue::HELP_META, true),
                'title'   => get_the_title($post_id),
            ];
        }

        return array_slice($parados, 0, $limit);
    }

    public static function render(): void
    {
        $proximos = self::next_up();
        $parados  = self::stuck();
        $ultimos  = Queue::sent_entries(3);
        $cola     = admin_url('admin.php?page=convoca-publisher&tab=queue');
        ?>
        <?php if ([] !== $parados) : ?>
            <div class="cp-notice cp-notice--warn">
                <p>
                    <strong>
                        <?php
                        printf(
                            /* translators: %d: número de envíos parados */
                            esc_html(_n('%d envío no ha salido.', '%d envíos no han salido.', count($parados), 'convoca-publisher')),
                            (int) count($parados)
                        );
            ?>
                    </strong>
                </p>
                <ul>
                    <?php foreach ($parados as $parado) : ?>
                        <li>
                            <?php echo esc_html(wp_date('d/m/Y H:i', (int) ($parado['time'] ?? 0))); ?> —
                            <?php echo esc_html((string) ($parado['title'] ?? '')); ?>
                        </li>
                    <?php endforeach; ?>
                </ul>
                <p>
                    <a class="button" href="<?php echo esc_url($cola); ?>">
                        <?php echo esc_html__('Ver qué pasó y reintentar', 'convoca-publisher'); ?>
                    </a>
                </p>
            </div>
        <?php endif; ?>

        <h3><?php echo esc_html__('Lo siguiente', 'convoca-publisher'); ?></h3>
        <?php if ([] === $proximos) : ?>
            <p class="description"><?php echo esc_html__('No hay nada programado para los próximos días.', 'convoca-publisher'); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ($proximos as $proximo) : ?>
                    <li>
                        <?php echo esc_html(wp_date('d/m/Y H:i', (int) ($proximo['time'] ?? 0))); ?> —
                        <?php echo esc_html((string) ($proximo['title'] ?? '')); ?>
                        <?php if (!empty($proximo['account_name'])) : ?>
                            <span class="description">(<?php echo esc_html((string) $proximo['account_name']); ?>)</span>
                        <?php endif; ?>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <h3><?php echo esc_html__('Lo último que salió', 'convoca-publisher'); ?></h3>
        <?php if ([] === $ultimos) : ?>
            <p class="description"><?php echo esc_html__('Todavía no se ha publicado nada en redes desde aquí.', 'convoca-publisher'); ?></p>
        <?php else : ?>
            <ul>
                <?php foreach ($ultimos as $ultimo) : ?>
                    <li>
                        <?php echo !empty($ultimo['success']) ? '✅' : '❌'; ?>
                        <?php echo esc_html((string) $ultimo['title']); ?>
                        <span class="description">
                            <?php echo esc_html(sprintf('%s · %s', (string) $ultimo['account'], (string) $ultimo['time'])); ?>
                        </span>
                    </li>
                <?php endforeach; ?>
            </ul>
        <?php endif; ?>

        <p><a href="<?php echo esc_url($cola); ?>"><?php echo esc_html__('Ir a la cola', 'convoca-publisher'); ?></a></p>
        <?php
    }
}
