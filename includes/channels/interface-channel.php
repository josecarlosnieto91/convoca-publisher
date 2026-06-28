<?php

namespace ConvocaPublisher\Channels;

defined('ABSPATH') || exit;

interface ChannelInterface
{
    public function get_id(): string;
    public function get_name(): string;
    public function is_available(): bool;

    /**
     * @param int    $post_id
     * @param string $message
     * @param string $url
     * @param string $image_url
     * @return array{success: bool, post_id?: string, error?: string, networks?: string}
     */
    public function publish(int $post_id, string $message, string $url, string $image_url = ''): array;

    /**
     * @return array<string, array{title: string, type: string, description: string}>
     */
    public function get_settings_fields(): array;

    /**
     * @param array<string, string> $settings
     * @return array<int, string>
     */
    public function validate_settings(array $settings): array;

    /**
     * Verifies the connection to the channel's API or validates stored credentials.
     *
     * @return array{success: bool, message: string}
     */
    public function verify_connection(): array;
}
