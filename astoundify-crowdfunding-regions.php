<?php
/**
 * Plugin Name: Crowdfunding by Astoundify - Predefined Regions
 * Plugin URI:  https://github.com/Astoundify/crowdfunding-regions
 * Description: Add predefined locations/regions to Crowdfunding by Astoundify plugin's submission form.
 * Author:      Astoundify
 * Author URI:  http://astoundify.com
 * Version:     1.1
 * Text Domain: acr
 */

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) exit;

class Astoundify_Crowdfunding_Regions {

	/**
	 * @var $instance
	 */
	private static $instance;

	/**
	 * Make sure only one instance is only running.
	 */
	public static function instance() {
		if ( ! isset ( self::$instance ) ) {
			self::$instance = new self;
		}

		return self::$instance;
	}

	/**
	 * Start things up.
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 */
	public function __construct() {
		$this->setup_globals();
		$this->setup_actions();
	}

	/**
	 * Set some smart defaults to class variables. Allow some of them to be
	 * filtered to allow for early overriding.
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 *
	 * @return void
	 */
	private function setup_globals() {
		$this->file         = __FILE__;
		
		$this->basename     = apply_filters( 'acr_plugin_basenname', plugin_basename( $this->file ) );
		$this->plugin_dir   = apply_filters( 'acr_plugin_dir_path',  plugin_dir_path( $this->file ) );
		$this->plugin_url   = apply_filters( 'acr_plugin_dir_url',   plugin_dir_url ( $this->file ) );

		$this->lang_dir     = apply_filters( 'acr_lang_dir',     trailingslashit( $this->plugin_dir . 'languages' ) );

		$this->domain       = 'acr'; 
	}

	/**
	 * Setup the default hooks and actions
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 *
	 * @return void
	 */
	private function setup_actions() {
		add_action( 'init', array( $this, 'register_post_taxonomy' ), 0 );
		
		add_filter( 'atcf_shortcode_submit_fields', array( $this, 'shortcode_submit_fields' ) );
		add_action( 'atcf_submit_process_after', array( $this, 'submit_process_after' ), 10, 4 );
		add_filter( 'atcf_shortcode_submit_saved_data_region', array( $this, 'saved_data_region' ), 10, 3 );
		
		add_action( 'template_redirect', array( $this, 'template_loader' ) );

		$this->load_textdomain();
	}

	/**
	 * Create the `job_listing_region` taxonomy.
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 */
	public function register_post_taxonomy() {
		$admin_capability = 'manage_product_terms';
		
		$singular  = __( 'Region', 'acr' );
		$plural    = __( 'Regions', 'acr' );

		register_taxonomy( 'campaign_region',
	        array( 'download' ),
	        array(
	            'hierarchical' 			=> true,
	            'update_count_callback' => '_update_post_term_count',
	            'label' 				=> $plural,
	            'labels' => array(
                    'name' 				=> $plural,
                    'singular_name' 	=> $singular,
                    'search_items' 		=> sprintf( __( 'Search %s', 'acr' ), $plural ),
                    'all_items' 		=> sprintf( __( 'All %s', 'acr' ), $plural ),
                    'parent_item' 		=> sprintf( __( 'Parent %s', 'acr' ), $singular ),
                    'parent_item_colon' => sprintf( __( 'Parent %s:', 'acr' ), $singular ),
                    'edit_item' 		=> sprintf( __( 'Edit %s', 'acr' ), $singular ),
                    'update_item' 		=> sprintf( __( 'Update %s', 'acr' ), $singular ),
                    'add_new_item' 		=> sprintf( __( 'Add New %s', 'acr' ), $singular ),
                    'new_item_name' 	=> sprintf( __( 'New %s Name', 'acr' ),  $singular )
            	),
	            'show_ui' 				=> true,
	            'query_var' 			=> true,
	            'has_archive'           => true,
	            'capabilities'			=> array(
	            	'manage_terms' 		=> $admin_capability,
	            	'edit_terms' 		=> $admin_capability,
	            	'delete_terms' 		=> $admin_capability,
	            	'assign_terms' 		=> $admin_capability,
	            ),
	            'rewrite' 				=>  array(
					'slug'         => _x( 'region', 'Campaign region slug - resave permalinks after changing this', 'acr' ),
					'with_front'   => false,
					'hierarchical' => false
				)
	        )
	    );
	}

	/**
	 * Add the field to the submission form.
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 */
	function shortcode_submit_fields( $fields ) {
		$regions = get_terms( 'campaign_region', array( 'hide_empty' => 0 ) );

		//if ( ! atcf_theme_supports( 'campaign-regions' ) || empty( $regions ) )
		//	return $fields;

		$fields[ 'region' ] = array(
			'label'       => __( 'Region', 'acr' ),
			'default'     => null,
			'type'        => 'select',
			'options'     => acr_get_regions_simple(),
			'editable'    => true,
			'required'    => false,
			'priority'    => 40
		);

		if ( true == apply_filters( 'acr_hide_location_field', false ) )
			unset( $fields[ 'location' ] );

		return $fields;
	}

	function saved_data_region( $data, $key, $campaign ) {
		if ( ! isset ( $campaign ) )
			return;

		$regions  = get_the_terms( $campaign->ID, 'campaign_region' );
		$selected = is_array( $regions ) ? current( array_keys( $regions ) ) : 0;

		return $selected;
	}

	/**
	 * When the form is submitted, update the data.
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 */
	function submit_process_after( $campaign, $postdata, $status, $fields ) {
		$region = isset ( $postdata[ 'region' ] ) ? $postdata[ 'region' ] : null;

		wp_set_post_terms( $campaign, array( $region ), 'campaign_region' );
	}

	/**
	 * If we are viewing the taxonomy archive, try loading some templates that
	 * might already exist.
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 *
	 * @return void
	 */
	public function template_loader() {
		if ( ! is_tax( 'campaign_region' ) )
			return;

		locate_template( apply_filters( 'acr_templates', array( 'archive-campaigns.php', 'taxonomy-download_category.php', 'taxonomy-download_tag.php', 'taxonomy-campaign_region.php' ) ), true );

		exit();
	}

	/**
	 * Loads the plugin language files
	 *
	 * @since Crowdfunding by Astoundify - Predefined Regions 1.0
	 */
	public function load_textdomain() {
		$locale        = apply_filters( 'plugin_locale', get_locale(), $this->domain );
		$mofile        = sprintf( '%1$s-%2$s.mo', $this->domain, $locale );

		$mofile_local  = $this->lang_dir . $mofile;
		$mofile_global = WP_LANG_DIR . '/' . $this->domain . '/' . $mofile;

		if ( file_exists( $mofile_global ) ) {
			return load_plugin_textdomain( $this->domain, false, $mofile_global );
		} elseif ( file_exists( $mofile_local ) ) {
			return load_plugin_textdomain( $this->domain, false, $mofile_local );
		}

		return false;
	}
}

/**
 * Start things up.
 *
 * Use this function instead of a global.
 *
 * $acr = acr();
 *
 * @since 1.0
 */
function acr() {
	return Astoundify_Crowdfunding_Regions::instance();
}

acr();

/**
 * Get regions (terms) helper.
 *
 * @since 1.0
 */
function acr_get_regions() {
	$locations = get_terms( 'campaign_region', apply_filters( 'acr_get_region_args', array( 'hide_empty' => 0 ) ) );

	return $locations;
}

/**
 * Create a key => value pair of term ID and term name.
 *
 * @since 1.0
 */
function acr_get_regions_simple() {
	$locations = acr_get_regions();
	$simple    = array();

	foreach ( $locations as $location ) {
		$simple[ $location->term_id ] = $location->name;
	}

	return apply_filters( 'acr_get_regions_simple', $simple );
}
