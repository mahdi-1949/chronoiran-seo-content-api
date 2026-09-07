<?php
/**
 * Plugin Name: ChronoIran SEO Content API
 * Description: Versioned REST API for drafting, reviewing, and publishing WooCommerce category and product SEO content.
 * Version: 0.2.0
 * Author: ChronoIran
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Text Domain: chronoiran-seo-api
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

final class ChronoIran_SEO_Content_API {
	const VERSION             = '0.2.0';
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
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/categories',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'list_categories' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::CREATABLE,
					'callback'            => array( $this, 'create_or_update_category' ),
					'permission_callback' => array( $this, 'can_access' ),
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
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_category' ),
					'permission_callback' => array( $this, 'can_access' ),
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
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products',
			array(
				'methods'             => WP_REST_Server::READABLE,
				'callback'            => array( $this, 'list_products' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/products/(?P<id>\\d+)',
			array(
				array(
					'methods'             => WP_REST_Server::READABLE,
					'callback'            => array( $this, 'get_product' ),
					'permission_callback' => array( $this, 'can_access' ),
				),
				array(
					'methods'             => WP_REST_Server::EDITABLE,
					'callback'            => array( $this, 'update_product' ),
					'permission_callback' => array( $this, 'can_access' ),
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
				'permission_callback' => array( $this, 'can_access' ),
			)
		);

		register_rest_route(
			self::REST_NAMESPACE,
			'/preview',
			array(
				'methods'             => WP_REST_Server::CREATABLE,
				'callback'            => array( $this, 'preview_content' ),
				'permission_callback' => array( $this, 'can_access' ),
			)
		);
	}

	public function can_access( WP_REST_Request $request ) {
		return (bool) apply_filters( 'chronoiran_seo_api_permission', true, $request );
	}

	public function health() {
		return array(
			'ok'          => true,
			'version'     => self::VERSION,
			'woocommerce' => class_exists( 'WooCommerce' ),
			'yoast'       => defined( 'WPSEO_VERSION' ),
			'time_utc'    => current_time( 'mysql', true ),
		);
	}

	private function category_write_args() {
		return array(
			'name'             => array( 'type' => 'string' ),
			'slug'             => array( 'type' => 'string' ),
			'parent'           => array( 'type' => 'integer', 'minimum' => 0 ),
			'external_id'      => array( 'type' => 'string' ),
			'description'      => array( 'type' => 'string' ),
			'intro_content'    => array( 'type' => 'string' ),
			'outro_content'    => array( 'type' => 'string' ),
			'seo_title'        => array( 'type' => 'string' ),
			'meta_description' => array( 'type' => 'string' ),
			'focus_keyword'    => array( 'type' => 'string' ),
			'canonical_url'    => array( 'type' => 'string', 'format' => 'uri' ),
			'status'           => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'published' ) ),
		);
	}

	private function product_write_args() {
		return array(
			'title'             => array( 'type' => 'string' ),
			'slug'              => array( 'type' => 'string' ),
			'external_id'       => array( 'type' => 'string' ),
			'description'       => array( 'type' => 'string' ),
			'short_description' => array( 'type' => 'string' ),
			'seo_title'         => array( 'type' => 'string' ),
			'meta_description'  => array( 'type' => 'string' ),
			'focus_keyword'     => array( 'type' => 'string' ),
			'canonical_url'     => array( 'type' => 'string', 'format' => 'uri' ),
			'status'            => array( 'type' => 'string', 'enum' => array( 'draft', 'pending', 'published' ) ),
		);
	}

	public function list_categories( WP_REST_Request $request ) {
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
		if ( null !== $request->get_param( 'parent' ) ) {
			$args['parent'] = (int) $request->get_param( 'parent' );
		}
		if ( $request->get_param( 'status' ) ) {
			$args['meta_query'] = array(
				array(
					'key'   => self::TERM_STATUS_META,
					'value' => sanitize_key( $request->get_param( 'status' ) ),
				),
			);
		}

		$terms = get_terms( $args );
		if ( is_wp_error( $terms ) ) {
			return new WP_Error( 'category_list_failed', $terms->get_error_message(), array( 'status' => 500 ) );
		}
		$response = rest_ensure_response( array_map( array( $this, 'format_category' ), $terms ) );
		$response->header( 'X-WP-Page', (string) $page );
		$response->header( 'X-WP-Per-Page', (string) $per_page );
		return $response;
	}

	public function get_category( WP_REST_Request $request ) {
		$term = $this->get_product_category( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		return $this->format_category( $term );
	}

	public function create_or_update_category( WP_REST_Request $request ) {
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
			return new WP_Error( 'missing_name', 'Category name is required.', array( 'status' => 400 ) );
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
		$status              = $request->get_param( 'status' ) ? sanitize_key( $request->get_param( 'status' ) ) : 'draft';
		$draft['status']     = $status;
		$draft['updated_at'] = current_time( 'mysql', true );

		if ( isset( $draft['external_id'] ) && '' !== $draft['external_id'] ) {
			update_term_meta( $term->term_id, self::TERM_EXTERNAL_META, $draft['external_id'] );
		}
		update_term_meta( $term->term_id, self::TERM_DRAFT_META, $draft );
		update_term_meta( $term->term_id, self::TERM_STATUS_META, $status );

		if ( 'published' === $status ) {
			return $this->publish_category( $request );
		}
		return new WP_REST_Response( $this->format_category( get_term( $term->term_id, 'product_cat' ) ), $created ? 201 : 200 );
	}

	public function publish_category( WP_REST_Request $request ) {
		$term = $this->get_product_category( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $term ) ) {
			return $term;
		}
		$draft = $this->get_meta_array( 'term', $term->term_id, self::TERM_DRAFT_META );
		if ( empty( $draft ) ) {
			return new WP_Error( 'empty_category_draft', 'No category SEO draft exists.', array( 'status' => 409 ) );
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
		return $this->format_category( get_term( $term->term_id, 'product_cat' ) );
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
		return $this->format_product( $post );
	}

	public function update_product( WP_REST_Request $request ) {
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
		$status              = $request->get_param( 'status' ) ? sanitize_key( $request->get_param( 'status' ) ) : 'draft';
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
		return $this->format_product( get_post( $post->ID ) );
	}

	public function publish_product( WP_REST_Request $request ) {
		$post = $this->get_product_post( (int) $request->get_param( 'id' ) );
		if ( is_wp_error( $post ) ) {
			return $post;
		}
		$draft = $this->get_meta_array( 'post', $post->ID, self::POST_DRAFT_META );
		if ( empty( $draft ) ) {
			return new WP_Error( 'empty_product_draft', 'No product SEO draft exists.', array( 'status' => 409 ) );
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

		$draft['status']       = 'published';
		$draft['published_at'] = current_time( 'mysql', true );
		update_post_meta( $post->ID, self::POST_DRAFT_META, $draft );
		update_post_meta( $post->ID, self::POST_STATUS_META, 'published' );
		clean_post_cache( $post->ID );
		return $this->format_product( get_post( $post->ID ) );
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
		$content     = trim( preg_replace( '/\\s+/u', ' ', $content ) );
		$seo_title  = isset( $payload['seo_title'] ) ? (string) $payload['seo_title'] : '';
		$meta       = isset( $payload['meta_description'] ) ? (string) $payload['meta_description'] : '';
		$keyword    = isset( $payload['focus_keyword'] ) ? sanitize_text_field( $payload['focus_keyword'] ) : '';
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

	private function publish_yoast_meta( $object_type, $object_id, array $draft ) {
		$map = array(
			'seo_title'        => '_yoast_wpseo_title',
			'meta_description' => '_yoast_wpseo_metadesc',
			'focus_keyword'    => '_yoast_wpseo_focuskw',
			'canonical_url'    => '_yoast_wpseo_canonical',
		);
		foreach ( $map as $source => $target ) {
			if ( ! array_key_exists( $source, $draft ) ) {
				continue;
			}
			$value = 'canonical_url' === $source ? esc_url_raw( $draft[ $source ] ) : sanitize_text_field( $draft[ $source ] );
			if ( 'term' === $object_type ) {
				update_term_meta( $object_id, $target, $value );
			} else {
				update_post_meta( $object_id, $target, $value );
			}
		}
	}

	private function get_product_category( $term_id ) {
		$term = get_term( $term_id, 'product_cat' );
		if ( ! $term || is_wp_error( $term ) ) {
			return new WP_Error( 'category_not_found', 'Product category not found.', array( 'status' => 404 ) );
		}
		return $term;
	}

	private function get_product_post( $post_id ) {
		$post = get_post( $post_id );
		if ( ! $post || 'product' !== $post->post_type ) {
			return new WP_Error( 'product_not_found', 'Product not found.', array( 'status' => 404 ) );
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
		$draft = $this->get_meta_array( 'term', $term->term_id, self::TERM_DRAFT_META );
		$url   = get_term_link( $term );
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
			'status'           => get_term_meta( $term->term_id, self::TERM_STATUS_META, true ) ? get_term_meta( $term->term_id, self::TERM_STATUS_META, true ) : 'published',
			'draft'            => $draft,
			'yoast'            => $this->get_yoast_meta( 'term', $term->term_id ),
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
			'status'            => get_post_meta( $post->ID, self::POST_STATUS_META, true ) ? get_post_meta( $post->ID, self::POST_STATUS_META, true ) : 'published',
			'draft'             => $this->get_meta_array( 'post', $post->ID, self::POST_DRAFT_META ),
			'yoast'             => $this->get_yoast_meta( 'post', $post->ID ),
			'modified_gmt'      => get_post_modified_time( 'c', true, $post ),
		);
	}

	private function get_yoast_meta( $object_type, $object_id ) {
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

	private function unicode_word_count( $text ) {
		if ( '' === trim( $text ) ) {
			return 0;
		}
		$count = preg_match_all( "/[\\p{L}\\p{N}]+(?:[\\x{200C}'’_-][\\p{L}\\p{N}]+)*/u", $text, $matches );
		return false === $count ? 0 : (int) $count;
	}
}

new ChronoIran_SEO_Content_API();
