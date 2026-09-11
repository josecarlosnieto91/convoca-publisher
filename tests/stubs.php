<?php

/**
 * WordPress function stubs for unit tests.
 */

// --- Time constants (normalmente definidas por WP) ---
define('MINUTE_IN_SECONDS', 60);
define('HOUR_IN_SECONDS', 3600);
define('DAY_IN_SECONDS', 86400);
define('WEEK_IN_SECONDS', 604800);
define('MONTH_IN_SECONDS', 2592000);
define('YEAR_IN_SECONDS', 31536000);

// --- Translation ---
function __(string $text, string $domain = 'default'): string
{
    return $text;
}
function _e(string $text, string $domain = 'default'): void
{
    echo esc_html($text);
}
function esc_html__(string $text, string $domain = 'default'): string
{
    return $text;
}
function esc_js(string $text): string
{
    return $text;
}
function esc_attr(string $text): string
{
    return $text;
}
function esc_url(string $url): string
{
    return $url;
}
function esc_html(string $text): string
{
    return htmlspecialchars($text, ENT_QUOTES, 'UTF-8');
}

$GLOBALS['_cp_test_http'] = [];

// --- Options ---
$GLOBALS['_cp_test_options'] = [];

function get_option(string $option, mixed $default = false): mixed
{
    if ($option === 'cp_encryption_key') {
        return 'SISo4fW6aYd2QYYabknhj3S9no1GI8HOjX0OMOEmsGA=';
    }

    // Como WordPress: `pre_option_{$option}` puede cortocircuitar la lectura (lo usa el
    // perfil para poner SUS ajustes en su sitio mientras se llama al canal).
    $pre = apply_filters("pre_option_{$option}", false);
    if (false !== $pre) {
        return $pre;
    }

    return $GLOBALS['_cp_test_options'][$option] ?? $default;
}
function update_option(string $option, mixed $value, bool $autoload = false): bool
{
    $GLOBALS['_cp_test_options'][$option] = $value;
    return true;
}
function delete_option(string $option): bool
{
    unset($GLOBALS['_cp_test_options'][$option]);
    return true;
}
function add_option(string $option, mixed $value, string $deprecated = '', bool $autoload = true): bool
{
    return true;
}
function register_setting(string $option_group, string $option_name, array $args = []): void {}

// --- WP Core ---
function wp_salt(string $scheme = 'auth'): string
{
    return 'test_salt_value_for_testing_only_32_chars__';
}
function wp_remote_post(string $url, array $args = []): array|WP_Error
{
    // Se apunta la llamada: hay pruebas que necesitan comprobar QUÉ se envió y con qué
    // credencial (por ejemplo, que cada cuenta use su propio token).
    $GLOBALS['_cp_test_http'][] = ['method' => 'POST', 'url' => $url, 'args' => $args];
    return [];
}
function wp_remote_get(string $url, array $args = []): array|WP_Error
{
    $GLOBALS['_cp_test_http'][] = ['method' => 'GET', 'url' => $url, 'args' => $args];
    return [];
}
function wp_remote_retrieve_body(array|WP_Error $response): string
{
    return '';
}
function wp_remote_retrieve_response_code(array|WP_Error $response): int
{
    return 200;
}
function is_wp_error(mixed $thing): bool
{
    return false;
}
function wp_json_encode(mixed $value, int $options = 0, int $depth = 512): string|false
{
    return json_encode($value, $options, $depth);
}
function wp_generate_password(int $length = 12, bool $special_chars = true, bool $extra_special_chars = false): string
{
    return str_repeat('x', $length);
}
function wp_trim_words(string $text, int $num_words = 55, string $more = '…'): string
{
    $words = preg_split('/\s+/', $text);
    if (count($words) > $num_words) {
        return implode(' ', array_slice($words, 0, $num_words)) . $more;
    }
    return $text;
}
function get_post(int|\WP_Post $post = null, ?string $output = null, string $filter = 'raw'): ?\WP_Post
{
    if ($post instanceof \WP_Post) {
        return $post;
    }
    if ($post === null || $post === 0) {
        return null;
    }
    $p = new \WP_Post();
    $p->ID = $post;
    return $p;
}
function get_permalink(\WP_Post|int $post): string
{
    return 'https://example.com/?p=' . ($post instanceof \WP_Post ? $post->ID : $post);
}
function get_the_date(string $format = '', \WP_Post|int $post = null): string
{
    return '2026-06-13';
}
function get_the_author_meta(string $field, int $user_id = 0): string
{
    return 'Test Author';
}
function get_the_excerpt(\WP_Post|int $post = null): string
{
    return 'Test excerpt.';
}
function get_post_thumbnail_id(\WP_Post|int $post = null): int|false
{
    return false;
}
function get_the_post_thumbnail_url(\WP_Post|int $post = null, string|array $size = 'post-thumbnail'): string|false
{
    return false;
}
function wp_get_attachment_image_src(int $attachment_id, string|array $size = 'thumbnail'): array|false
{
    return false;
}
$GLOBALS['_cp_test_postmeta'] = [];
$GLOBALS['_cp_test_posts']    = [];
$GLOBALS['_cp_test_titles']   = [];

function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
{
    $valor = $GLOBALS['_cp_test_postmeta'][$post_id][$key] ?? '';

    return $single ? $valor : ('' === $valor ? [] : [$valor]);
}
function update_post_meta(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int|bool
{
    $GLOBALS['_cp_test_postmeta'][$post_id][$meta_key] = $meta_value;

    return true;
}
function delete_post_meta(int $post_id, string $meta_key, mixed $meta_value = ''): bool
{
    $existia = isset($GLOBALS['_cp_test_postmeta'][$post_id][$meta_key]);
    unset($GLOBALS['_cp_test_postmeta'][$post_id][$meta_key]);

    return $existia;
}
function get_posts(array $args = []): array
{
    // El harness no tiene base de datos: devuelve lo que la prueba haya dejado puesto.
    return $GLOBALS['_cp_test_posts'];
}
function get_the_title(int|WP_Post $post = 0): string
{
    $id = $post instanceof WP_Post ? $post->ID : (int) $post;

    return (string) ($GLOBALS['_cp_test_titles'][$id] ?? 'Test Post');
}
function wp_clear_scheduled_hook(string $hook, array $args = []): bool
{
    return true;
}
function wp_schedule_event(int $timestamp, string $recurrence, string $hook, array $args = []): bool
{
    return true;
}
function wp_next_scheduled(string $hook, array $args = []): int|false
{
    return false;
}
function wp_get_post_tags(int $post_id, array $args = []): array
{
    return [];
}
function sanitize_title(string $title): string
{
    // Como WordPress: minúsculas, lo que no sea alfanumérico pasa a guion y se recorta.
    $title = strtolower($title);
    $title = preg_replace('/[^a-z0-9]+/', '-', $title) ?? '';

    return trim($title, '-');
}

// --- Hooks ---
function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void {}
$GLOBALS['_cp_test_filters'] = [];

function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): bool
{
    $GLOBALS['_cp_test_filters'][$hook_name][$priority][] = ['callback' => $callback, 'accepted' => $accepted_args];
    return true;
}
function remove_filter(string $hook_name, callable $callback, int $priority = 10): bool
{
    if (empty($GLOBALS['_cp_test_filters'][$hook_name][$priority])) {
        return false;
    }

    $antes = $GLOBALS['_cp_test_filters'][$hook_name][$priority];
    $quedan = array_values(array_filter(
        $antes,
        static fn(array $entry): bool => $entry['callback'] !== $callback && $entry['callback'] != $callback
    ));

    $GLOBALS['_cp_test_filters'][$hook_name][$priority] = $quedan;

    return count($quedan) < count($antes);
}
function has_filter(string $hook_name, callable|false $callback = false): bool
{
    return !empty($GLOBALS['_cp_test_filters'][$hook_name]);
}
function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed
{
    if (empty($GLOBALS['_cp_test_filters'][$hook_name])) {
        return $value;
    }

    $porPrioridad = $GLOBALS['_cp_test_filters'][$hook_name];
    ksort($porPrioridad);

    foreach ($porPrioridad as $entradas) {
        foreach ($entradas as $entrada) {
            $valores = array_slice(array_merge([$value], $args), 0, max(1, (int) $entrada['accepted']));
            $value   = ($entrada['callback'])(...$valores);
        }
    }

    return $value;
}
function do_action(string $hook_name, mixed ...$args): void {}
function current_user_can(string $capability): bool
{
    return true;
}
function wp_die(string|WP_Error $message = '', string $title = '', array $args = []): void
{
    exit;
}
function check_admin_referer(string $action = '-1', string $query_arg = '_wpnonce'): int|false
{
    return 1;
}
function check_ajax_referer(string $action = '-1', string $query_arg = '_wpnonce', bool $die = true): int|false
{
    return 1;
}
function wp_verify_nonce(string $nonce, string $action = '-1'): int|false
{
    return 1;
}
function wp_create_nonce(string $action = '-1'): string
{
    return 'test_nonce_value';
}
function wp_nonce_field(string $action = '-1', string $name = '_wpnonce', bool $referer = true, bool $display = true): string
{
    return '<input type="hidden" />';
}
function wp_send_json(mixed $response, int $status_code = 200): void
{
    throw new \RuntimeException('wp_send_json called: ' . json_encode($response));
}
function current_time(string $type, bool $gmt = false): string|int
{
    return '2026-06-13 08:00:00';
}
function wp_schedule_single_event(int $timestamp, string $hook, array $args = []): bool
{
    return true;
}
function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = null, string $media = 'all'): void {}
function wp_add_inline_style(string $handle, string $data): void {}
function plugin_dir_path(string $file): string
{
    return '/tmp/test/wp-content/plugins/convoca-publisher/';
}
function plugin_dir_url(string $file): string
{
    return 'http://example.com/wp-content/plugins/convoca-publisher/';
}

// --- Admin ---
function get_current_screen(): ?object
{
    // Doble configurable: los avisos solo salen en su pantalla, así que hay que poder decir cuál es.
    if (empty($GLOBALS['_cp_test_screen_id'])) {
        return null;
    }

    return new class {
        public string $id = '';

        public function __construct()
        {
            $this->id = (string) $GLOBALS['_cp_test_screen_id'];
        }
    };
}
function get_current_user_id(): int
{
    return 1;
}
function get_user_meta(int $user_id, string $key = '', bool $single = false): mixed
{
    return $single ? false : [];
}
function update_user_meta(int $user_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int|bool
{
    return true;
}
function admin_url(string $path = '', string $scheme = 'admin'): string
{
    return 'http://example.com/wp-admin/' . $path;
}
function wp_dropdown_pages(array $args = []): void
{
    echo '<select></select>';
}
function checked(mixed $checked, mixed $current = true, bool $echo = false): string
{
    return 'checked';
}
function submit_button(string $text = '', string $type = 'primary', string $name = 'submit', bool $wrap = true, array|string $other_attributes = []): void
{
    echo '<button type="submit">' . esc_html($text) . '</button>';
}
function settings_fields(string $option_group): void {}
function wp_kses_post(string $data): string
{
    return $data;
}
function set_transient(string $transient, mixed $value, int $expiration = 0): bool
{
    return true;
}
function get_transient(string $transient): mixed
{
    return false;
}
function wp_safe_redirect(string $location, int $status = 302, string $x_redirect_by = 'WordPress'): bool
{
    return true;
}
function add_query_arg(string|array $key, string $value = '', string $url = ''): string
{
    if (is_array($key)) {
        $params = $key;
        $url    = (string) $value;
    } else {
        $params = [$key => $value];
    }

    $trozos = [];

    foreach ($params as $k => $v) {
        $trozos[] = rawurlencode((string) $k) . '=' . rawurlencode((string) $v);
    }

    return $url . (str_contains($url, '?') ? '&' : '?') . implode('&', $trozos);
}
function wp_get_referer(): string|false
{
    return 'http://example.com/wp-admin/admin.php?page=convoca-publisher';
}

// --- WP_Error ---
class WP_Error
{
    private array $errors = [];
    public function __construct(string $code = '', string $message = '', mixed $data = '') {}
    public function get_error_message(): string
    {
        return '';
    }
}

// --- wpdb (mínimo para probar la cola de reintentos/moderación) ---
class wpdb
{
    public string $prefix = 'wp_';
    public int $insert_id = 0;

    public function prepare(string $query, mixed ...$args): string
    {
        return $query;
    }

    public function insert(string $table, array $data, array|string $format = null): int|false
    {
        $this->insert_id++;
        $row = $data;
        $row['id'] = $this->insert_id;
        $GLOBALS['_cp_test_db']['rows'][$table][] = $row;
        $GLOBALS['_cp_test_db']['inserts'][] = ['table' => $table, 'data' => $data];
        return 1;
    }

    public function get_results(string $query = null, string $output = 'OBJECT'): array
    {
        // La prueba puede dejar filas en `_cp_test_db['results']` (cola de reintentos).
        return $GLOBALS['_cp_test_db']['results'] ?? [];
    }

    public function get_row(string $query = null, string $output = 'OBJECT', int $y = 0): object|array|null
    {
        return null;
    }

    public function get_var(string $query = null, int $x = 0, int $y = 0): mixed
    {
        return null;
    }

    public function update(string $table, array $data, array $where, array|string $format = null, array|string $where_format = null): int|false
    {
        return 1;
    }

    public function delete(string $table, array $where, array|string $where_format = null): int|false
    {
        return 1;
    }

    public function get_charset_collate(): string
    {
        return '';
    }
}

function wp_mail(string|array $to, string $subject, string $message, string|array $headers = '', string|array $attachments = []): bool
{
    return true;
}

// --- WP_Post ---
class WP_Post
{
    public int $ID = 0;
    public int $post_author = 1;
    public string $post_title = 'Test Post';
    public string $post_content = 'Test content for the post.';
    public string $post_excerpt = 'Test excerpt.';
    public string $post_status = 'publish';
    public string $post_type = 'post';
    public string $post_date = '2026-06-13 08:00:00';
}

// --- Dobles que necesitan las pantallas del panel ---
function esc_attr__(string $text, string $domain = 'default'): string
{
    return $text;
}
function esc_html_e(string $text, string $domain = 'default'): void
{
    echo $text;
}
function selected(mixed $selected, mixed $current = true, bool $echo = true): string
{
    return $echo ? (string) $selected === (string) $current ? 'selected' : '' : '';
}
function wp_nonce_url(string $actionurl, string|int $action = -1, string $name = '_wpnonce'): string
{
    return add_query_arg($name, 'nonce-de-prueba', $actionurl);
}
function sanitize_key(string $key): string
{
    return preg_replace('/[^a-z0-9_\-]/', '', strtolower($key)) ?? '';
}
function wp_unslash(mixed $value): mixed
{
    return is_string($value) ? stripslashes($value) : $value;
}
function delete_transient(string $transient): bool
{
    return true;
}
function number_format_i18n(float $number, int $decimals = 0): string
{
    return number_format($number, $decimals);
}
function _n(string $single, string $plural, int $number, string $domain = 'default'): string
{
    return 1 === $number ? $single : $plural;
}

// --- Dobles del panel de cuentas ---
function sanitize_text_field(string $str): string
{
    return trim(strip_tags($str));
}

// --- Dobles del calendario ---
function wp_timezone(): DateTimeZone
{
    return new DateTimeZone('UTC');
}
function wp_date(string $format, ?int $timestamp = null, ?DateTimeZone $timezone = null): string
{
    return gmdate($format, $timestamp ?? time());
}
