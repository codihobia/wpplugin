<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DSG_Block {

    public static function register(): void {
        register_block_type( DSG_PLUGIN_DIR . 'blocks/deepseek-generator', [
            'render_callback' => [ __CLASS__, 'render' ],
        ] );

        wp_localize_script( 'dsg-generator-editor-script', 'dsgBlockData', self::get_templates_for_editor() );
    }

    private static function get_templates_for_editor(): array {
        $templates = get_posts( [
            'post_type'      => 'ds_prompt_template',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        $list = [ [ 'value' => 0, 'label' => __( '— 请选择模板 —', 'deepseek-generator' ) ] ];
        foreach ( $templates as $t ) {
            $list[] = [
                'value'       => $t->ID,
                'label'       => $t->post_title,
                'example'     => get_post_meta( $t->ID, '_ds_output_example', true ),
                'allowSave'   => ! empty( get_post_meta( $t->ID, '_ds_allow_save', true ) ),
                'showHistory' => ! empty( get_post_meta( $t->ID, '_ds_show_history', true ) ),
            ];
        }

        return [ 'templates' => $list ];
    }

    public static function render( array $attributes ): string {
        $template_id = (int) ( $attributes['templateId'] ?? 0 );
        if ( ! $template_id || get_post_type( $template_id ) !== 'ds_prompt_template' ) {
            return '<p class="dsg-error">' . esc_html__( '请在 Block 设置中选择一个提示词模板。', 'deepseek-generator' ) . '</p>';
        }

        DSG_Shortcode::enqueue_assets();

        $title          = get_the_title( $template_id );
        $placeholder    = $attributes['placeholder'] ?? __( '请输入你的内容...', 'deepseek-generator' );
        $button_text    = $attributes['buttonText'] ?? __( '生成', 'deepseek-generator' );
        $show_example   = $attributes['showExample'] ?? true;
        $theme          = in_array( $attributes['theme'] ?? 'light', [ 'light', 'dark' ], true ) ? $attributes['theme'] : 'light';
        $output_example = $show_example ? get_post_meta( $template_id, '_ds_output_example', true ) : '';
        $allow_save     = ! empty( get_post_meta( $template_id, '_ds_allow_save', true ) );
        $show_history   = ! empty( get_post_meta( $template_id, '_ds_show_history', true ) );

        return DSG_Shortcode::build_html( $template_id, $title, $placeholder, $button_text, $output_example, $theme, $allow_save, $show_history );
    }
}
