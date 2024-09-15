<?php
namespace WPAI_Content_Generator;

/**
 * Class AI_API
 * Handles interactions with the AI API.
 */
class AI_API {
    /**
     * @var string The API key.
     */
    private $api_key;

    /**
     * @var string The AI model to use.
     */
    private $model;

    /**
     * @var float The temperature setting for content generation.
     */
    private $temperature;

    /**
     * @var int The maximum number of tokens to generate.
     */
    private $max_tokens;

    /**
     * AI_API constructor.
     */
    public function __construct() {
        $this->api_key     = get_option( 'wpai_api_key' );
        $this->model       = get_option( 'wpai_model', 'default-model' );
        $this->temperature = floatval( get_option( 'wpai_temperature', '0.7' ) );
        $this->max_tokens  = intval( get_option( 'wpai_max_tokens', '8192' ) );
    }

    /**
     * Generate content using the AI API.
     *
     * @param string $prompt The prompt for content generation.
     * @return string|false The generated content or false on failure.
     */
    public function generate_content( $prompt ) {
        $api_key  = $this->api_key;
        $api_url  = 'https://generativelanguage.googleapis.com/v1beta/models/' . $this->model . ':generateContent?key=' . $api_key;
        $markdown_prompt = "Generate content using markdown formatting (e.g., ** for bold, * for italics, ## for headings). Prompt: " . $prompt;

        $data = array(
            'contents' => array(
                array(
                    'parts' => array(
                        array( 'text' => $markdown_prompt )
                    )
                )
            ),
            'generationConfig' => array(
                'temperature'      => $this->temperature,
                'topK'             => 1,
                'topP'             => 1,
                'maxOutputTokens'  => $this->max_tokens,
            )
        );
    
        $response = wp_remote_post( $api_url, array(
            'headers' => array( 'Content-Type' => 'application/json' ),
            'body'    => wp_json_encode( $data ),
            'timeout' => 60,
        ) );
    
        if ( is_wp_error( $response ) ) {
            error_log( 'Gemini API Error: ' . $response->get_error_message() );
            return false;
        }
    
        $response_code = wp_remote_retrieve_response_code( $response );
        $body          = wp_remote_retrieve_body( $response );

        if ( 200 !== $response_code ) {
            error_log( 'Gemini API Non-200 Response: ' . $response_code );
            return false;
        }
    
        $result = json_decode( $body, true );
    
        if ( JSON_ERROR_NONE !== json_last_error() ) {
            error_log( 'Gemini API JSON Decode Error: ' . json_last_error_msg() );
            return false;
        }
    
        if ( ! isset( $result['candidates'][0]['content']['parts'][0]['text'] ) ) {
            error_log( 'Gemini API Unexpected Response Structure: ' . print_r( $result, true ) );
            return false;
        }
    
        return $result['candidates'][0]['content']['parts'][0]['text'];
    }
}