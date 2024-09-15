<?php
/**
 * WPAI Admin Settings
 *
 * @package WPAIContentGenerator
 */

namespace WPAI_Content_Generator;

/**
 * Class Admin_Settings
 */
class Admin_Settings {

    /**
     * Constructor.
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_settings_page' ) );
        add_action( 'admin_init', array( $this, 'register_settings' ) );
    }

    /**
     * Add settings page to the admin menu.
     */
    public function add_settings_page() {
        add_options_page(
            __( 'WP AI Content Generator Settings', 'wp-ai-content-generator' ),
            __( 'WP AI Generator', 'wp-ai-content-generator' ),
            'manage_options',
            'wpai-settings',
            array( $this, 'render_settings_page' )
        );
    }

    /**
     * Register plugin settings.
     */
    public function register_settings() {
        register_setting( 'wpai_settings_group', 'wpai_api_key', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => '',
        ) );
        register_setting( 'wpai_settings_group', 'wpai_model', array(
            'type'              => 'string',
            'sanitize_callback' => 'sanitize_text_field',
            'default'           => 'default-model',
        ) );
        register_setting( 'wpai_settings_group', 'wpai_temperature', array(
            'type'              => 'number',
            'sanitize_callback' => array( $this, 'sanitize_temperature' ),
            'default'           => 0.7,
        ) );
        register_setting( 'wpai_settings_group', 'wpai_max_tokens', array(
            'type'              => 'integer',
            'sanitize_callback' => array( $this, 'sanitize_max_tokens' ),
            'default'           => 2048,
        ) );
    }

    /**
     * Sanitize temperature value.
     *
     * @param mixed $input The input value to sanitize.
     * @return float Sanitized temperature value.
     */
    public function sanitize_temperature( $input ) {
        $input = floatval( $input );
        return min( max( $input, 0 ), 1 );
    }

    /**
     * Sanitize max tokens value.
     *
     * @param mixed $input The input value to sanitize.
     * @return int Sanitized max tokens value.
     */
    public function sanitize_max_tokens( $input ) {
        $input = intval( $input );
        return min( max( $input, 1 ), 8192 );
    }

    /**
     * Render the settings page.
     */
    public function render_settings_page() {
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'WP AI Content Generator Settings', 'wp-ai-content-generator' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'wpai_settings_group' );
                do_settings_sections( 'wpai_settings_group' );
                ?>
                <table class="form-table">
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'AI API Key', 'wp-ai-content-generator' ); ?></th>
                        <td>
                            <input type="text" name="wpai_api_key" value="<?php echo esc_attr( get_option( 'wpai_api_key' ) ); ?>" class="regular-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'AI Model', 'wp-ai-content-generator' ); ?></th>
                        <td>
                            <select name="wpai_model">
                                <option value="default-model" <?php selected( get_option( 'wpai_model' ), 'default-model' ); ?>><?php esc_html_e( 'Default Model', 'wp-ai-content-generator' ); ?></option>
                                <option value="gemini-1.0-pro" <?php selected(get_option('ggcg_gemini_model'), 'gemini-1.0-pro'); ?>>Gemini 1.0 Pro</option>
                                <option value="gemini-1.5-pro" <?php selected(get_option('ggcg_gemini_model'), 'gemini-1.5-pro'); ?>>Gemini 1.5 Pro</option>
                                <option value="gemini-1.5-flash" <?php selected(get_option('ggcg_gemini_model'), 'gemini-1.5-flash'); ?>>Gemini 1.5 Flash</option>
                            </select>
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Temperature', 'wp-ai-content-generator' ); ?></th>
                        <td>
                            <input type="number" step="0.1" min="0" max="1" name="wpai_temperature" value="<?php echo esc_attr( get_option( 'wpai_temperature', '0.7' ) ); ?>" class="small-text" />
                        </td>
                    </tr>
                    <tr valign="top">
                        <th scope="row"><?php esc_html_e( 'Max Output Tokens', 'wp-ai-content-generator' ); ?></th>
                        <td>
                            <input type="number" min="1" max="8192" name="wpai_max_tokens" value="<?php echo esc_attr( get_option( 'wpai_max_tokens', '2048' ) ); ?>" class="small-text" />
                        </td>
                    </tr>
                </table>
                <?php submit_button(); ?>
            </form>
        </div>
        <?php
    }
}