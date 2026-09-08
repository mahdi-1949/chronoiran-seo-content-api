<?php
/**
 * Plugin Name: رابط محتوای سئوی کورنو ایران
 * Plugin URI: https://github.com/mahdi-1949/chronoiran-seo-content-api
 * Description: رابط برنامه‌نویسی نسخه‌بندی‌شده برای پیش‌نویس، بازبینی و انتشار محتوای سئوی دسته‌ها و محصولات ووکامرس؛ طراحی شده توسط مهدی توکلی.
 * Version: 0.4.1
 * Author: مهدی توکلی
 * Author URI: https://github.com/mahdi-1949
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: chronoiran-seo-api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ChronoIran_SEO_Content_API {
	const VERSION             = '0.4.1';
	const REST_NAMESPACE      = 'chrono-seo/v1';
	const TERM_DRAFT_META     = '_chrono_seo_draft';
	const TERM_STATUS_META    = '_chrono_seo_status';
	const TERM_EXTERNAL_META  = '_chrono_external_id';
	const POST_DRAFT_META     = '_chrono_seo_product_draft';
	const POST_STATUS_META    = '_chrono_seo_product_status';
	const POST_EXTERNAL_META  = '_chrono_external_id';

	public function __construct() {
		add_action( 'rest_api_init', array( $this, 'register_routes' ) );
	}

	public function register_routes() {
		register_rest_route(
			self::REST_NAMESPACE,
			'/health',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'health' ),
				'permission_callback' => '__return_true',
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_categories' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_or_update_category' ),
					'permission_callback' => array( $this, 'can_write' ),
					'args'                => $this->category_write_args(),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/categories/(?P<id>\\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_category' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_category' ),
					'permission_callback' => array( $this, 'can_write' ),
					'args'                => $this->category_write_args(),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/categories/(?P<id>\\d+)/publish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'publish_category' ),
				'permission_callback' => array( $this, 'can_write' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_products' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products/(?P<id>\\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => array( $this, 'can_read' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_product' ),
					'permission_callback' => array( $this, 'can_write' ),
					'args'                => $this->product_write_args(),
				),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products/(?P<id>\\d+)/publish',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'publish_product' ),
				'permission_callback' => array( $this, 'can_write' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_content' ),
				'permission_callback' => array( $this, 'can_read' ),
			)
		);
	}

	public function can_read( WP_REST_Request $request ) {
		return apply_filters( 'chronoiran_seo_api_read_permission', true, $request );
	}

	public function can_write( WP_REST_Request $request ) {
		if ( current_user_can( 'manage_woocommerce' ) || current_user_can( 'manage_options' ) ) {
			return true;
		}

		$configured_key = defined( 'CHRONOIRAN_SEO_API_KEY' ) ? (string) CHRONOIRAN_SEO_API_KEY : '';
		if ( '' !== $configured_key ) {
			$provided_key = (string) $request->get_header( 'X-Chrono-API-Key' );
			if ( '' === $provided_key ) {
				$authorization = (string) $request->get_header( 'Authorization' );
				if ( 0 === stripos( $authorization, 'Bearer ' ) ) {
					$provided_key = trim( substr( $authorization, 7 ) );
				}
			}
			if ( '' === $provided_key || ! hash_equals( $configured_key, $provided_key ) ) {
				return new WP_Error( 'chrono_api_unauthorized', 'کلید دسترسی API نامعتبر یا ارسال نشده است.', array( 'status' => 401 ) );
			}
			return true;
		}

		return apply_filters( 'chronoiran_seo_api_permission', true, $request );
	}

	public function health() {
		$api_key_enabled = defined( 'CHRONOIRAN_SEO_API_KEY' ) && '' !== (string) CHRONOIRAN_SEO_API_KEY;
		return array(
			'ok'              => taxonomy_exists( 'product_cat' ),
			'version'         => self::VERSION,
			'wordpress'       => get_bloginfo( 'version' ),
			'php'             => PHP_VERSION,
			'woocommerce'     => class_exists( 'WooCommerce' ),
			'product_cat'     => taxonomy_exists( 'product_cat' ),
			'yoast'           => defined( 'WPSEO_VERSION' ),
			'yoast_version'   => defined( 'WPSEO_VERSION' ) ? WPSEO_VERSION : '',
			'write_auth_mode' => $api_key_enabled ? 'api_key' : 'legacy_public_or_filter',
			'time_utc'        => current_time( 'mysql', true ),
		);
	}

	private function category_write_args() {
		return array(
			'name'             => array( 'type' => 'string' ),
			'title'            => array( 'type' => 'string' ),
			'h1'               => array( 'type' => 'string' ),
			'slug'             => array( 'type' => 'string' ),
			'url'              => array( 'type' => 'string' ),
			'category_url'     => array( 'type' => 'string' ),
			'parent'           => array( 'type' => 'integer', 'minimum' => 0 ),
			'external_id'      => array( 'type' => 'string' ),
			'description'      => array( 'type' => 'string' ),
			'content'          => array( 'type' => 'string' ),
			'intro_content'    => array( 'type' => 'string' ),
			'outro_content'    => array( 'type' => 'string' ),
			'seo_title'        => array( 'type' => 'string' ),
			'meta_description' => array( 'type' => 'string' ),
			'seo_description'  => array( 'type' => 'string' ),
			'focus_keyword'    => array( 'type' => 'string' ),
			'canonical_url'    => array( 'type' => 'string' ),
			'yoast'            => array(
				'type'       => 'object',
				'properties' => array(
					'title'            => array( 'type' => 'string' ),
					'description'      => array( 'type' => 'string' ),
					'focus_keyword'    => array( 'type' => 'string' ),
					'canonical_url'    => array( 'type' => 'string' ),
				),
			),
			'publish_now'      => array( 'type' => 'boolean' ),
			'status'           => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'published' ) ),
		);
	}

	private function product_write_args() {
		return array(
			'title'             => array( 'type' => 'string' ),
			'slug'              => array( 'type' => 'string' ),
			'url'               => array( 'type' => 'string' ),
			'external_id'       => array( 'type' => 'string' ),
			'description'       => array( 'type' => 'string' ),
			'content'           => array( 'type' => 'string' ),
			'short_description' => array( 'type' => 'string' ),
			'seo_title'         => array( 'type' => 'string' ),
			'meta_description'  => array( 'type' => 'string' ),
			'seo_description'   => array( 'type' => 'string' ),
			'focus_keyword'     => array( 'type' => 'string' ),
			'canonical_url'     => array( 'type' => 'string' ),
			'yoast'             => array(
				'type'       => 'object',
				'properties' => array(
					'title'            => array( 'type' => 'string' ),
					'description'      => array( 'type' => 'string' ),
					'focus_keyword'    => array( 'type' => 'string' ),
					'canonical_url'    => array( 'type' => 'string' ),
				),
			),
			'publish_now'       => array( 'type' => 'boolean' ),
			'status'            => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'published' ) ),
		);
	}

	public function list_categories( WP_REST_Request $request ) {
		$dependency_error = $this->category_dependency_error();
		if ( $dependency_error ) {
			return $dependency_error;
		}
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		if ( ! $request->get_param( 'per_page' ) ) {
			$per_page = 100;
		}

		$args = array(
			'taxonomy'   => 'product_cat',
			'hide_empty' => false,
			'number'     => $per_page,
			'offset'     => ( $page - 1 ) * $per_page,
			'orderby'    => 'term_id',
			'order'      => 'ASC',
		);
		if ( $request->get_param( 'search' ) ) {
			$args['search'] = sanitize_text_field( $request->get_param( 'search' ) );
		}
		if ( $request->get_param( 'slug' ) ) {
			$args['slug'] = sanitize_title( $request->get_param( 'slug' ) );
		}
		if ( null !== $request->get_param( 'parent' ) ) {
			$args['parent'] = (int) $request->get_param( 'parent' );
		}
		$meta_query = array( 'relation' => 'AND' );
		if ( $request->get_param( 'external_id' ) ) {
			$meta_query[] = array(
				'key'   => self::TERM_EXTERNAL_META,
				'value' => sanitize_text_field( $request->get_param( 'external_id' ) ),
			);
		}
		if ( $request->get_param( 'status' ) ) {
			$status = sanitize_key( $request->get_param( 'status' ) );
			if ( 'published' === $status ) {
				$meta_query[] = array(
					'relation' => 'OR',
					array(
						'key'     => self::TERM_STATUS_META,
						'compare' => 'NOT EXISTS',
					),
					array(
						'key'   => self::TERM_STATUS_META,
						'value' => 'published',
					),
				);
			} else {
				$meta_query[] = array(
					'key'   => self::TERM_STATUS_META,
					'value' => $status,
				);
			}
		}
		if ( count( $meta_query ) > 1 ) {
			$args['meta_query'] = $meta_query;
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return new WP_Error( 'category_list_failed', 'دریافت فهرست دسته‌ها ناموفق بود: ' . $terms->get_error_message(), array( 'status' => 500 ) );
		}
		$response = rest_ensure_response( array_map( array( $this, 'format_category' ), $terms ) );
		$count_args           = $args;
		$count_args['fields'] = 'count';
		unset( $count_args['number'], $count_args['offset'], $count_args['orderby'], $count_args['order'] );
		$total = get_terms( $count_args );
		if ( ! is_wp_error( $total ) ) {
			$total = (int) $total;
			$response->header( 'X-WP-Total', (string) $total );
			$response->header( 'X-WP-TotalPages', (string) ( $total ? (int) ceil( $total / $per_page ) : 0 ) );
		}
		$response->header( 'X-WP-Page', (string) $page );
		$response->header( 'X-WP-Per-Page', (string) $per_page );
		return $response;
	}

	public function get_category( WP_REST_Request $request ) {
		$term = $this->get_product_category( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return $this->category_response( $term );
	}

	public function create_or_update_category( WP_REST_Request $request ) {
		$dependency_error = $this->category_dependency_error();
		if ( $dependency_error ) {
			return $dependency_error;
		}
		$this->normalize_category_request( $request );
		$validation = $this->validate_write_request( $request );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$status = $this->resolve_write_status( $request );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$request->set_param( 'status', $status );
		$external_id = sanitize_text_field( (string) $request->get_param( 'external_id' ) );
		$term        = $external_id ? $this->find_category_by_external_id( $external_id ) : false;
		if ( ! $term && $request->get_param( 'slug' ) ) {
			$term = get_term_by( 'slug', sanitize_title( $request->get_param( 'slug' ) ), 'product_cat' );
		}
		if ( $term ) {
			$request->set_param( 'id', (int) $term->term_id );
			return $this->update_category( $request );
		}

		$name = sanitize_text_field( (string) $request->get_param( 'name' ) );
		if ( '' === $name ) {
			return new WP_Error( 'missing_name', 'برای ساخت دسته جدید یکی از فیلدهای name، h1 یا title الزامی است.', array( 'status' => 400 ) );
		}
		$result = wp_insert_term(
			$name,
			'product_cat',
			array(
				'slug'   => sanitize_title( $request->get_param( 'slug' ) ? $request->get_param( 'slug' ) : $name ),
				'parent' => (int) $request->get_param( 'parent' ),
			)
		);
		if ( is_wp_error( $result ) ) {
			return $result;
		}
		$request->set_param( 'id', (int) $result['term_id'] );
		return $this->update_category( $request, true );
	}

	public function update_category( WP_REST_Request $request, $created = false ) {
		$dependency_error = $this->category_dependency_error();
		if ( $dependency_error ) {
			return $dependency_error;
		}
		$this->normalize_category_request( $request );
		$validation = $this->validate_write_request( $request );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$status = $this->resolve_write_status( $request );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$term = $this->get_product_category( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}

		$draft = $this->get_meta_array( 'term', $term->term_id, self::TERM_DRAFT_META );
		$draft = $this->merge_draft_fields(
			$draft,
			$request,
			array( 'name', 'slug', 'parent', 'description', 'intro_content', 'outro_content', 'seo_title', 'meta_description', 'focus_keyword', 'canonical_url', 'external_id' )
		);
		$draft['status']     = $status;
		$draft['updated_at'] = current_time( 'mysql', true );

		if ( isset( $draft['external_id'] ) && '' !== $draft['external_id'] ) {
			update_term_meta( $term->term_id, self::TERM_EXTERNAL_META, $draft['external_id'] );
		}
		update_term_meta( $term->term_id, self::TERM_DRAFT_META, $draft );
		update_term_meta( $term->term_id, self::TERM_STATUS_META, $status );

		if ( 'published' === $status ) {
			return $this->publish_category( $request, $created );
		}
		return $this->category_response( get_term( $term->term_id, 'product_cat' ), $created ? 201 : 200 );
	}

	public function publish_category( WP_REST_Request $request, $created = false ) {
		$term = $this->get_product_category( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$draft = $this->get_meta_array( 'term', $term->term_id, self::TERM_DRAFT_META );
		if ( empty( $draft ) ) {
			return new WP_Error( 'empty_category_draft', 'برای این دسته پیش‌نویسی ذخیره نشده است.', array( 'status' => 409 ) );
		}

		$term_args = array();
		foreach ( array( 'name', 'slug', 'parent', 'description' ) as $field ) {
			if ( array_key_exists( $field, $draft ) ) {
				$term_args[ $field ] = 'parent' === $field ? (int) $draft[ $field ] : $draft[ $field ];
			}
		}
		if ( $term_args ) {
			$result = wp_update_term( $term->term_id, 'product_cat', $term_args );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$this->publish_yoast_meta( 'term', $term->term_id, $draft );
		$this->rebuild_yoast_term_indexable( $term->term_id );
		foreach ( array( 'intro_content', 'outro_content' ) as $field ) {
			if ( array_key_exists( $field, $draft ) ) {
				update_term_meta( $term->term_id, '_chrono_' . $field, $draft[ $field ] );
			}
		}

		$draft['status']       = 'published';
		$draft['published_at'] = current_time( 'mysql', true );
		update_term_meta( $term->term_id, self::TERM_DRAFT_META, $draft );
		update_term_meta( $term->term_id, self::TERM_STATUS_META, 'published' );
		clean_term_cache( $term->term_id, 'product_cat' );
		return $this->category_response( get_term( $term->term_id, 'product_cat' ), $created ? 201 : 200 );
	}

	public function list_products( WP_REST_Request $request ) {
		$per_page = min( 100, max( 1, (int) $request->get_param( 'per_page' ) ) );
		$page     = max( 1, (int) $request->get_param( 'page' ) );
		if ( ! $request->get_param( 'per_page' ) ) {
			$per_page = 50;
		}

		$args = array(
			'post_type'              => 'product',
			'post_status'            => 'publish',
			'posts_per_page'         => $per_page,
			'paged'                  => $page,
			'orderby'                => 'ID',
			'order'                  => 'ASC',
			'no_found_rows'          => false,
			'update_post_meta_cache' => true,
			'update_post_term_cache' => true,
		);
		if ( $request->get_param( 'search' ) ) {
			$args['s'] = sanitize_text_field( $request->get_param( 'search' ) );
		}
		if ( $request->get_param( 'category' ) ) {
			$args['tax_query'] = array(
				array(
					'taxonomy' => 'product_cat',
					'field'    => is_numeric( $request->get_param( 'category' ) ) ? 'term_id' : 'slug',
					'terms'    => is_numeric( $request->get_param( 'category' ) ) ? (int) $request->get_param( 'category' ) : sanitize_title( $request->get_param( 'category' ) ),
				),
			);
		}
		if ( $request->get_param( 'sku' ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => '_sku',
					'value' => sanitize_text_field( $request->get_param( 'sku' ) ),
				),
			);
		}

		$query    = new WP_Query( $args );
		$products = array_map( array( $this, 'format_product' ), $query->posts );
		$response = rest_ensure_response( $products );
		$response->header( 'X-WP-Total', (string) $query->found_posts );
		$response->header( 'X-WP-TotalPages', (string) $query->max_num_pages );
		return $response;
	}

	public function get_product( WP_REST_Request $request ) {
		$post = $this->get_product_post( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		return $this->product_response( $post );
	}

	public function update_product( WP_REST_Request $request ) {
		$this->normalize_product_request( $request );
		$validation = $this->validate_write_request( $request );
		if ( is_wp_error( $validation ) ) {
			return $validation;
		}
		$status = $this->resolve_write_status( $request );
		if ( is_wp_error( $status ) ) {
			return $status;
		}
		$post = $this->get_product_post( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}

		$draft = $this->get_meta_array( 'post', $post->ID, self::POST_DRAFT_META );
		$draft = $this->merge_draft_fields(
			$draft,
			$request,
			array( 'title', 'slug', 'description', 'short_description', 'seo_title', 'meta_description', 'focus_keyword', 'canonical_url', 'external_id' )
		);
		$draft['status']     = $status;
		$draft['updated_at'] = current_time( 'mysql', true );

		if ( isset( $draft['external_id'] ) && '' !== $draft['external_id'] ) {
			update_post_meta( $post->ID, self::POST_EXTERNAL_META, $draft['external_id'] );
		}
		update_post_meta( $post->ID, self::POST_DRAFT_META, $draft );
		update_post_meta( $post->ID, self::POST_STATUS_META, $status );

		if ( 'published' === $status ) {
			return $this->publish_product( $request );
		}
		return $this->product_response( get_post( $post->ID ) );
	}

	public function publish_product( WP_REST_Request $request ) {
		$post = $this->get_product_post( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$draft = $this->get_meta_array( 'post', $post->ID, self::POST_DRAFT_META );
		if ( empty( $draft ) ) {
			return new WP_Error( 'empty_product_draft', 'برای این محصول پیش‌نویسی ذخیره نشده است.', array( 'status' => 409 ) );
		}

		$post_args = array( 'ID' => $post->ID );
		$map       = array(
			'title'             => 'post_title',
			'slug'              => 'post_name',
			'description'       => 'post_content',
			'short_description' => 'post_excerpt',
		);
		foreach ( $map as $source => $target ) {
			if ( array_key_exists( $source, $draft ) ) {
				$post_args[ $target ] = $draft[ $source ];
			}
		}
		if ( count( $post_args ) > 1 ) {
			$result = wp_update_post( wp_slash( $post_args ), true );
			if ( is_wp_error( $result ) ) {
				return $result;
			}
		}
		$this->publish_yoast_meta( 'post', $post->ID, $draft );
		$this->rebuild_yoast_post_indexable( $post->ID );

		$draft['status']       = 'published';
		$draft['published_at'] = current_time( 'mysql', true );
		update_post_meta( $post->ID, self::POST_DRAFT_META, $draft );
		update_post_meta( $post->ID, self::POST_STATUS_META, 'published' );
		clean_post_cache( $post->ID );
		return $this->product_response( get_post( $post->ID ) );
	}

	public function preview_content( WP_REST_Request $request ) {
		$payload = $request->get_json_params();
		if ( ! is_array( $payload ) ) {
			$payload = $request->get_body_params();
		}
		$content = '';
		foreach ( array( 'content', 'description', 'short_description', 'intro_content', 'outro_content' ) as $field ) {
			if ( isset( $payload[ $field ] ) ) {
				$content .= ' ' . wp_strip_all_tags( (string) $payload[ $field ] );
			}
		}
		$yoast      = isset( $payload['yoast'] ) && is_array( $payload['yoast'] ) ? $payload['yoast'] : array();
		$content    = trim( preg_replace( '/\\s+/u', ' ', $content ) );
		$seo_title = isset( $payload['seo_title'] )
			? (string) $payload['seo_title']
			: ( isset( $yoast['title'] ) ? (string) $yoast['title'] : '' );
		$meta = isset( $payload['meta_description'] )
			? (string) $payload['meta_description']
			: ( isset( $payload['seo_description'] )
				? (string) $payload['seo_description']
				: ( isset( $yoast['description'] ) ? (string) $yoast['description'] : '' ) );
		$keyword = isset( $payload['focus_keyword'] )
			? sanitize_text_field( $payload['focus_keyword'] )
			: ( isset( $yoast['focus_keyword'] ) ? sanitize_text_field( $yoast['focus_keyword'] ) : '' );
		$word_count = $this->unicode_word_count( $content );

		$recommendations = array();
		if ( mb_strlen( $seo_title ) < 30 || mb_strlen( $seo_title ) > 60 ) {
			$recommendations[] = 'عنوان SEO بهتر است حدود ۳۰ تا ۶۰ کاراکتر باشد.';
		}
		if ( mb_strlen( $meta ) < 120 || mb_strlen( $meta ) > 160 ) {
			$recommendations[] = 'توضیحات متا بهتر است حدود ۱۲۰ تا ۱۶۰ کاراکتر باشد.';
		}
		if ( $keyword && false === mb_stripos( $content . ' ' . $seo_title . ' ' . $meta, $keyword ) ) {
			$recommendations[] = 'کلمه کلیدی کانونی در عنوان، متا یا محتوا دیده نشد.';
		}

		return array(
			'valid'           => (bool) $content,
			'word_count'      => $word_count,
			'title_length'    => mb_strlen( $seo_title ),
			'meta_length'     => mb_strlen( $meta ),
			'focus_keyword'   => $keyword,
			'recommendations' => $recommendations,
		);
	}

	private function merge_draft_fields( array $draft, WP_REST_Request $request, array $fields ) {
		$html_fields = array( 'description', 'short_description', 'intro_content', 'outro_content' );
		foreach ( $fields as $field ) {
			$value = $request->get_param( $field );
			if ( null === $value ) {
				continue;
			}
			if ( 'parent' === $field ) {
				$draft[ $field ] = (int) $value;
			} elseif ( in_array( $field, $html_fields, true ) ) {
				$draft[ $field ] = wp_kses_post( (string) $value );
			} elseif ( 'canonical_url' === $field ) {
				$draft[ $field ] = esc_url_raw( (string) $value );
			} elseif ( 'slug' === $field ) {
				$draft[ $field ] = sanitize_title( (string) $value );
			} else {
				$draft[ $field ] = sanitize_text_field( (string) $value );
			}
		}
		return $draft;
	}

	/**
	 * Accept documented flat category fields and common nested/alias payloads.
	 *
	 * Flat fields remain authoritative when both formats are present.
	 */
	private function normalize_category_request( WP_REST_Request $request ) {
		$aliases = array(
			'h1'              => 'name',
			'title'           => 'name',
			'content'         => 'description',
			'seo_description' => 'meta_description',
		);
		foreach ( $aliases as $source => $target ) {
			if ( null === $request->get_param( $target ) && null !== $request->get_param( $source ) ) {
				$request->set_param( $target, $request->get_param( $source ) );
			}
		}
		if ( null === $request->get_param( 'slug' ) && null !== $request->get_param( 'url' ) ) {
			$request->set_param( 'slug', $this->slug_from_value( $request->get_param( 'url' ) ) );
		}
		if ( null === $request->get_param( 'parent' ) && $request->get_param( 'category_url' ) ) {
			$parent_slug = $this->slug_from_value( $request->get_param( 'category_url' ) );
			$parent      = $parent_slug ? get_term_by( 'slug', $parent_slug, 'product_cat' ) : false;
			if ( $parent && ! is_wp_error( $parent ) ) {
				$request->set_param( 'parent', (int) $parent->term_id );
			} else {
				$request->set_param( '_chrono_parent_lookup_error', (string) $request->get_param( 'category_url' ) );
			}
		}

		$yoast = $request->get_param( 'yoast' );
		if ( ! is_array( $yoast ) ) {
			return;
		}
		$map = array(
			'title'            => 'seo_title',
			'description'      => 'meta_description',
			'focus_keyword'    => 'focus_keyword',
			'canonical_url'    => 'canonical_url',
		);
		foreach ( $map as $source => $target ) {
			if ( null === $request->get_param( $target ) && array_key_exists( $source, $yoast ) ) {
				$request->set_param( $target, $yoast[ $source ] );
			}
		}
	}

	/**
	 * Accept the same content and Yoast aliases for product updates.
	 */
	private function normalize_product_request( WP_REST_Request $request ) {
		if ( null === $request->get_param( 'description' ) && null !== $request->get_param( 'content' ) ) {
			$request->set_param( 'description', $request->get_param( 'content' ) );
		}
		if ( null === $request->get_param( 'meta_description' ) && null !== $request->get_param( 'seo_description' ) ) {
			$request->set_param( 'meta_description', $request->get_param( 'seo_description' ) );
		}
		if ( null === $request->get_param( 'slug' ) && null !== $request->get_param( 'url' ) ) {
			$request->set_param( 'slug', $this->slug_from_value( $request->get_param( 'url' ) ) );
		}

		$yoast = $request->get_param( 'yoast' );
		if ( ! is_array( $yoast ) ) {
			return;
		}
		$map = array(
			'title'            => 'seo_title',
			'description'      => 'meta_description',
			'focus_keyword'    => 'focus_keyword',
			'canonical_url'    => 'canonical_url',
		);
		foreach ( $map as $source => $target ) {
			if ( null === $request->get_param( $target ) && array_key_exists( $source, $yoast ) ) {
				$request->set_param( $target, $yoast[ $source ] );
			}
		}
	}

	/**
	 * Require callers to make the draft/publish decision explicitly.
	 */
	private function resolve_write_status( WP_REST_Request $request ) {
		$status      = $request->get_param( 'status' );
		$publish_now = $request->get_param( 'publish_now' );

		if ( null === $status && null === $publish_now ) {
			return new WP_Error(
				'missing_publish_choice',
				'یکی از فیلدهای status یا publish_now را ارسال کنید. برای پیش‌نویس status=draft و برای انتشار status=published است.',
				array( 'status' => 400 )
			);
		}

		$resolved = null !== $status ? sanitize_key( (string) $status ) : ( rest_sanitize_boolean( $publish_now ) ? 'published' : 'draft' );
		if ( null !== $status && null !== $publish_now ) {
			$boolean_status = rest_sanitize_boolean( $publish_now ) ? 'published' : 'draft';
			$status_group    = 'published' === $resolved ? 'published' : 'draft';
			if ( $boolean_status !== $status_group ) {
				return new WP_Error(
					'conflicting_publish_choice',
					'مقادیر status و publish_now با یکدیگر تناقض دارند.',
					array( 'status' => 400 )
				);
			}
		}

		return $resolved;
	}

	private function validate_write_request( WP_REST_Request $request ) {
		$has_writable_field = false;
		foreach ( array( 'name', 'title', 'slug', 'parent', 'external_id', 'description', 'short_description', 'intro_content', 'outro_content', 'seo_title', 'meta_description', 'focus_keyword', 'canonical_url' ) as $field ) {
			if ( null !== $request->get_param( $field ) ) {
				$has_writable_field = true;
				break;
			}
		}
		if ( ! $has_writable_field ) {
			return new WP_Error(
				'empty_write_payload',
				'هیچ فیلد محتوایی برای ذخیره ارسال نشده است. برای انتشار پیش‌نویس موجود از endpoint اختصاصی /publish استفاده کنید.',
				array( 'status' => 400 )
			);
		}

		if ( $request->get_param( '_chrono_parent_lookup_error' ) ) {
			return new WP_Error(
				'parent_category_not_found',
				'دسته والدِ مشخص‌شده در category_url پیدا نشد. آدرس یا slug دسته والد را بررسی کنید.',
				array( 'status' => 400 )
			);
		}
		$slug = $request->get_param( 'slug' );
		if ( null !== $slug && '' !== (string) $slug && '' === sanitize_title( (string) $slug ) ) {
			return new WP_Error( 'invalid_slug', 'مقدار slug معتبر نیست.', array( 'status' => 400 ) );
		}

		$canonical = (string) $request->get_param( 'canonical_url' );
		if ( '' !== $canonical ) {
			$parts  = wp_parse_url( $canonical );
			$scheme = is_array( $parts ) && isset( $parts['scheme'] ) ? strtolower( $parts['scheme'] ) : '';
			if ( ! is_array( $parts ) || empty( $parts['host'] ) || ! in_array( $scheme, array( 'http', 'https' ), true ) ) {
				return new WP_Error(
					'invalid_canonical_url',
					'canonical_url باید خالی یا یک آدرس کامل با http:// یا https:// باشد.',
					array( 'status' => 400 )
				);
			}
		}

		return true;
	}

	private function slug_from_value( $value ) {
		$value = trim( (string) $value );
		if ( '' === $value ) {
			return '';
		}
		$path = wp_parse_url( $value, PHP_URL_PATH );
		if ( is_string( $path ) && '' !== trim( $path, '/' ) ) {
			$segments = explode( '/', trim( $path, '/' ) );
			$value    = end( $segments );
		}
		return sanitize_title( $value );
	}

	private function category_dependency_error() {
		if ( ! class_exists( 'WooCommerce' ) || ! taxonomy_exists( 'product_cat' ) ) {
			return new WP_Error(
				'woocommerce_unavailable',
				'ووکامرس یا taxonomy دسته‌بندی محصول در دسترس نیست.',
				array( 'status' => 503 )
			);
		}
		return false;
	}

	private function publish_yoast_meta( $object_type, $object_id, array $draft ) {
		$map = array(
			'seo_title'        => '_yoast_wpseo_title',
			'meta_description' => '_yoast_wpseo_metadesc',
			'focus_keyword'    => '_yoast_wpseo_focuskw',
			'canonical_url'    => '_yoast_wpseo_canonical',
		);
		$yoast_term_values = array();
		if ( 'term' === $object_type && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			WPSEO_Taxonomy_Meta::get_instance();
			$current = WPSEO_Taxonomy_Meta::get_term_meta( (int) $object_id, 'product_cat' );
			if ( is_array( $current ) ) {
				$yoast_term_values = $current;
			}
		}

		$term_map = array(
			'seo_title'        => 'wpseo_title',
			'meta_description' => 'wpseo_desc',
			'focus_keyword'    => 'wpseo_focuskw',
			'canonical_url'    => 'wpseo_canonical',
		);
		foreach ( $map as $source => $target ) {
			if ( ! array_key_exists( $source, $draft ) ) {
				continue;
			}
			$value = 'canonical_url' === $source ? esc_url_raw( $draft[ $source ] ) : sanitize_text_field( $draft[ $source ] );
			if ( 'term' === $object_type ) {
				if ( isset( $term_map[ $source ] ) ) {
					$yoast_term_values[ $term_map[ $source ] ] = $value;
				}
				// Mirror values for compatibility with version 0.2.0 API data.
				update_term_meta( $object_id, $target, $value );
			} else {
				update_post_meta( $object_id, $target, $value );
			}
		}

		if ( 'term' === $object_type && $yoast_term_values && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			WPSEO_Taxonomy_Meta::set_values( (int) $object_id, 'product_cat', $yoast_term_values );
		}
	}

	/**
	 * Rebuild Yoast's cached representation after taxonomy SEO metadata changes.
	 */
	private function rebuild_yoast_term_indexable( $term_id ) {
		$class = '\\Yoast\\WP\\SEO\\Integrations\\Watchers\\Indexable_Term_Watcher';
		if ( ! function_exists( 'YoastSEO' ) || ! class_exists( $class ) ) {
			return;
		}
		try {
			$watcher = YoastSEO()->classes->get( $class );
			if ( $watcher && is_callable( array( $watcher, 'build_indexable' ) ) ) {
				$watcher->build_indexable( (int) $term_id );
			}
		} catch ( Throwable $exception ) {
			// Metadata is already saved; Yoast can rebuild the indexable later.
		}
	}

	/**
	 * Rebuild Yoast's cached representation after product SEO metadata changes.
	 */
	private function rebuild_yoast_post_indexable( $post_id ) {
		$class = '\\Yoast\\WP\\SEO\\Integrations\\Watchers\\Indexable_Post_Watcher';
		if ( ! function_exists( 'YoastSEO' ) || ! class_exists( $class ) ) {
			return;
		}
		try {
			$watcher = YoastSEO()->classes->get( $class );
			if ( $watcher && is_callable( array( $watcher, 'build_indexable' ) ) ) {
				$watcher->build_indexable( (int) $post_id );
			}
		} catch ( Throwable $exception ) {
			// Metadata is already saved; Yoast can rebuild the indexable later.
		}
	}

	private function category_response( $term, $http_status = 200 ) {
		$data     = $this->format_category( $term );
		$response = new WP_REST_Response( $data, $http_status );
		$response->header( 'X-Chrono-Status', $data['status'] );
		return $response;
	}

	private function product_response( $post, $http_status = 200 ) {
		$data     = $this->format_product( $post );
		$response = new WP_REST_Response( $data, $http_status );
		$response->header( 'X-Chrono-Status', $data['status'] );
		return $response;
	}

	private function get_product_category( $term_id ) {
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'category_not_found', 'دسته محصول با این ID پیدا نشد.', array( 'status' => 404 ) );
		}
		return $term;
	}

	private function get_product_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			return new WP_Error( 'product_not_found', 'محصول با این ID پیدا نشد.', array( 'status' => 404 ) );
		}
		return $post;
	}

	private function find_category_by_external_id( $external_id ) {
		$terms = get_terms(
			array(
				'taxonomy'   => 'product_cat',
				'hide_empty' => false,
				'number'     => 1,
				'meta_query' => array(
					array(
						'key'   => self::TERM_EXTERNAL_META,
						'value' => $external_id,
					),
				),
			)
		);
		return ( ! is_wp_error( $terms ) && $terms ) ? $terms[0] : false;
	}

	private function get_meta_array( $object_type, $object_id, $key ) {
		$value = 'term' === $object_type ? get_term_meta( $object_id, $key, true ) : get_post_meta( $object_id, $key, true );
		if ( is_array( $value ) ) {
			return $value;
		}
		if ( is_string( $value ) && '' !== $value ) {
			$decoded = json_decode( $value, true );
			return is_array( $decoded ) ? $decoded : array();
		}
		return array();
	}

	private function format_category( $term ) {
		$draft  = $this->get_meta_array( 'term', $term->term_id, self::TERM_DRAFT_META );
		$url    = get_term_link( $term );
		$status = (string) get_term_meta( $term->term_id, self::TERM_STATUS_META, true );
		$status = $status ? $status : 'published';
		$yoast  = $this->get_yoast_meta( 'term', $term->term_id );
		$applied = 'published' === $status && ! empty( $draft );
		$warnings = array();
		if ( $applied && ! defined( 'WPSEO_VERSION' ) && $this->draft_has_seo_fields( $draft ) ) {
			$warnings[] = 'Yoast SEO فعال نیست؛ محتوای اصلی منتشر شده اما خروجی متای Yoast تضمین نمی‌شود.';
		}
		if ( isset( $draft['intro_content'] ) || isset( $draft['outro_content'] ) ) {
			$warnings[] = 'intro_content و outro_content متای سفارشی هستند و نمایش آن‌ها به قالب سایت وابسته است؛ برای محتوای استاندارد دسته از description استفاده کنید.';
		}
		return array(
			'id'               => (int) $term->term_id,
			'external_id'      => (string) get_term_meta( $term->term_id, self::TERM_EXTERNAL_META, true ),
			'name'             => $term->name,
			'slug'             => $term->slug,
			'parent'           => (int) $term->parent,
			'count'            => (int) $term->count,
			'url'              => is_wp_error( $url ) ? '' : $url,
			'live_description' => $term->description,
			'intro_content'    => (string) get_term_meta( $term->term_id, '_chrono_intro_content', true ),
			'outro_content'    => (string) get_term_meta( $term->term_id, '_chrono_outro_content', true ),
			'status'           => $status,
			'workflow'         => array(
				'selected_status'        => $status,
				'has_saved_draft'        => ! empty( $draft ),
				'draft_applied_to_live'  => $applied,
				'term_record_exists'     => true,
				'taxonomy_supports_draft' => false,
				'message'                => $applied
					? 'محتوا و تنظیمات سئو روی نسخه زنده اعمال شده‌اند.'
					: ( 'published' === $status
						? 'این دسته پیش‌نویس افزونه ندارد و داده‌های فعلی آن مستقیماً زنده هستند.'
						: 'محتوا و تنظیمات سئو فقط در پیش‌نویس افزونه ذخیره شده‌اند؛ رکورد taxonomy برای دریافت ID وجود دارد اما تغییرات محتوا هنوز روی نسخه زنده اعمال نشده‌اند.' ),
			),
			'verification'     => array(
				'url_resolved'              => ! is_wp_error( $url ) && '' !== (string) $url,
				'yoast_active'               => defined( 'WPSEO_VERSION' ),
				'description_matches_draft' => $applied ? ( ! array_key_exists( 'description', $draft ) || $term->description === $draft['description'] ) : null,
				'yoast_title_matches_draft' => $applied ? ( ! array_key_exists( 'seo_title', $draft ) || $yoast['title'] === $draft['seo_title'] ) : null,
				'yoast_desc_matches_draft'  => $applied ? ( ! array_key_exists( 'meta_description', $draft ) || $yoast['description'] === $draft['meta_description'] ) : null,
			),
			'warnings'         => $warnings,
			'draft'            => $draft,
			'yoast'            => $yoast,
		);
	}

	private function format_product( $post ) {
		$terms      = get_the_terms( $post->ID, 'product_cat' );
		$categories = array();
		if ( is_array( $terms ) ) {
			foreach ( $terms as $term ) {
				$categories[] = array( 'id' => (int) $term->term_id, 'name' => $term->name, 'slug' => $term->slug );
			}
		}
		$draft  = $this->get_meta_array( 'post', $post->ID, self::POST_DRAFT_META );
		$status = (string) get_post_meta( $post->ID, self::POST_STATUS_META, true );
		$status = $status ? $status : 'published';
		$yoast  = $this->get_yoast_meta( 'post', $post->ID );
		$applied  = 'published' === $status && ! empty( $draft );
		$warnings = array();
		if ( 'published' === $status && ! defined( 'WPSEO_VERSION' ) && $this->draft_has_seo_fields( $draft ) ) {
			$warnings[] = 'Yoast SEO فعال نیست؛ محتوای اصلی منتشر شده اما خروجی متای Yoast تضمین نمی‌شود.';
		}
		return array(
			'id'                => (int) $post->ID,
			'external_id'       => (string) get_post_meta( $post->ID, self::POST_EXTERNAL_META, true ),
			'sku'               => (string) get_post_meta( $post->ID, '_sku', true ),
			'title'             => get_the_title( $post ),
			'slug'              => $post->post_name,
			'url'               => get_permalink( $post ),
			'live_description'  => $post->post_content,
			'short_description' => $post->post_excerpt,
			'categories'        => $categories,
			'status'            => $status,
			'workflow'          => array(
				'selected_status'       => $status,
				'has_saved_draft'       => ! empty( $draft ),
				'draft_applied_to_live' => $applied,
				'message'               => $applied
					? 'محتوا و تنظیمات سئو روی نسخه زنده اعمال شده‌اند.'
					: ( 'published' === $status
						? 'این محصول پیش‌نویس افزونه ندارد و داده‌های فعلی آن مستقیماً زنده هستند.'
						: 'تغییرات فقط در پیش‌نویس افزونه ذخیره شده‌اند و هنوز روی نسخه زنده اعمال نشده‌اند.' ),
			),
			'verification'      => array(
				'wordpress_post_status'     => $post->post_status,
				'yoast_active'               => defined( 'WPSEO_VERSION' ),
				'description_matches_draft' => $applied ? ( ! array_key_exists( 'description', $draft ) || $post->post_content === $draft['description'] ) : null,
				'yoast_title_matches_draft' => $applied ? ( ! array_key_exists( 'seo_title', $draft ) || $yoast['title'] === $draft['seo_title'] ) : null,
				'yoast_desc_matches_draft'  => $applied ? ( ! array_key_exists( 'meta_description', $draft ) || $yoast['description'] === $draft['meta_description'] ) : null,
			),
			'draft'             => $draft,
			'yoast'             => $yoast,
			'warnings'          => $warnings,
			'modified_gmt'      => get_post_modified_time( 'c', true, $post ),
		);
	}

	private function get_yoast_meta( $object_type, $object_id ) {
		if ( 'term' === $object_type && class_exists( 'WPSEO_Taxonomy_Meta' ) ) {
			WPSEO_Taxonomy_Meta::get_instance();
			$term_meta = WPSEO_Taxonomy_Meta::get_term_meta( (int) $object_id, 'product_cat' );
			if ( is_array( $term_meta ) ) {
				return array(
					'title'         => isset( $term_meta['wpseo_title'] ) ? (string) $term_meta['wpseo_title'] : '',
					'description'   => isset( $term_meta['wpseo_desc'] ) ? (string) $term_meta['wpseo_desc'] : '',
					'focus_keyword' => isset( $term_meta['wpseo_focuskw'] ) ? (string) $term_meta['wpseo_focuskw'] : '',
					'canonical_url' => isset( $term_meta['wpseo_canonical'] ) ? (string) $term_meta['wpseo_canonical'] : '',
				);
			}
		}
		$get = function( $key ) use ( $object_type, $object_id ) {
			return 'term' === $object_type ? get_term_meta( $object_id, $key, true ) : get_post_meta( $object_id, $key, true );
		};
		return array(
			'title'         => (string) $get( '_yoast_wpseo_title' ),
			'description'   => (string) $get( '_yoast_wpseo_metadesc' ),
			'focus_keyword' => (string) $get( '_yoast_wpseo_focuskw' ),
			'canonical_url' => (string) $get( '_yoast_wpseo_canonical' ),
		);
	}

	private function draft_has_seo_fields( array $draft ) {
		foreach ( array( 'seo_title', 'meta_description', 'focus_keyword', 'canonical_url' ) as $field ) {
			if ( array_key_exists( $field, $draft ) && '' !== (string) $draft[ $field ] ) {
				return true;
			}
		}
		return false;
	}

	private function unicode_word_count( $text ) {
		if ( '' === trim( $text ) ) {
			return 0;
		}
		$count = preg_match_all( "/[\\p{L}\\p{N}]+(?:[\\x{200C}'’_-][\\p{L}\\p{N}]+)*/u", $text, $matches );
		return false === $count ? 0 : (int) $count;
	}
}

new ChronoIran_SEO_Content_API();
