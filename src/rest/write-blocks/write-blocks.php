<?php

namespace WPCOMVIP\BlockDataApi;

use WP_REST_Request;
use WP_Error;

defined( 'ABSPATH' ) || die();

class WriteBlocks {
	public static function init() {
		add_action( 'rest_api_init', [ __CLASS__, 'register_rest_routes' ] );
	}

	public static function register_rest_routes() {
		if ( ! self::is_write_enabled() ) {
			return;
		}

		register_rest_route( WPCOMVIP__BLOCK_DATA_API__REST_ROUTE, 'posts/(?P<id>[0-9]+)', [
			'methods'             => 'POST',
			'permission_callback' => function( WP_REST_Request $request ) {
				if ( $request->get_param( 'id' ) === null ) {
					return false;
				}

				$post_id = intval( $request->get_param( 'id' ) );
				return current_user_can( 'edit_post', $post_id );
			},
			'callback'            => [ __CLASS__, 'write_blocks' ],
			'args'                => [
				'id'     => [
					'required'          => true,
					'validate_callback' => function( $param ) {
						$post_id = intval( $param );
						$post    = get_post( $post_id );

						if ( empty( $post ) || empty( $post->ID ) ) {
							return false;
						} else {
							return true;
						}
					},
					'sanitize_callback' => function( $param ) {
						return intval( $param );
					},
				],
				'blocks' => [
					'required'          => true,
					'validate_callback' => function( $param ) {
						return self::validate_blocks( $param );
					},
				],
			],
		] );

		register_rest_route( WPCOMVIP__BLOCK_DATA_API__REST_ROUTE, 'posts/new', [
			'methods'             => 'POST',
			'permission_callback' => function( WP_REST_Request $request ) {
				return current_user_can( 'publish_posts' );
			},
			'callback'            => [ __CLASS__, 'write_blocks' ],
			'args'                => [
				'blocks' => [
					'required'          => true,
					'validate_callback' => function( $param ) {
						return self::validate_blocks( $param );
					},
				],
			],
		] );
	}

	public static function write_blocks( $params ) {
		// Verify 'Editor-User' and 'Editor-App-Password' auth headers
		$request_headers = $params->get_headers();

		// 'Editor-User' header is sent as an array with one element
		$editor_user_headers = $request_headers['editor_user'] ?? [];
		$editor_user         = is_array( $editor_user_headers ) && count( $editor_user_headers ) === 1 ? $editor_user_headers[0] : '';
		if ( empty( $editor_user ) ) {
			return new WP_Error( 'vip-block-data-api-missing-header', __( 'Missing "Editor-User" header required for authentication' ) );
		}

		// 'Editor-App-Password' header is sent as an array with one element
		$editor_password_headers = $request_headers['editor_app_password'] ?? [];
		$editor_app_password     = is_array( $editor_password_headers ) && count( $editor_password_headers ) === 1 ? $editor_password_headers[0] : '';
		if ( empty( $editor_app_password ) ) {
			return new WP_Error( 'vip-block-data-api-missing-header', __( 'Missing "Editor-App-Password" header required for authentication' ) );
		}

		$post_id = $params['id'] ?? 'new';
		$blocks  = $params['blocks'];

		$response = wp_remote_post( WPCOMVIP__BLOCK_DATA_API__WRITE_MIDDLEWARE_URL, [
			'headers'     => [ 'Content-Type' => 'application/json; charset=utf-8' ],
			'data_format' => 'body',
			'timeout'     => 10,
			'body'        => wp_json_encode([
				'blocks'            => $blocks,
				'editorUser'        => $editor_user,
				'editorAppPassword' => $editor_app_password,
				'secretKey'         => WPCOMVIP__BLOCK_DATA_API__WRITE_SECRET_KEY,
			]),
		]);

		if ( is_wp_error( $response ) ) {
			$response_body = [ 'error' => $response->get_error_message() ];
		} else {
			$response_body = json_decode( $response['body'], /* associative */ true );
		}

		if ( isset( $response_body['error'] ) ) {
			return new WP_Error( 'vip-block-data-api-write-error', $response_body['error'] );
		}

		if ( ! isset( $response_body['blockHtml'] ) ) {
			return new WP_Error( 'vip-block-data-api-write-failure', __( 'Write failed unexpectly' ) );
		}

		$block_html = $response_body['blockHtml'];

		if ( 'new' === $post_id ) {
			// self::create_new_post_with_block_html( $block_html );
		} else {
			// self::update_post_with_block_html( $post_id, $block_html );
		}

		return $block_html;
	}


	public static function is_write_enabled() {
		return defined( 'WPCOMVIP__BLOCK_DATA_API__WRITE_SECRET_KEY' ) && defined( 'WPCOMVIP__BLOCK_DATA_API__WRITE_MIDDLEWARE_URL' );
	}

	private static function create_new_post_with_block_html( $user_id, $block_html ) {
		$post_data = [
			'post_title'   => __( 'New Post' ),
			'post_author'  => $user_id,
			'post_content' => $block_html,
		];

		/**
		 * Filters post data from the write endpoint before it's used to create a new post.
		 * Use this to change the post type, post status, etc.
		 *
		 * @param array $post_data Post data arary that will be passed to wp_insert_post().
		 * @param int   $user_id   User ID of the user that initiated the write request.
		 *
		 */
		return apply_filters( 'vip_block_data_api__rest_validate_post_id', $is_valid, $post_id );

		wp_insert_post( $post_data );
	}

	private static function validate_blocks( $blocks ) {
		return is_array( $blocks );
	}
}

WriteBlocks::init();
