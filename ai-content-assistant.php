<?php
/*
Plugin Name: AI Content Assistant
Description: A plugin that integrates with OpenAI and Gemini to generate content
Version: 1.0.1
Author: Mozayyan Abbas
*/

if (!defined('ABSPATH')) {
    exit; // Exit if accessed directly
}

class AIContentAssistant {
    private $options;

    public function __construct() {
        add_action('admin_menu', array($this, 'add_plugin_page'));
        add_action('admin_init', array($this, 'page_init'));
        add_shortcode('ai_content_assistant', array($this, 'frontend_shortcode'));
        add_action('wp_ajax_generate_ai_content', array($this, 'generate_ai_content'));
        add_action('wp_ajax_nopriv_generate_ai_content', array($this, 'generate_ai_content'));
    }

    // Add options page
    public function add_plugin_page() {
        add_options_page(
            'AI Content Assistant Settings',
            'AI Content Assistant',
            'manage_options',
            'ai-content-assistant',
            array($this, 'create_admin_page')
        );
    }

    // Options page callback
    public function create_admin_page() {
        $this->options = get_option('ai_content_assistant_options');
        ?>
        <div class="wrap">
            <h1>AI Content Assistant Settings</h1>
            <form method="post" action="options.php">
            <?php
                settings_fields('ai_content_assistant_group');
                do_settings_sections('ai-content-assistant');
                submit_button();
            ?>
            </form>
            
            <h2>Test AI Generation</h2>
            <div id="ai-test-area">
                <textarea id="ai-prompt" style="width: 100%; height: 100px;" placeholder="Enter your prompt here..."></textarea>
                <select id="ai-service">
                    <option value="openai">OpenAI</option>
                    <option value="gemini">Google Gemini</option>
                </select>
                <button id="generate-content" class="button button-primary">Generate Content</button>
                <div id="ai-result" style="margin-top: 20px; padding: 10px; border: 1px solid #ddd;"></div>
            </div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#generate-content').click(function() {
                var prompt = $('#ai-prompt').val();
                var service = $('#ai-service').val();
                
                if (!prompt) {
                    alert('Please enter a prompt');
                    return;
                }
                
                $('#ai-result').html('<p>Generating content... <span class="spinner is-active"></span></p>');
                
                $.post(ajaxurl, {
                    action: 'generate_ai_content',
                    prompt: prompt,
                    service: service,
                    security: '<?php echo wp_create_nonce("ai_content_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        $('#ai-result').html('<h3>Generated Content:</h3>' + response.data);
                    } else {
                        $('#ai-result').html('<p class="error">Error: ' + response.data + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
    }

    // Register and add settings
    public function page_init() {
        register_setting(
            'ai_content_assistant_group',
            'ai_content_assistant_options',
            array($this, 'sanitize')
        );

        add_settings_section(
            'ai_content_assistant_section',
            'API Settings',
            array($this, 'print_section_info'),
            'ai-content-assistant'
        );

        add_settings_field(
            'openai_api_key',
            'OpenAI API Key',
            array($this, 'openai_api_key_callback'),
            'ai-content-assistant',
            'ai_content_assistant_section'
        );

        add_settings_field(
            'gemini_api_key',
            'Google Gemini API Key',
            array($this, 'gemini_api_key_callback'),
            'ai-content-assistant',
            'ai_content_assistant_section'
        );

        add_settings_field(
            'default_ai',
            'Default AI Service',
            array($this, 'default_ai_callback'),
            'ai-content-assistant',
            'ai_content_assistant_section'
        );
    }

    // Sanitize settings
    public function sanitize($input) {
        $new_input = array();
        if (isset($input['openai_api_key']))
            $new_input['openai_api_key'] = sanitize_text_field($input['openai_api_key']);
        
        if (isset($input['gemini_api_key']))
            $new_input['gemini_api_key'] = sanitize_text_field($input['gemini_api_key']);
        
        if (isset($input['default_ai']))
            $new_input['default_ai'] = sanitize_text_field($input['default_ai']);
        
        return $new_input;
    }

    // Print section info
    public function print_section_info() {
        print 'Enter your API settings below:';
    }

    // Callbacks for settings fields
    public function openai_api_key_callback() {
        printf(
            '<input type="text" id="openai_api_key" name="ai_content_assistant_options[openai_api_key]" value="%s" style="width: 300px;" />',
            isset($this->options['openai_api_key']) ? esc_attr($this->options['openai_api_key']) : ''
        );
    }

    public function gemini_api_key_callback() {
        printf(
            '<input type="text" id="gemini_api_key" name="ai_content_assistant_options[gemini_api_key]" value="%s" style="width: 300px;" />',
            isset($this->options['gemini_api_key']) ? esc_attr($this->options['gemini_api_key']) : ''
        );
    }

    public function default_ai_callback() {
        $selected = isset($this->options['default_ai']) ? $this->options['default_ai'] : 'openai';
        ?>
        <select id="default_ai" name="ai_content_assistant_options[default_ai]">
            <option value="openai" <?php selected($selected, 'openai'); ?>>OpenAI</option>
            <option value="gemini" <?php selected($selected, 'gemini'); ?>>Google Gemini</option>
        </select>
        <?php
    }

    // Frontend shortcode
    public function frontend_shortcode($atts) {
        wp_enqueue_script('jquery');
        
        $atts = shortcode_atts(array(
            'default_service' => isset($this->options['default_ai']) ? $this->options['default_ai'] : 'openai',
            'button_text' => 'Generate Content'
        ), $atts);
        
        ob_start();
        ?>
        <div class="ai-content-assistant-frontend">
            <textarea class="ai-prompt" style="width: 100%; height: 100px;" placeholder="Enter your prompt here..."></textarea>
            <select class="ai-service">
                <option value="openai" <?php selected($atts['default_service'], 'openai'); ?>>OpenAI</option>
                <option value="gemini" <?php selected($atts['default_service'], 'gemini'); ?>>Google Gemini</option>
            </select>
            <button class="generate-content button"><?php echo esc_html($atts['button_text']); ?></button>
            <div class="ai-result" style="margin-top: 20px; padding: 10px; border: 1px solid #ddd;"></div>
        </div>
        
        <script>
        jQuery(document).ready(function($) {
            $('.ai-content-assistant-frontend .generate-content').click(function() {
                var container = $(this).closest('.ai-content-assistant-frontend');
                var prompt = container.find('.ai-prompt').val();
                var service = container.find('.ai-service').val();
                
                if (!prompt) {
                    alert('Please enter a prompt');
                    return;
                }
                
                container.find('.ai-result').html('<p>Generating content... <span class="spinner is-active"></span></p>');
                
                $.post('<?php echo admin_url('admin-ajax.php'); ?>', {
                    action: 'generate_ai_content',
                    prompt: prompt,
                    service: service,
                    security: '<?php echo wp_create_nonce("ai_content_nonce"); ?>'
                }, function(response) {
                    if (response.success) {
                        container.find('.ai-result').html('<h3>Generated Content:</h3>' + response.data);
                    } else {
                        container.find('.ai-result').html('<p class="error">Error: ' + response.data + '</p>');
                    }
                });
            });
        });
        </script>
        <?php
        return ob_get_clean();
    }

    // Handle AI content generation
    public function generate_ai_content() {
        check_ajax_referer('ai_content_nonce', 'security');
        
        $options = get_option('ai_content_assistant_options');
        $prompt = sanitize_text_field($_POST['prompt']);
        $service = sanitize_text_field($_POST['service']);
        
        if (empty($prompt)) {
            wp_send_json_error('Prompt is required');
        }
        
        try {
            if ($service === 'openai') {
                if (empty($options['openai_api_key'])) {
                    wp_send_json_error('OpenAI API key is not configured');
                }
                $content = $this->generate_with_openai($prompt, $options['openai_api_key']);
            } elseif ($service === 'gemini') {
                if (empty($options['gemini_api_key'])) {
                    wp_send_json_error('Gemini API key is not configured');
                }
                $content = $this->generate_with_gemini($prompt, $options['gemini_api_key']);
            } else {
                wp_send_json_error('Invalid AI service selected');
            }
            
            wp_send_json_success($content);
        } catch (Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
    
    // Generate content with OpenAI
    private function generate_with_openai($prompt, $api_key) {
        $url = 'https://api.openai.com/v1/chat/completions';
        
        $headers = array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        );
        
        $body = array(
            'model' => 'gpt-3.5-turbo',
            'messages' => array(
                array(
                    'role' => 'user',
                    'content' => $prompt
                )
            ),
            'temperature' => 0.7
        );
        
        $args = array(
            'body' => json_encode($body),
            'headers' => $headers,
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['error'])) {
            throw new Exception($response_body['error']['message']);
        }
        
        return $response_body['choices'][0]['message']['content'];
    }
    
    // Generate content with Gemini
    private function generate_with_gemini($prompt, $api_key) {
        $url = 'https://generativelanguage.googleapis.com/v1beta/models/gemini-pro:generateContent?key=' . $api_key;
        
        $headers = array(
            'Content-Type' => 'application/json'
        );
        
        $body = array(
            'contents' => array(
                'parts' => array(
                    array(
                        'text' => $prompt
                    )
                )
            )
        );
        
        $args = array(
            'body' => json_encode($body),
            'headers' => $headers,
            'timeout' => 30
        );
        
        $response = wp_remote_post($url, $args);
        
        if (is_wp_error($response)) {
            throw new Exception($response->get_error_message());
        }
        
        $response_body = json_decode(wp_remote_retrieve_body($response), true);
        
        if (isset($response_body['error'])) {
            throw new Exception($response_body['error']['message']);
        }
        
        return $response_body['candidates'][0]['content']['parts'][0]['text'];
    }
}

// Initialize the plugin
if (is_admin()) {
    new AIContentAssistant();
}

// Register shortcode
add_shortcode('ai_content_assistant', array('AIContentAssistant', 'frontend_shortcode'));
