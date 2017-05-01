<?php
/**
 * Plugin Name: oEmbed Cache Clear
 * Plugin URI: https://karimeo.com/
 * Description: Clear oEmbed-Cache of Posts and Pages
 * Version: 1.0.0
 * Author: Karim El Ouerghemmi
 * Author URI: https://karimeo.com
 * Requires at least: 3.7.0
 * Tested up to: 4.7
 *
 * Text Domain: sumo-oembed
 * Domain Path: /languages/
 */

if ( ! defined( 'WPINC' ) ) {
	die;
}

class Sumo_OEmbed_Clearer {

	protected static $instance;

	protected $plugin_name;

	protected $version;

	protected $wp_embed;

	/**
	 * Ensures that we always have only one instance ( a.k.a Singleton ).
	 *
	 * @return object $instance Instance of Sumo_OEmbed_Clearer.
	 */
	public static function instance() {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Sumo_OEmbed_Clearer constructor.
	 */
	private function __construct() {

		$this->plugin_name = 'oembed-cache-clear';
		$this->version     = '1.0.0';

	}

	/**
	 * Start the plugin.
	 */
	public function run() {

		$this->register_hooks();

	}

	/**
	 * Registers all the hooks needed for the plugin.
	 */
	public function register_hooks() {

		//Load the textdomain on init
		add_action( 'init', array( $this, 'load_textdomain' ) );

		//Hooks for display
		add_action( 'post_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_action( 'page_row_actions', array( $this, 'add_row_action' ), 10, 2 );
		add_filter( 'bulk_actions-edit-post', array( $this, 'add_bulk_action' ) );
		add_filter( 'bulk_actions-edit-page', array( $this, 'add_bulk_action' ) );
		add_filter( 'bulk_post_updated_messages', array( $this, 'bulk_post_updated_messages' ), 10, 2 );

		//Hooks for handling the actions
		add_action( 'post_action_clear-oembed-cache', array( $this, 'handle_action' ) );
		add_action( 'handle_bulk_actions-edit-post', array( $this, 'handle_bulk_action' ), 10, 3 );
		add_action( 'handle_bulk_actions-edit-page', array( $this, 'handle_bulk_action' ), 10, 3 );

	}

	/**
	 * Adds a link into the actions row underneath of title in posts/pages listing.
	 *
	 * @param array $actions An array of row action links.
	 * @param WP_Post $post The post object.
	 *
	 * @return array The input with the new link added.
	 */
	public function add_row_action( $actions, $post ) {
		$post_id    = $post->ID;
		$post_title = $post->post_title;
		$url        = admin_url( 'post.php?post=' . $post_id . '&action=clear-oembed-cache' );

		$translation =  __( 'Clear oEmbed-Cache', 'sumo-oembed' );

		$actions['sumo-oembed-empty'] = "<a href='$url'
 											aria-label='Clear oEmbed-Cache of " . esc_attr( $post_title ) . "'> " .
		                                __( 'Clear oEmbed-Cache', 'sumo-oembed' )
		                                . "</a>";

		return $actions;
	}

	/**
	 * Adds an option into the the Bulk Actions list.
	 *
	 * @param array $actions An array of the available bulk actions.
	 *
	 * @return array The input with the new option added.
	 */
	public function add_bulk_action( $actions ) {

		$actions['clear-oembed-cache'] = __( 'Clear oEmbed-Cache', 'sumo-oembed' );

		return $actions;
	}

	/**
	 * Handles the single post/page cache clear request.
	 *
	 * Attached to Hook : post_action_clear-oembed-cache.
	 *
	 * @param int $post_id Post ID
	 */
	public function handle_action( $post_id ) {

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( "You don't seem to have the right permissions to do this." );
		}

		$sendback = wp_get_referer();

		$this->clear_cache( $post_id );

		wp_redirect( add_query_arg( array( 'updated' => 1, 'oembed-cleared' => 1, 'ids' => $post_id ), $sendback ) );
		exit();
	}

	/**
	 * Handles the cache clear when bulk action is used.
	 *
	 * Attached to Hook : bulk_actions-edit-post & bulk_actions-edit-page.
	 *
	 * @param string $sendback The redirect URL.
	 * @param string $doaction The action being taken. Only on 'clear-oembed-cache' we do something here.
	 * @param array $post_ids The IDs of the posts/pages the whose cache should be cleaned.
	 */
	public function handle_bulk_action( $sendback, $doaction, $post_ids ) {

		//Check if it's our concern
		if ( $doaction !== 'clear-oembed-cache' ) {
			return;
		}

		if ( ! current_user_can( 'edit_posts' ) ) {
			wp_die( "You don't seem to have the right permissions to do this." );
		}

		foreach ( $post_ids as $post_id ) {
			$this->clear_cache( $post_id );
		}

		wp_redirect( add_query_arg( array(
			'updated'        => count( $post_ids ),
			'oembed-cleared' => count( $post_ids ),
			'ids'            => join( ',', $post_ids )
		),
			$sendback ) );

		exit();
	}

	/**
	 * Modifies the the feedback messages of bulk actions if needed.
	 *
	 * Attached to Hook : bulk_post_updated_messages.
	 *
	 * @param array $bulk_messages Array of messages before modifying.
	 * @param array $bulk_counts Array containing count of posts involved in the action.
	 *
	 * @return array
	 */
	public function bulk_post_updated_messages( $bulk_messages, $bulk_counts ) {

		if ( isset( $_REQUEST['oembed-cleared'] ) ) {

			$bulk_messages['post']['updated'] = _n( "oEmbed-Cache of %s post cleared",
				"oEmbed-Cache of %s posts cleared", $bulk_counts['updated'], 'sumo-oembed' );

			$bulk_messages['page']['updated'] = _n( "oEmbed-Cache of %s page cleared",
				"oEmbed-Cache of %s pages cleared", $bulk_counts['updated'], 'sumo-oembed' );

			//This sort of thing shouldn't be in a Filter. However, no other solution possible.
			$_SERVER['REQUEST_URI'] = remove_query_arg( 'oembed-cleared', $_SERVER['REQUEST_URI'] );
		}

		return $bulk_messages;
	}

	/**
	 * Clears the cache using a method of WP_Embed.
	 *
	 * @param int $post_id
	 */
	private function clear_cache( $post_id ) {

		if ( $this->wp_embed === null ) {
			$this->wp_embed = new WP_Embed();
		}

		$this->wp_embed->delete_oembed_caches( $post_id );

	}

	public function load_textdomain() {

		load_plugin_textdomain( 'sumo-oembed', false, dirname( plugin_basename( __FILE__ ) ) . '/languages' );

	}

}

$sumo_oembed_clearer = Sumo_OEmbed_Clearer::instance();
$sumo_oembed_clearer->run();