<?php
/**
 * WPAI Content Generator
 *
 * @package WPAIContentGenerator
 */

namespace WPAI_Content_Generator;

/**
 * Class Content_Generator
 */
class Content_Generator {

    /**
     * The AI API instance.
     *
     * @var AI_API
     */
    private $ai_api;

    /**
     * Constructor.
     */
    public function __construct() {
        $this->ai_api = new AI_API();
        add_action( 'add_meta_boxes', array( $this, 'add_meta_box' ) );
        add_action( 'save_post', array( $this, 'save_generated_content' ) );
        add_action( 'wp_ajax_wpai_generate_content', array( $this, 'ajax_generate_content' ) );
    }

    /**
     * Add meta box to the post editor.
     */
    public function add_meta_box() {
        add_meta_box(
            'ggcg_content_generator',
            __( 'WP AI Content Generator', 'wp-ai-content-generator' ),
            array( $this, 'render_meta_box' ),
            'post',
            'side',
            'high'
        );
    }

    /**
     * Render the meta box content.
     *
     * @param WP_Post $post The post object.
     */
    public function render_meta_box( $post ) {
        wp_nonce_field( 'wpai_generate_content', 'wpai_nonce' );
        ?>
        <label for="wpai_prompt"><?php esc_html_e( 'Enter your prompt:', 'wp-ai-content-generator' ); ?></label>
        <textarea id="wpai_prompt" name="wpai_prompt" rows="4" style="width: 100%;"></textarea>
        <button type="button" id="wpai_generate_button" class="button"><?php esc_html_e( 'Generate Content', 'wp-ai-content-generator' ); ?></button>
        <div id="wpai_loading" style="display: none;"><?php esc_html_e( 'Generating content...', 'wp-ai-content-generator' ); ?></div>
        <?php
    }

    /**
     * Save the generated content.
     *
     * @param int $post_id The post ID.
     */
    public function save_generated_content( $post_id ) {
        if ( ! isset( $_POST['ggcg_nonce'] ) || ! wp_verify_nonce( sanitize_key( $_POST['ggcg_nonce'] ), 'ggcg_generate_content' ) ) {
            return;
        }

        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }

        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        if ( isset( $_POST['ggcg_prompt'] ) && ! empty( $_POST['ggcg_prompt'] ) ) {
            $prompt = sanitize_textarea_field( wp_unslash( $_POST['ggcg_prompt'] ) );
            $generated_content = $this->ai_api->generate_content( $prompt );

            if ( $generated_content ) {
                $this->update_post_content( $post_id, $generated_content );
            }
        }
    }

    /**
     * Update the post content with generated content.
     *
     * @param int    $post_id The post ID.
     * @param string $content The generated content.
     */
    private function update_post_content( $post_id, $content ) {
        $formatted_content = $this->format_content_for_editor( $content );
        $post_data = array(
            'ID'           => $post_id,
            'post_content' => $formatted_content,
            'post_title'   => $this->generate_title( $content ),
            'post_excerpt' => $this->generate_excerpt( $content ),
        );

        wp_update_post( $post_data );
    }

    /**
     * Format content for the editor.
     *
     * @param string $content The raw content.
     * @return string The formatted content.
     */
    private function format_content_for_editor( $content ) {
        if ( use_block_editor_for_post_type( get_post_type() ) ) {
            $blocks = $this->convert_to_gutenberg_blocks( $content );
            return serialize_blocks( $blocks );
        } else {
            return wpautop( $this->convert_markdown_to_html( $content ) );
        }
    }

    /**
     * Convert content to Gutenberg blocks.
     *
     * @param string $content The raw content.
     * @return array The Gutenberg blocks.
     */
    private function convert_to_gutenberg_blocks( $content ) {
        $blocks = array();
        $paragraphs = explode( "\n\n", $content );
        $in_list = false;
        $list_items = array();

        foreach ( $paragraphs as $paragraph ) {
            if ( preg_match( '/^(#+)\s(.+)/', $paragraph, $matches ) ) {
                if ( $in_list ) {
                    $blocks[] = $this->create_list_block( $list_items );
                    $list_items = array();
                    $in_list = false;
                }
                $level = strlen( $matches[1] );
                $text = $this->parse_inline_markdown( $matches[2] );
                $blocks[] = array(
                    'blockName'    => 'core/heading',
                    'attrs'        => array( 'level' => $level ),
                    'innerHTML'    => "<h{$level}>" . wp_kses_post( $text ) . "</h{$level}>",
                    'innerContent' => array( "<h{$level}>" . wp_kses_post( $text ) . "</h{$level}>" ),
                );
            } elseif ( preg_match( '/^\* (.*)$/m', $paragraph ) ) {
                $in_list = true;
                $list_items[] = $this->parse_inline_markdown( $paragraph );
            } else {
                if ( $in_list ) {
                    $blocks[] = $this->create_list_block( $list_items );
                    $list_items = array();
                    $in_list = false;
                }
                $text = $this->parse_inline_markdown( $paragraph );
                $blocks[] = array(
                    'blockName'    => 'core/paragraph',
                    'attrs'        => array(),
                    'innerHTML'    => '<p>' . wp_kses_post( $text ) . '</p>',
                    'innerContent' => array( '<p>' . wp_kses_post( $text ) . '</p>' ),
                );
            }
        }

        if ( $in_list ) {
            $blocks[] = $this->create_list_block( $list_items );
        }

        return $blocks;
    }

    /**
     * Create a list block.
     *
     * @param array $items The list items.
     * @return array The list block.
     */
    private function create_list_block( $items ) {
        $inner_html = '<ul>' . implode( '', array_map( function( $item ) {
            return '<li>' . wp_kses_post( $item ) . '</li>';
        }, $items ) ) . '</ul>';

        return array(
            'blockName'    => 'core/list',
            'attrs'        => array(),
            'innerHTML'    => $inner_html,
            'innerContent' => array( $inner_html ),
        );
    }

    /**
     * Parse inline markdown.
     *
     * @param string $text The text to parse.
     * @return string The parsed text.
     */
    private function parse_inline_markdown( $text ) {
        $text = preg_replace( '/\*\*(.*?)\*\*/', '<strong>$1</strong>', $text );
        $text = preg_replace( '/\*(.*?)\*/', '<em>$1</em>', $text );
        $text = preg_replace( '/$$(.*?)$$$$(.*?)$$/', '<a href="$2">$1</a>', $text );
        $text = preg_replace( '/^\* (.*)$/m', '<li>$1</li>', $text );
        $text = preg_replace( '/^\* /m', '', $text );

        return $text;
    }

    /**
     * Convert markdown to HTML.
     *
     * @param string $content The markdown content.
     * @return string The HTML content.
     */
    private function convert_markdown_to_html( $content ) {
        $html = '';
        $paragraphs = explode( "\n\n", $content );

        foreach ( $paragraphs as $paragraph ) {
            if ( preg_match( '/^#+\s/', $paragraph ) ) {
                $level = strlen( trim( substr( $paragraph, 0, strpos( $paragraph, ' ' ) ) ) );
                $text = trim( substr( $paragraph, strpos( $paragraph, ' ' ) ) );
                $html .= "<h$level>" . $this->parse_inline_markdown( $text ) . "</h$level>";
            } else {
                $html .= '<p>' . $this->parse_inline_markdown( $paragraph ) . '</p>';
            }
        }

        return $html;
    }

    /**
     * Generate a title from the content.
     *
     * @param string $content The content.
     * @return string The generated title.
     */
    private function generate_title( $content ) {
        $lines = explode( "\n", $content );
        $potential_title = '';
        foreach ( $lines as $line ) {
            $line = trim( $line );
            if ( preg_match( '/^#+\s(.+)/', $line, $matches ) ) {
                $potential_title = $matches[1];
                break;
            } elseif ( ! empty( $line ) ) {
                $potential_title = $line;
                break;
            }
        }

        if ( empty( $potential_title ) ) {
            $words = preg_split( '/\s+/', $content, -1, PREG_SPLIT_NO_EMPTY );
            $potential_title = implode( ' ', array_slice( $words, 0, 5 ) );
        }

        $title = preg_replace( '/^#+\s*/', '', $potential_title );
        $title = preg_replace( '/\*\*(.*?)\*\*/', '$1', $title );
        $title = preg_replace( '/\*(.*?)\*/', '$1', $title );
        $title = preg_replace( '/$$(.*?)$$$$.*?$$/', '$1', $title );
        $title = strip_tags( $title );
        $title = trim( $title );

        return wp_trim_words( $title, 10, '...' );
    }

    /**
     * Generate an excerpt from the content.
     *
     * @param string $content The content.
     * @return string The generated excerpt.
     */
    private function generate_excerpt( $content ) {
        $content = preg_replace( '/^#+\s/m', '', $content );
        $content = preg_replace( '/\*\*(.*?)\*\*/', '$1', $content );
        $content = preg_replace( '/\*(.*?)\*/', '$1', $content );
        $content = preg_replace( '/$$(.*?)$$$$.*?$$/', '$1', $content );
        $content = strip_tags( $content );
        $content = preg_replace( '/\s+/', ' ', trim( $content ) );

        return wp_trim_words( $content, 55, '...' );
    }

    /**
     * Handle AJAX request to generate content.
     */
    public function ajax_generate_content() {
        check_ajax_referer( 'wpai_generate_content', 'nonce' );

        if ( ! current_user_can( 'edit_posts' ) ) {
            wp_send_json_error( array( 'message' => __( 'You do not have permission to perform this action.', 'wp-ai-content-generator' ) ) );
        }

        $prompt = isset( $_POST['prompt'] ) ? sanitize_textarea_field( wp_unslash( $_POST['prompt'] ) ) : '';
        $post_id = isset( $_POST['post_id'] ) ? intval( $_POST['post_id'] ) : 0;

        if ( empty( $prompt ) || empty( $post_id ) ) {
            wp_send_json_error( array( 'message' => __( 'Invalid prompt or post ID', 'wp-ai-content-generator' ) ) );
        }

        $generated_content = $this->ai_api->generate_content( $prompt );

        if ( false === $generated_content ) {
            error_log( 'Content generation failed for prompt: ' . $prompt );
            wp_send_json_error( array( 'message' => __( 'Failed to generate content. Please check the server logs for more details.', 'wp-ai-content-generator' ) ) );
        } elseif ( empty( $generated_content ) ) {
            wp_send_json_error( array( 'message' => __( 'Generated content is empty. Please try a different prompt.', 'wp-ai-content-generator' ) ) );
        } else {
            $title = $this->generate_title( $generated_content );
            $excerpt = $this->generate_excerpt( $generated_content );
            $formatted_content = $this->format_content_for_editor( $generated_content );

            wp_update_post( array(
                'ID'           => $post_id,
                'post_content' => $formatted_content,
                'post_excerpt' => $excerpt,
            ) );

            wp_send_json_success( array(
                'title'   => $title,
                'content' => $formatted_content,
                'excerpt' => $excerpt,
            ) );
        }
    }
}