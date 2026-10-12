<?php

/**
 * Convoca Publisher
 *
 * @package    Convoca\Publisher
 * @subpackage Includes
 *
 * @copyright  Copyright (C) 2026 Jose Carlos Nieto Ramos
 * @license    GPL-2.0-or-later
 *
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 */

namespace ConvocaPublisher;

defined( 'ABSPATH' ) || exit;

class Rest {

	private const NAMESPACE = 'convoca-publisher/v1';

	public static function init(): void {
		add_action( 'rest_api_init', array( self::class, 'register_routes' ) );
	}

	public static function register_routes(): void {
		register_rest_route(
			self::NAMESPACE,
			'/test/(?P<channel>[a-z_]+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'test_channel' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'channel' => array(
						'required' => true,
						'type'     => 'string',
					),
					'post_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/status',
			array(
				'methods'             => 'GET',
				'callback'            => array( self::class, 'get_status' ),
				'permission_callback' => array( self::class, 'check_permission' ),
			)
		);

		register_rest_route(
			self::NAMESPACE,
			'/publish/(?P<post_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array( self::class, 'publish_post' ),
				'permission_callback' => array( self::class, 'check_permission' ),
				'args'                => array(
					'dry_run'  => array(
						'type'    => 'boolean',
						'default' => false,
					),
					'channels' => array(
						'type'  => 'array',
						'items' => array( 'type' => 'string' ),
					),
				),
			)
		);
	}

	public static function check_permission( \WP_REST_Request $request ): bool {
		return current_user_can( 'manage_options' );
	}

	public static function test_channel( \WP_REST_Request $request ): \WP_REST_Response {
		$channel_id = $request->get_param( 'channel' );
		$post_id    = $request->get_param( 'post_id' );

		$channels = convoca_publisher()->get_channels();
		if ( ! isset( $channels[ $channel_id ] ) ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Canal no encontrado o no configurado.', 'convoca-publisher' ),
				),
				404
			);
		}

		$post = get_post( $post_id );
		if ( ! $post ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Post no encontrado.', 'convoca-publisher' ),
				),
				404
			);
		}

		$publisher = Publisher::instance();
		if ( ! $publisher ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Publisher no inicializado.', 'convoca-publisher' ),
				),
				500
			);
		}

		$results = $publisher->publish_post( $post_id, true );
		$result  = $results[ $channel_id ] ?? array(
			'success' => false,
			'error'   => __( 'Canal no procesado.', 'convoca-publisher' ),
		);

		return new \WP_REST_Response( $result, ! empty( $result['success'] ) ? 200 : 500 );
	}

	public static function get_status(): \WP_REST_Response {
		$channels = convoca_publisher()->get_channels();
		$data     = array();

		foreach ( $channels as $id => $ch ) {
			$data[ $id ] = array(
				'name'      => $ch->get_name(),
				'available' => $ch->is_available(),
				'settings'  => array_keys( $ch->get_settings_fields() ),
			);
		}

		$retry_stats = class_exists( Retry::class ) ? Retry::get_queue_stats() : array();

		return new \WP_REST_Response(
			array(
				'channels'    => $data,
				'retry_queue' => $retry_stats,
				'version'     => CONVOCA_PUBLISHER_VERSION,
			)
		);
	}

	public static function publish_post( \WP_REST_Request $request ): \WP_REST_Response {
		$post_id         = $request->get_param( 'post_id' );
		$dry_run         = $request->get_param( 'dry_run' );
		$channels_filter = $request->get_param( 'channels' );

		$post = get_post( $post_id );
		if ( ! $post || $post->post_status !== 'publish' ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Post no encontrado o no publicado.', 'convoca-publisher' ),
				),
				404
			);
		}

		if ( $dry_run ) {
			$all_channels = convoca_publisher()->get_channels();
			$results      = array();
			foreach ( $all_channels as $id => $ch ) {
				if ( $channels_filter && ! in_array( $id, $channels_filter, true ) ) {
					continue;
				}
				$results[ $id ] = array(
					'success'  => true,
					'dry_run'  => true,
					'message'  => sprintf(
						/* translators: %s: channel name */
						__( 'Simulación: se publicaría en %s', 'convoca-publisher' ),
						$ch->get_name()
					),
					'channel'  => $ch->get_name(),
					'post_url' => get_permalink( $post ),
				);
			}
			return new \WP_REST_Response(
				array(
					'success' => true,
					'dry_run' => true,
					'results' => $results,
				)
			);
		}

		$publisher = Publisher::instance();
		if ( ! $publisher ) {
			return new \WP_REST_Response(
				array(
					'success' => false,
					'error'   => __( 'Publisher no inicializado.', 'convoca-publisher' ),
				),
				500
			);
		}

		if ( ! empty( $channels_filter ) ) {
			$results = array();
			foreach ( $channels_filter as $channel_id ) {
				$channels = convoca_publisher()->get_channels();
				$channel  = $channels[ $channel_id ] ?? null;
				if ( $channel && $channel->is_available() ) {
					$url       = get_permalink( $post );
					$hashtags  = self::get_hashtags( $post );
					$image_url = '';
					$thumb_id  = get_post_thumbnail_id( $post );
					if ( $thumb_id ) {
						$image     = wp_get_attachment_image_src( $thumb_id, 'large' );
						$image_url = $image ? $image[0] : '';
					}

					$tkey     = 'convoca_publisher_' . $channel->get_id() . '_template';
					$template = get_option( $tkey, get_option( 'convoca_publisher_message_template', '{title} — {url}' ) );
					$message  = str_replace(
						array( '{title}', '{url}', '{excerpt}', '{hashtags}', '{date}', '{author}' ),
						array( $post->post_title, $url, wp_trim_words( $post->post_excerpt ?: $post->post_title, 20 ), $hashtags, get_the_date( '', $post ), get_the_author_meta( 'display_name', (int) $post->post_author ) ),
						$template
					);

					$result                 = $channel->publish( $post_id, $message, $url, $image_url );
					$results[ $channel_id ] = $result;
				}
			}
			return new \WP_REST_Response(
				array(
					'success' => true,
					'results' => $results,
				)
			);
		}

		$results = $publisher->publish_post( $post_id, true );
		return new \WP_REST_Response(
			array(
				'success' => true,
				'results' => $results,
			)
		);
	}

	private static function get_hashtags( \WP_Post $post ): string {
		$tags = wp_get_post_tags( $post->ID, array( 'fields' => 'names' ) );
		if ( empty( $tags ) ) {
			return '';
		}
		$tags     = array_slice( $tags, 0, 5 );
		$hashtags = array_map(
			function ( string $tag ): string {
				$tag = sanitize_title( $tag );
				return '#' . str_replace( array( '-', '_', ' ' ), '', $tag );
			},
			$tags
		);
		return implode( ' ', $hashtags );
	}
}
