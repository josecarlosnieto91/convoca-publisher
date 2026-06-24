<?php

/**
 * WordPress function stubs for unit tests.
 */

// --- Translation ---
function __(string $text, string $domain = 'default'): string
{
    return $text;
}
function _e(string $text, string $domain = 'default'): void
{
    echo $text;
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

// --- Options ---
function get_option(string $option, mixed $default = false): mixed
{
    // Return a fixed AES-256 key for Crypto so tests are deterministic
    if ($option === 'cp_encryption_key') {
        return 'SISo4fW6aYd2QYYabknhj3S9no1GI8HOjX0OMOEmsGA=';
    }
    return $default;
}
function update_option(string $option, mixed $value, bool $autoload = false): bool
{
    return true;
}
function delete_option(string $option): bool
{
    return true;
}
function add_option(string $option, mixed $value, string $deprecated = '', bool $autoload = true): bool
{
    return true;
}
function register_setting(string $option_group, string $option_name, array $args = []): void
{
}

// --- WP Core ---
function wp_salt(string $scheme = 'auth'): string
{
    return 'test_salt_value_for_testing_only_32_chars__';
}
function wp_remote_post(string $url, array $args = []): array|WP_Error
{
    return [];
}
function wp_remote_get(string $url, array $args = []): array|WP_Error
{
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
function get_post_thumbnail_id(\WP_Post|int $post = null): int|false
{
    return false;
}
function wp_get_attachment_image_src(int $attachment_id, string|array $size = 'thumbnail'): array|false
{
    return false;
}
function get_post_meta(int $post_id, string $key = '', bool $single = false): mixed
{
    return $single ? '' : [];
}
function update_post_meta(int $post_id, string $meta_key, mixed $meta_value, mixed $prev_value = ''): int|bool
{
    return true;
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
    return strtolower(trim(preg_replace('/[^a-zA-Z0-9áéíóúüñÁÉÍÓÚÜÑ\s-]/', '', $title)));
}

// --- Hooks ---
function add_action(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
}
function add_filter(string $hook_name, callable $callback, int $priority = 10, int $accepted_args = 1): void
{
}
function apply_filters(string $hook_name, mixed $value, mixed ...$args): mixed
{
    return $value;
}
function do_action(string $hook_name, mixed ...$args): void
{
}
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
function wp_enqueue_style(string $handle, string $src = '', array $deps = [], $ver = null, string $media = 'all'): void
{
}
function wp_add_inline_style(string $handle, string $data): void
{
}
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
    return null;
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
    echo '<button type="submit">' . $text . '</button>';
}
function settings_fields(string $option_group): void
{
}
function wp_kses_post(string $data): string
{
    return $data;
}

// --- WP_Error ---
class WP_Error
{
    private array $errors = [];
    public function __construct(string $code = '', string $message = '', mixed $data = '')
    {
    }
    public function get_error_message(): string
    {
        return '';
    }
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
