<?php
/**
 * PHPUnit bootstrap for Peptide Repo Core tests.
 *
 * Loads WordPress stubs so unit tests can reference WP functions/classes
 * without requiring a full WordPress installation.
 *
 * @package PeptideRepoCore\Tests
 */

declare(strict_types=1);

// Composer autoloader.
require_once dirname( __DIR__ ) . '/vendor/autoload.php';

// ── WordPress constants ───────────────────────────────────────────────.

if ( ! defined( 'ABSPATH' ) ) {
	define( 'ABSPATH', '/tmp/wordpress/' );
}
if ( ! defined( 'WPINC' ) ) {
	define( 'WPINC', 'wp-includes' );
}
if ( ! defined( 'PR_CORE_VERSION' ) ) {
	define( 'PR_CORE_VERSION', '0.7.0-test' );
}
if ( ! defined( 'PR_CORE_PLUGIN_DIR' ) ) {
	define( 'PR_CORE_PLUGIN_DIR', dirname( __DIR__ ) . '/' );
}
if ( ! defined( 'PR_CORE_PLUGIN_URL' ) ) {
	define( 'PR_CORE_PLUGIN_URL', 'https://example.com/wp-content/plugins/peptide-repo-core/' );
}
if ( ! defined( 'PR_CORE_PLUGIN_FILE' ) ) {
	define( 'PR_CORE_PLUGIN_FILE', PR_CORE_PLUGIN_DIR . 'peptide-repo-core.php' );
}
if ( ! defined( 'PR_CORE_TARGET_SCHEMA_VERSION' ) ) {
	define( 'PR_CORE_TARGET_SCHEMA_VERSION', 4 );
}
if ( ! defined( 'DAY_IN_SECONDS' ) ) {
	define( 'DAY_IN_SECONDS', 86400 );
}
if ( ! defined( 'HOUR_IN_SECONDS' ) ) {
	define( 'HOUR_IN_SECONDS', 3600 );
}
if ( ! defined( 'MINUTE_IN_SECONDS' ) ) {
	define( 'MINUTE_IN_SECONDS', 60 );
}

// ── Global harness state (reset between test cases via setUp) ─────────.

$GLOBALS['pr_core_test_state'] = [
	'existing_post_types'   => [],
	'existing_taxonomies'   => [],
	'registered_post_types' => [],
	'registered_taxonomies' => [],
	'registered_meta'       => [],
	'added_actions'         => [],
	'added_filters'         => [],
];
$GLOBALS['pr_core_options']      = [];
$GLOBALS['pr_core_cron_calls']   = [];
$GLOBALS['pr_test_postmeta']     = [];
$GLOBALS['pr_test_posts']        = [];
$GLOBALS['pr_test_updated_meta'] = [];
$GLOBALS['pr_test_is_singular']  = false;
$GLOBALS['pr_test_singular_type'] = '';
$GLOBALS['pr_test_the_id']        = 0;
$GLOBALS['pr_test_peptide_dto']   = null;

// ── WordPress function stubs ──────────────────────────────────────────.

if ( ! function_exists( 'trailingslashit' ) ) {
	function trailingslashit( string $path ): string {
		return rtrim( $path, '/\\' ) . '/';
	}
}
if ( ! function_exists( 'plugin_dir_path' ) ) {
	function plugin_dir_path( string $file ): string {
		return trailingslashit( dirname( $file ) );
	}
}
if ( ! function_exists( 'plugin_dir_url' ) ) {
	function plugin_dir_url( string $file ): string {
		return 'https://example.com/wp-content/plugins/' . basename( dirname( $file ) ) . '/';
	}
}
if ( ! function_exists( 'plugin_basename' ) ) {
	function plugin_basename( string $file ): string {
		return basename( dirname( $file ) ) . '/' . basename( $file );
	}
}
if ( ! function_exists( '__' ) ) {
	function __( string $text, string $domain = 'default' ): string {
		return $text;
	}
}
if ( ! function_exists( 'esc_html' ) ) {
	function esc_html( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_html__' ) ) {
	function esc_html__( string $text, string $domain = 'default' ): string {
		return esc_html( $text );
	}
}
if ( ! function_exists( 'esc_attr' ) ) {
	function esc_attr( string $text ): string {
		return htmlspecialchars( $text, ENT_QUOTES, 'UTF-8' );
	}
}
if ( ! function_exists( 'esc_url_raw' ) ) {
	function esc_url_raw( string $url ): string {
		return filter_var( $url, FILTER_SANITIZE_URL ) ?: '';
	}
}
if ( ! function_exists( 'sanitize_text_field' ) ) {
	function sanitize_text_field( $value ): string {
		return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
	}
}
if ( ! function_exists( 'sanitize_textarea_field' ) ) {
	function sanitize_textarea_field( $value ): string {
		return is_scalar( $value ) ? trim( strip_tags( (string) $value ) ) : '';
	}
}
if ( ! function_exists( 'wp_strip_all_tags' ) ) {
	function wp_strip_all_tags( $value ): string {
		return strip_tags( (string) $value );
	}
}
if ( ! function_exists( 'absint' ) ) {
	function absint( $value ): int {
		return abs( (int) $value );
	}
}
if ( ! function_exists( 'wp_json_encode' ) ) {
	function wp_json_encode( $data, int $flags = 0, int $depth = 512 ): string {
		return json_encode( $data, $flags, $depth ) ?: '[]';
	}
}
if ( ! function_exists( 'is_wp_error' ) ) {
	function is_wp_error( $thing ): bool {
		return $thing instanceof WP_Error;
	}
}
if ( ! function_exists( 'apply_filters' ) ) {
	function apply_filters( string $tag, $value ) {
		return $value;
	}
}
if ( ! function_exists( 'current_user_can' ) ) {
	function current_user_can( string $capability ): bool {
		return true;
	}
}
if ( ! function_exists( 'home_url' ) ) {
	function home_url( string $path = '' ): string {
		return 'https://peptiderepo.com' . $path;
	}
}
if ( ! function_exists( 'error_log' ) ) {
	function error_log( string $msg ): bool {
		return true;
	}
}
if ( ! function_exists( 'is_email' ) ) {
	function is_email( string $email ): bool {
		return (bool) filter_var( $email, FILTER_VALIDATE_EMAIL );
	}
}

// ── Option / cron stubs ───────────────────────────────────────────────.

if ( ! function_exists( 'get_option' ) ) {
	function get_option( string $key, $default = false ) {
		if ( isset( $GLOBALS['pr_core_options'][ $key ] ) ) {
			return $GLOBALS['pr_core_options'][ $key ];
		}
		return $default;
	}
}
if ( ! function_exists( 'update_option' ) ) {
	function update_option( string $key, $value ): bool {
		$GLOBALS['pr_core_options'][ $key ] = $value;
		return true;
	}
}
if ( ! function_exists( 'wp_clear_scheduled_hook' ) ) {
	function wp_clear_scheduled_hook( string $hook ): void {
		$GLOBALS['pr_core_cron_calls'][] = [ 'action' => 'clear', 'hook' => $hook ];
	}
}
if ( ! function_exists( 'wp_schedule_event' ) ) {
	function wp_schedule_event( int $timestamp, string $recurrence, string $hook ): void {
		$GLOBALS['pr_core_cron_calls'][] = [
			'action'     => 'schedule',
			'hook'       => $hook,
			'recurrence' => $recurrence,
		];
	}
}
if ( ! function_exists( 'register_setting' ) ) {
	function register_setting( string $group, string $option, array $args = [] ): void {}
}
if ( ! function_exists( 'add_settings_section' ) ) {
	function add_settings_section( string $id, string $title, $cb, string $page ): void {}
}
if ( ! function_exists( 'add_settings_field' ) ) {
	function add_settings_field( string $id, string $title, $cb, string $page, string $section = '' ): void {}
}

// ── Post meta stubs ───────────────────────────────────────────────────.

if ( ! function_exists( 'get_post_meta' ) ) {
	function get_post_meta( $post_id, string $key = '', bool $single = false ) {
		if ( $single ) {
			return $GLOBALS['pr_test_postmeta'][ (int) $post_id ][ $key ] ?? '';
		}
		return array_values( $GLOBALS['pr_test_postmeta'][ (int) $post_id ] ?? [] );
	}
}
if ( ! function_exists( 'update_post_meta' ) ) {
	function update_post_meta( int $post_id, string $key, $value ): bool {
		$GLOBALS['pr_test_postmeta'][ $post_id ][ $key ] = $value;
		$GLOBALS['pr_test_updated_meta'][]               = [
			'post_id' => $post_id,
			'key'     => $key,
			'value'   => $value,
		];
		return true;
	}
}
if ( ! function_exists( 'get_post' ) ) {
	function get_post( int $id ) {
		return $GLOBALS['pr_test_posts'][ $id ] ?? null;
	}
}
if ( ! function_exists( 'get_posts' ) ) {
	function get_posts( array $args = [] ): array {
		return $GLOBALS['pr_test_all_post_ids'] ?? [];
	}
}

// ── CPT / taxonomy stubs ──────────────────────────────────────────────.

if ( ! function_exists( 'post_type_exists' ) ) {
	function post_type_exists( string $post_type ): bool {
		return in_array( $post_type, $GLOBALS['pr_core_test_state']['existing_post_types'], true );
	}
}
if ( ! function_exists( 'taxonomy_exists' ) ) {
	function taxonomy_exists( string $taxonomy ): bool {
		return in_array( $taxonomy, $GLOBALS['pr_core_test_state']['existing_taxonomies'], true );
	}
}
if ( ! function_exists( 'register_post_type' ) ) {
	function register_post_type( string $post_type, array $args = [] ) {
		$GLOBALS['pr_core_test_state']['registered_post_types'][ $post_type ] = $args;
		$GLOBALS['pr_core_test_state']['existing_post_types'][]               = $post_type;
		return (object) [ 'name' => $post_type, 'args' => $args ];
	}
}
if ( ! function_exists( 'register_taxonomy' ) ) {
	function register_taxonomy( string $taxonomy, $object_type, array $args = [] ): void {
		$GLOBALS['pr_core_test_state']['registered_taxonomies'][ $taxonomy ] = [
			'object_type' => $object_type,
			'args'        => $args,
		];
		$GLOBALS['pr_core_test_state']['existing_taxonomies'][] = $taxonomy;
	}
}
if ( ! function_exists( 'register_post_meta' ) ) {
	function register_post_meta( string $post_type, string $key, array $args = [] ): bool {
		$GLOBALS['pr_core_test_state']['registered_meta'][ $post_type ][ $key ] = $args;
		return true;
	}
}
if ( ! function_exists( 'add_action' ) ) {
	function add_action( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['pr_core_test_state']['added_actions'][] = [
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		];
		return true;
	}
}
if ( ! function_exists( 'add_filter' ) ) {
	function add_filter( string $hook, $callback, int $priority = 10, int $accepted_args = 1 ): bool {
		$GLOBALS['pr_core_test_state']['added_filters'][] = [
			'hook'     => $hook,
			'callback' => $callback,
			'priority' => $priority,
		];
		return true;
	}
}

// ── Singular / permalink stubs ────────────────────────────────────────.

if ( ! function_exists( 'is_singular' ) ) {
	function is_singular( $type = '' ): bool {
		if ( '' === $type ) {
			return $GLOBALS['pr_test_is_singular'];
		}
		return $GLOBALS['pr_test_is_singular']
			&& ( $GLOBALS['pr_test_singular_type'] === $type );
	}
}
if ( ! function_exists( 'get_the_ID' ) ) {
	function get_the_ID(): int {
		return $GLOBALS['pr_test_the_id'];
	}
}
if ( ! function_exists( 'get_permalink' ) ) {
	function get_permalink( $post_id = null ): string {
		return 'https://peptiderepo.com/peptides/bpc-157/';
	}
}
if ( ! function_exists( 'get_post_modified_time' ) ) {
	function get_post_modified_time( $format, $gmt = false, $post = null ) {
		return '2026-04-25';
	}
}
if ( ! function_exists( 'get_userdata' ) ) {
	function get_userdata( int $id ) {
		return false;
	}
}
if ( ! function_exists( 'get_author_posts_url' ) ) {
	function get_author_posts_url( int $id ): string {
		return 'https://peptiderepo.com/author/' . $id;
	}
}

// ── WP_Error stub ─────────────────────────────────────────────────────.

if ( ! class_exists( 'WP_Error' ) ) {
	class WP_Error {
		/** @var array<string, string[]> */
		public array $errors = [];
		/** @var array<string, mixed> */
		public array $error_data = [];

		public function __construct( string $code = '', string $message = '', $data = '' ) {
			if ( $code ) {
				$this->errors[ $code ][] = $message;
				if ( $data ) {
					$this->error_data[ $code ] = $data;
				}
			}
		}

		public function get_error_code(): string {
			$codes = array_keys( $this->errors );
			return $codes ? $codes[0] : '';
		}

		public function get_error_message( string $code = '' ): string {
			if ( ! $code ) {
				$code = $this->get_error_code();
			}
			return isset( $this->errors[ $code ] ) ? $this->errors[ $code ][0] : '';
		}
	}
}


// ── WP_Post stub ──────────────────────────────────────────────────────.

if ( ! class_exists( 'WP_Post' ) ) {
	class WP_Post {
		public int    $ID           = 0;
		public string $post_author  = '0';
		public string $post_date    = '';
		public string $post_title   = '';
		public string $post_content = '';
		public string $post_excerpt = '';
		public string $post_status  = 'publish';
		public string $post_name    = '';
		public string $post_type    = 'post';
		public string $post_modified = '';

		/**
		 * @param array<string, mixed>|object $data
		 */
		public function __construct( $data = [] ) {
			foreach ( (array) $data as $key => $value ) {
				$this->$key = $value;
			}
		}
	}
}

if ( ! function_exists( 'wp_get_post_terms' ) ) {
	function wp_get_post_terms( int $post_id, string $taxonomy, array $args = [] ): array {
		return [];
	}
}

if ( ! function_exists( 'sanitize_title' ) ) {
	function sanitize_title( string $title ): string {
		return strtolower( trim( preg_replace( '/[^a-z0-9-]/', '-', strtolower( $title ) ) ?: '', '-' ) );
	}
}

if ( ! function_exists( 'wp_count_posts' ) ) {
	function wp_count_posts( string $type = 'post' ) {
		return (object) [ 'publish' => 0 ];
	}
}

// ── Load the plugin's autoloader ──────────────────────────────────────.

require_once PR_CORE_PLUGIN_DIR . 'includes/class-pr-core-autoloader.php';
PR_Core_Autoloader::register();
