<?php
/**
 * WP AI Content Generator
 *
 * @package           WPAIContentGenerator
 * @author            That Freelancer Guy
 * @copyright         2024 That Freelancer Guy
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name:       WP AI Content Generator
 * Plugin URI:        https://github.com/that-freelancer-guy/wp-ai-content-generator
 * Description:       Generate article content using AI APIs for WordPress.
 * Version:           1.0.0
 * Requires at least: 5.2
 * Requires PHP:      7.2
 * Author:            That Freelancer Guy
 * Author URI:        https://github.com/that-freelancer-guy/
 * Text Domain:       wp-ai-content-generator
 * License:           GPL v2 or later
 * License URI:       http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI:        https://github.com/that-freelancer-guy/wp-ai-content-generator
 */

namespace WPAI_Content_Generator;

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
    die;
}

define( 'WPAI_VERSION', '1.0.0' );
define( 'WPAI_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'WPAI_PLUGIN_URL', plugin_dir_url( __FILE__ ) );

require_once WPAI_PLUGIN_DIR . 'includes/class-ai-api.php';
require_once WPAI_PLUGIN_DIR . 'includes/class-content-generator.php';
require_once WPAI_PLUGIN_DIR . 'includes/class-admin-settings.php';

/**
 * Initialize the plugin.
 */
function init() {
    new Admin_Settings();
    new Content_Generator();
}
add_action( 'plugins_loaded', __NAMESPACE__ . '\init' );

/**
 * Enqueue scripts and styles.
 */
function enqueue_scripts() {
    wp_enqueue_script(
        'wpai-editor-integration',
        WPAI_PLUGIN_URL . 'assets/js/editor-integration.js',
        array( 'wp-blocks', 'wp-editor', 'wp-components', 'jquery' ),
        WPAI_VERSION,
        true
    );
    wp_enqueue_style(
        'wpai-admin-style',
        WPAI_PLUGIN_URL . 'assets/css/admin-style.css',
        array(),
        WPAI_VERSION
    );

    wp_localize_script(
        'wpai-editor-integration',
        'wpai_ajax',
        array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'wpai_generate_content' ),
            'i18n'     => array(
                'empty_prompt'     => __( 'Please enter a prompt.', 'wp-ai-content-generator' ),
                'generation_error' => __( 'Error generating content: ', 'wp-ai-content-generator' ),
                'server_error'     => __( 'Error communicating with the server. Please check the console for more details.', 'wp-ai-content-generator' ),
            ),
        )
    );
}
add_action( 'enqueue_block_editor_assets', __NAMESPACE__ . '\enqueue_scripts' );
add_action( 'admin_enqueue_scripts', __NAMESPACE__ . '\enqueue_scripts' );

/**
 * Activate the plugin.
 */
function activate() {
    // Activation code here
}
register_activation_hook( __FILE__, __NAMESPACE__ . '\activate' );

/**
 * Deactivate the plugin.
 */
function deactivate() {
    // Deactivation code here
}
register_deactivation_hook( __FILE__, __NAMESPACE__ . '\deactivate' );