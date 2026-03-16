<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DSG_API {

    private static function get_settings(): array {
        $defaults = [
            'api_key'      => '',
            'base_url'     => 'https://api.deepseek.com',
            'model'        => 'deepseek-chat',
            'temperature'  => 1,
            'max_tokens'   => 2048,
            'top_p'        => 1,
            'allow_guests' => false,
            'rate_limit'   => 10,
        ];
        $saved = get_option( 'dsg_settings', [] );
        return wp_parse_args( $saved, $defaults );
    }

    private static function decrypt_api_key( string $encrypted ): string {
        if ( empty( $encrypted ) ) {
            return '';
        }
        $salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'dsg-fallback-salt';
        $decoded = base64_decode( $encrypted, true );
        if ( false === $decoded || strlen( $decoded ) < 17 ) {
            return $encrypted;
        }
        $iv   = substr( $decoded, 0, 16 );
        $data = substr( $decoded, 16 );
        $key  = hash( 'sha256', $salt, true );
        $decrypted = openssl_decrypt( $data, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return false === $decrypted ? $encrypted : $decrypted;
    }

    public static function encrypt_api_key( string $raw ): string {
        if ( empty( $raw ) ) {
            return '';
        }
        $salt = defined( 'SECURE_AUTH_SALT' ) ? SECURE_AUTH_SALT : 'dsg-fallback-salt';
        $key  = hash( 'sha256', $salt, true );
        $iv   = openssl_random_pseudo_bytes( 16 );
        $encrypted = openssl_encrypt( $raw, 'AES-256-CBC', $key, OPENSSL_RAW_DATA, $iv );
        return base64_encode( $iv . $encrypted );
    }

    public static function chat_completion( string $system_prompt, string $user_message, array $options = [] ): array {
        $settings = self::get_settings();
        $api_key  = self::decrypt_api_key( $settings['api_key'] );

        if ( empty( $api_key ) ) {
            return [ 'error' => __( 'API Key 未配置。', 'deepseek-generator' ) ];
        }

        $body = [
            'model'       => $options['model'] ?? $settings['model'],
            'temperature' => (float) ( $options['temperature'] ?? $settings['temperature'] ),
            'max_tokens'  => (int) ( $options['max_tokens'] ?? $settings['max_tokens'] ),
            'top_p'       => (float) ( $options['top_p'] ?? $settings['top_p'] ),
            'stream'      => false,
            'messages'    => [
                [ 'role' => 'system', 'content' => $system_prompt ],
                [ 'role' => 'user',   'content' => $user_message ],
            ],
        ];

        $response = wp_remote_post(
            rtrim( $settings['base_url'], '/' ) . '/chat/completions',
            [
                'timeout' => 120,
                'headers' => [
                    'Content-Type'  => 'application/json',
                    'Authorization' => 'Bearer ' . $api_key,
                ],
                'body' => wp_json_encode( $body ),
            ]
        );

        if ( is_wp_error( $response ) ) {
            return [ 'error' => $response->get_error_message() ];
        }

        $code = wp_remote_retrieve_response_code( $response );
        $data = json_decode( wp_remote_retrieve_body( $response ), true );

        if ( $code !== 200 ) {
            $msg = $data['error']['message'] ?? ( __( 'API 返回错误 ', 'deepseek-generator' ) . $code );
            return [ 'error' => $msg ];
        }

        return [
            'content' => $data['choices'][0]['message']['content'] ?? '',
            'usage'   => $data['usage'] ?? [],
        ];
    }

    public static function chat_completion_stream( string $system_prompt, string $user_message, array $options = [] ): void {
        $settings = self::get_settings();
        $api_key  = self::decrypt_api_key( $settings['api_key'] );

        if ( empty( $api_key ) ) {
            echo "data: " . wp_json_encode( [ 'error' => __( 'API Key 未配置。', 'deepseek-generator' ) ] ) . "\n\n";
            return;
        }

        $body = [
            'model'       => $options['model'] ?? $settings['model'],
            'temperature' => (float) ( $options['temperature'] ?? $settings['temperature'] ),
            'max_tokens'  => (int) ( $options['max_tokens'] ?? $settings['max_tokens'] ),
            'top_p'       => (float) ( $options['top_p'] ?? $settings['top_p'] ),
            'stream'      => true,
            'messages'    => [
                [ 'role' => 'system', 'content' => $system_prompt ],
                [ 'role' => 'user',   'content' => $user_message ],
            ],
        ];

        $url = rtrim( $settings['base_url'], '/' ) . '/chat/completions';

        $ch = curl_init( $url );
        curl_setopt_array( $ch, [
            CURLOPT_POST           => true,
            CURLOPT_HTTPHEADER     => [
                'Content-Type: application/json',
                'Authorization: Bearer ' . $api_key,
                'Accept: text/event-stream',
            ],
            CURLOPT_POSTFIELDS     => wp_json_encode( $body ),
            CURLOPT_RETURNTRANSFER => false,
            CURLOPT_TIMEOUT        => 300,
            CURLOPT_WRITEFUNCTION  => function ( $ch, $data ) {
                echo $data;
                if ( ob_get_level() ) {
                    ob_flush();
                }
                flush();
                return strlen( $data );
            },
        ] );

        curl_exec( $ch );

        $err = curl_error( $ch );
        if ( $err ) {
            echo "data: " . wp_json_encode( [ 'error' => $err ] ) . "\n\n";
        }

        curl_close( $ch );
    }

    private static function check_rate_limit(): bool {
        $settings   = self::get_settings();
        $max        = (int) $settings['rate_limit'];
        if ( $max <= 0 ) {
            return true;
        }

        $identifier = is_user_logged_in() ? 'user_' . get_current_user_id() : 'ip_' . md5( $_SERVER['REMOTE_ADDR'] ?? '' );
        $key        = 'dsg_rate_' . $identifier;
        $count      = (int) get_transient( $key );

        if ( $count >= $max ) {
            return false;
        }

        set_transient( $key, $count + 1, MINUTE_IN_SECONDS );
        return true;
    }

    public static function ajax_handler(): void {
        self::process_ajax( true );
    }

    public static function ajax_handler_nopriv(): void {
        $settings = self::get_settings();
        if ( empty( $settings['allow_guests'] ) ) {
            wp_send_json_error( __( '请先登录。', 'deepseek-generator' ), 403 );
        }
        self::process_ajax( false );
    }

    private static function process_ajax( bool $is_logged_in ): void {
        check_ajax_referer( 'dsg_nonce', 'nonce' );

        if ( ! self::check_rate_limit() ) {
            wp_send_json_error( __( '请求过于频繁，请稍后再试。', 'deepseek-generator' ), 429 );
        }

        $template_id = (int) ( $_POST['template_id'] ?? 0 );
        $user_input  = sanitize_textarea_field( $_POST['user_input'] ?? '' );
        $stream      = ! empty( $_POST['stream'] );

        if ( ! $template_id || get_post_type( $template_id ) !== 'ds_prompt_template' ) {
            wp_send_json_error( __( '无效的模板。', 'deepseek-generator' ), 400 );
        }

        if ( empty( $user_input ) ) {
            wp_send_json_error( __( '请输入内容。', 'deepseek-generator' ), 400 );
        }

        $system_prompt = get_post_meta( $template_id, '_ds_system_prompt', true );
        $user_template = get_post_meta( $template_id, '_ds_user_prompt_template', true );
        $temperature   = get_post_meta( $template_id, '_ds_temperature', true );
        $max_tokens    = get_post_meta( $template_id, '_ds_max_tokens', true );

        $user_message = str_replace( '{{user_input}}', $user_input, $user_template ?: '{{user_input}}' );

        $style_ref       = get_post_meta( $template_id, '_ds_style_reference', true );
        $style_instruction = get_post_meta( $template_id, '_ds_style_instruction', true );
        if ( trim( (string) $style_ref ) !== '' || trim( (string) $style_instruction ) !== '' ) {
            $system_prompt .= "\n\n## 风格与用词要求\n";
            if ( trim( (string) $style_ref ) !== '' ) {
                $system_prompt .= __( '请模仿以下参考文本的风格、用词与句式：', 'deepseek-generator' ) . "\n\n" . $style_ref . "\n\n";
            }
            if ( trim( (string) $style_instruction ) !== '' ) {
                $system_prompt .= __( '补充要求：', 'deepseek-generator' ) . $style_instruction . "\n";
            }
        }

        $references    = DSG_Retriever::get_references( $template_id, $user_input );
        $ref_context   = DSG_Retriever::format_context( $references );
        $system_prompt .= $ref_context;

        $options = [];
        if ( $temperature !== '' && $temperature !== false ) {
            $options['temperature'] = (float) $temperature;
        }
        if ( $max_tokens !== '' && $max_tokens !== false ) {
            $options['max_tokens'] = (int) $max_tokens;
        }

        if ( $stream ) {
            header( 'Content-Type: text/event-stream' );
            header( 'Cache-Control: no-cache' );
            header( 'X-Accel-Buffering: no' );

            while ( ob_get_level() ) {
                ob_end_clean();
            }

            self::chat_completion_stream( $system_prompt ?: '', $user_message, $options );
            exit;
        }

        $result = self::chat_completion( $system_prompt ?: '', $user_message, $options );

        if ( isset( $result['error'] ) ) {
            wp_send_json_error( $result['error'], 500 );
        }

        wp_send_json_success( $result );
    }
}
