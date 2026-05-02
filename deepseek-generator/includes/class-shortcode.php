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

        $smoke_settings = self::get_smoke_config();

        wp_localize_script( 'dsg-frontend', 'dsgConfig', [
            'ajaxUrl' => admin_url( 'admin-ajax.php' ),
            'nonce'   => wp_create_nonce( 'dsg_nonce' ),
            'smoke'   => $smoke_settings,
            'i18n'    => [
                'generate'    => __( '生成', 'deepseek-generator' ),
                'generating'  => __( '生成中...', 'deepseek-generator' ),
                'copy'        => __( '复制', 'deepseek-generator' ),
                'copied'      => __( '已复制', 'deepseek-generator' ),
                'regenerate'  => __( '重新生成', 'deepseek-generator' ),
                'error'       => __( '生成失败，请重试。', 'deepseek-generator' ),
                'empty_input' => __( '请输入内容。', 'deepseek-generator' ),
                'example'     => __( '输出样例', 'deepseek-generator' ),
                'save'        => __( '保存', 'deepseek-generator' ),
                'saved'       => __( '已保存', 'deepseek-generator' ),
                'saving'      => __( '保存中...', 'deepseek-generator' ),
                'save_fail'   => __( '保存失败。', 'deepseek-generator' ),
                'load_more'   => __( '加载更多', 'deepseek-generator' ),
                'no_history'  => __( '暂无记录。', 'deepseek-generator' ),
                'history'     => __( '历史生成记录', 'deepseek-generator' ),
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
        $allow_save      = get_post_meta( $template_id, '_ds_allow_save', true );
        $show_history    = get_post_meta( $template_id, '_ds_show_history', true );
        $theme           = in_array( $atts['theme'], [ 'light', 'dark' ], true ) ? $atts['theme'] : 'light';

        return self::build_html( $template_id, $template_title, $atts['placeholder'], $atts['button_text'], $output_example, $theme, ! empty( $allow_save ), ! empty( $show_history ) );
    }

    private static function get_smoke_config(): array {
        $settings = get_option( 'dsg_settings', [] );
        $enabled = ! empty( $settings['smoke_enabled'] );
        return [
            'enabled'     => $enabled,
            'particles'   => max( 10, min( 3000, (int) ( $settings['smoke_particles'] ?? 800 ) ) ),
            'speed'       => max( 0.1, min( 3.0, (float) ( $settings['smoke_speed'] ?? 0.6 ) ) ),
            'opacity'     => max( 0.05, min( 0.8, (float) ( $settings['smoke_opacity'] ?? 0.3 ) ) ),
            'color'       => sanitize_hex_color( $settings['smoke_color'] ?? '#4f46e5' ) ?: '#4f46e5',
            'spread'      => max( 0.3, min( 3.0, (float) ( $settings['smoke_spread'] ?? 1.0 ) ) ),
            'breathe'     => max( 0.0, min( 1.0, (float) ( $settings['smoke_breathe'] ?? 0.5 ) ) ),
            'mask_bottom' => max( 0.0, min( 1.0, (float) ( $settings['smoke_mask_bottom'] ?? 0.8 ) ) ),
            'mask_mid'    => max( 0.0, min( 1.0, (float) ( $settings['smoke_mask_mid'] ?? 0.4 ) ) ),
            'mask_top'    => max( 0.0, min( 1.0, (float) ( $settings['smoke_mask_top'] ?? 0.1 ) ) ),
        ];
    }

    public static function build_html( int $template_id, string $title, string $placeholder, string $button_text, string $output_example, string $theme, bool $allow_save = false, bool $show_history = false ): string {
        $uid = 'dsg-' . wp_unique_id();

        $smoke_config = self::get_smoke_config();

        $smoke_style = '';
        if ( $smoke_config['enabled'] ) {
            $smoke_style = sprintf(
                '--dsg-smoke-mask-bottom:%.2f;--dsg-smoke-mask-mid:%.2f;--dsg-smoke-mask-top:%.2f;',
                $smoke_config['mask_bottom'],
                $smoke_config['mask_mid'],
                $smoke_config['mask_top']
            );
        }

        ob_start();
        ?>
        <div id="<?php echo esc_attr( $uid ); ?>"
             class="dsg-generator dsg-theme-<?php echo esc_attr( $theme ); ?>"
             style="<?php echo esc_attr( $smoke_style ); ?>"
             data-template-id="<?php echo esc_attr( $template_id ); ?>"
             data-saveable="<?php echo $allow_save ? '1' : '0'; ?>"
             data-show-history="<?php echo $show_history ? '1' : '0'; ?>">

            <?php if ( $smoke_config['enabled'] ) : ?>
                <canvas class="dsg-smoke-canvas" aria-hidden="true"></canvas>
            <?php endif; ?>

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
                    <?php if ( $allow_save ) : ?>
                        <button type="button" class="dsg-btn-icon dsg-btn-save" title="<?php esc_attr_e( '保存到展示墙', 'deepseek-generator' ); ?>" style="display:none;">
                            <svg width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M19 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11l5 5v11a2 2 0 0 1-2 2z"/><polyline points="17 21 17 13 7 13 7 21"/><polyline points="7 3 7 8 15 8"/></svg>
                            <span><?php esc_html_e( '保存', 'deepseek-generator' ); ?></span>
                        </button>
                    <?php endif; ?>
                </div>
                <div class="dsg-output-content"></div>
            </div>

            <?php if ( $output_example ) : ?>
                <div class="dsg-example">
                    <div class="dsg-example-label"><?php esc_html_e( '输出样例', 'deepseek-generator' ); ?></div>
                    <div class="dsg-example-content"><?php echo nl2br( esc_html( $output_example ) ); ?></div>
                </div>
            <?php endif; ?>

            <?php if ( $show_history ) : ?>
                <div class="dsg-history">
                    <div class="dsg-history-header">
                        <h4 class="dsg-history-title"><?php esc_html_e( '历史生成记录', 'deepseek-generator' ); ?></h4>
                    </div>
                    <div class="dsg-history-list"></div>
                    <div class="dsg-history-more" style="display:none;">
                        <button type="button" class="dsg-btn-icon dsg-btn-load-more">
                            <?php esc_html_e( '加载更多', 'deepseek-generator' ); ?>
                        </button>
                    </div>
                </div>
            <?php endif; ?>
        </div>
        <?php
        return ob_get_clean();
    }
}
