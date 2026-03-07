<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DSG_Shortcode {

    private static bool $enqueued = false;

    public static function init(): void {
        add_shortcode( 'deepseek_gen', [ __CLASS__, 'render' ] );
    }

    public static function enqueue_assets(): void {
        if ( self::$enqueued ) {
            return;
        }
        self::$enqueued = true;

        wp_enqueue_style(
            'dsg-frontend',
            DSG_PLUGIN_URL . 'assets/css/frontend.css',
            [],
            DSG_VERSION
        );

        wp_enqueue_script(
            'marked-js',
            'https://cdn.jsdelivr.net/npm/marked/marked.min.js',
            [],
            '15.0.0',
            true
        );

        wp_enqueue_script(
            'dsg-frontend',
            DSG_PLUGIN_URL . 'assets/js/frontend.js',
            [ 'marked-js' ],
            DSG_VERSION,
            true
        );

        wp_localize_script( 'dsg-frontend', 'dsgConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dsg_nonce' ),
            'i18n'    => [
                'generate'    => __( '生成', 'deepseek-generator' ),
                'generating'  => __( '生成中...', 'deepseek-generator' ),
                'copy'        => __( '复制', 'deepseek-generator' ),
                'copied'      => __( '已复制', 'deepseek-generator' ),
                'regenerate'  => __( '重新生成', 'deepseek-generator' ),
                'error'       => __( '生成失败，请重试。', 'deepseek-generator' ),
                'empty_input' => __( '请输入内容。', 'deepseek-generator' ),
                'example'     => __( '输出样例', 'deepseek-generator' ),
            ],
        ] );
    }

    public static function render( $atts ): string {
        $atts = shortcode_atts( [
            'id'          => 0,
            'placeholder' => __( '请输入你的内容...', 'deepseek-generator' ),
            'button_text' => __( '生成', 'deepseek-generator' ),
            'theme'       => 'light',
        ], $atts, 'deepseek_gen' );

        $template_id = (int) $atts['id'];
        if ( ! $template_id || get_post_type( $template_id ) !== 'ds_prompt_template' ) {
            return '<p class="dsg-error">' . esc_html__( '无效的模板 ID。', 'deepseek-generator' ) . '</p>';
        }

        self::enqueue_assets();

        $template_title  = get_the_title( $template_id );
        $output_example  = get_post_meta( $template_id, '_ds_output_example', true );
        $theme           = in_array( $atts['theme'], [ 'light', 'dark' ], true ) ? $atts['theme'] : 'light';

        return self::build_html( $template_id, $template_title, $atts['placeholder'], $atts['button_text'], $output_example, $theme );
    }

    public static function build_html( int $template_id, string $title, string $placeholder, string $button_text, string $output_example, string $theme ): string {
        $uid = 'dsg-' . wp_unique_id();

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $uid ); ?>"
             class="dsg-generator dsg-theme-<?php echo esc_attr( $theme ); ?>"
             data-template-id="<?php echo esc_attr( $template_id ); ?>">

            <?php if ( $title ) : ?>
                <div class="dsg-header">
                    <h3 class="dsg-title"><?php echo esc_html( $title ); ?></h3>
                </div>
            <?php endif; ?>

            <div class="dsg-input-area">
                <textarea class="dsg-input"
                          placeholder="<?php echo esc_attr( $placeholder ); ?>"
                          rows="4"></textarea>
            </div>

            <div class="dsg-actions">
                <button type="button" class="dsg-btn dsg-btn-generate">
                    <span class="dsg-btn-text"><?php echo esc_html( $button_text ); ?></span>
                    <span class="dsg-spinner" aria-hidden="true"></span>
                </button>
            </div>

            <div class="dsg-output-area" style="display:none;">
                <div class="dsg-output-toolbar">
                    <button type="button" class="dsg-btn-icon dsg-btn-copy" title="<?php esc_attr_e( '复制', 'deepseek-generator' ); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="9" y="9" width="13" height="13" rx="2"/><path d="M5 15H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h9a2 2 0 0 1 2 2v1"/></svg>
                        <span><?php esc_html_e( '复制', 'deepseek-generator' ); ?></span>
                    </button>
                    <button type="button" class="dsg-btn-icon dsg-btn-regenerate" title="<?php esc_attr_e( '重新生成', 'deepseek-generator' ); ?>">
                        <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><polyline points="23 4 23 10 17 10"/><path d="M20.49 15a9 9 0 1 1-2.12-9.36L23 10"/></svg>
                        <span><?php esc_html_e( '重新生成', 'deepseek-generator' ); ?></span>
                    </button>
                </div>
                <div class="dsg-output-content"></div>
            </div>

            <?php if ( $output_example ) : ?>
                <div class="dsg-example">
                    <div class="dsg-example-label"><?php esc_html_e( '输出样例', 'deepseek-generator' ); ?></div>
                    <div class="dsg-example-content"><?php echo nl2br( esc_html( $output_example ) ); ?></div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
