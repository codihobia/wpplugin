<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DSG_Admin {

    public static function register_reference_cpt(): void {
        register_taxonomy( 'ds_ref_tag', 'ds_reference_doc', [
            'labels' => [
                'name'          => __( '参考标签', 'deepseek-generator' ),
                'singular_name' => __( '参考标签', 'deepseek-generator' ),
                'search_items'  => __( '搜索标签', 'deepseek-generator' ),
                'all_items'     => __( '所有标签', 'deepseek-generator' ),
                'edit_item'     => __( '编辑标签', 'deepseek-generator' ),
                'update_item'   => __( '更新标签', 'deepseek-generator' ),
                'add_new_item'  => __( '添加新标签', 'deepseek-generator' ),
                'new_item_name' => __( '新标签名称', 'deepseek-generator' ),
                'menu_name'     => __( '参考标签', 'deepseek-generator' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => true,
            'hierarchical' => false,
            'rewrite'      => false,
        ] );

        register_post_type( 'ds_reference_doc', [
            'labels' => [
                'name'               => __( '参考文档', 'deepseek-generator' ),
                'singular_name'      => __( '参考文档', 'deepseek-generator' ),
                'add_new'            => __( '添加文档', 'deepseek-generator' ),
                'add_new_item'       => __( '添加新参考文档', 'deepseek-generator' ),
                'edit_item'          => __( '编辑参考文档', 'deepseek-generator' ),
                'new_item'           => __( '新参考文档', 'deepseek-generator' ),
                'view_item'          => __( '查看参考文档', 'deepseek-generator' ),
                'search_items'       => __( '搜索参考文档', 'deepseek-generator' ),
                'not_found'          => __( '未找到参考文档', 'deepseek-generator' ),
                'not_found_in_trash' => __( '回收站中无参考文档', 'deepseek-generator' ),
                'all_items'          => __( '参考文档库', 'deepseek-generator' ),
                'menu_name'          => __( '参考文档库', 'deepseek-generator' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'dsg-settings',
            'supports'     => [ 'title', 'editor' ],
            'has_archive'  => false,
            'rewrite'      => false,
            'taxonomies'   => [ 'ds_ref_tag' ],
        ] );
    }

    public static function register_prompt_cpt(): void {
        register_post_type( 'ds_prompt_template', [
            'labels' => [
                'name'               => __( '提示词模板', 'deepseek-generator' ),
                'singular_name'      => __( '提示词模板', 'deepseek-generator' ),
                'add_new'            => __( '添加模板', 'deepseek-generator' ),
                'add_new_item'       => __( '添加新模板', 'deepseek-generator' ),
                'edit_item'          => __( '编辑模板', 'deepseek-generator' ),
                'new_item'           => __( '新模板', 'deepseek-generator' ),
                'view_item'          => __( '查看模板', 'deepseek-generator' ),
                'search_items'       => __( '搜索模板', 'deepseek-generator' ),
                'not_found'          => __( '未找到模板', 'deepseek-generator' ),
                'not_found_in_trash' => __( '回收站中无模板', 'deepseek-generator' ),
                'all_items'          => __( '所有模板', 'deepseek-generator' ),
                'menu_name'          => __( '提示词模板', 'deepseek-generator' ),
            ],
            'public'       => false,
            'show_ui'      => true,
            'show_in_menu' => 'dsg-settings',
            'supports'     => [ 'title' ],
            'has_archive'  => false,
            'rewrite'      => false,
        ] );
    }

    public static function add_menu_page(): void {
        add_menu_page(
            __( 'DeepSeek Generator', 'deepseek-generator' ),
            __( 'DeepSeek AI', 'deepseek-generator' ),
            'manage_options',
            'dsg-settings',
            [ __CLASS__, 'render_settings_page' ],
            'dashicons-format-chat',
            80
        );

        add_submenu_page(
            'dsg-settings',
            __( '设置', 'deepseek-generator' ),
            __( '设置', 'deepseek-generator' ),
            'manage_options',
            'dsg-settings',
            [ __CLASS__, 'render_settings_page' ]
        );

        add_submenu_page(
            'dsg-settings',
            __( '生成记录', 'deepseek-generator' ),
            __( '生成记录', 'deepseek-generator' ),
            'manage_options',
            'dsg-history',
            [ 'DSG_History', 'render_admin_page' ]
        );
    }

    public static function register_settings(): void {
        register_setting( 'dsg_settings_group', 'dsg_settings', [
            'type'              => 'array',
            'sanitize_callback' => [ __CLASS__, 'sanitize_settings' ],
        ] );

        add_settings_section(
            'dsg_api_section',
            __( 'API 配置', 'deepseek-generator' ),
            null,
            'dsg-settings'
        );

        add_settings_field( 'dsg_api_key', __( 'API Key', 'deepseek-generator' ), [ __CLASS__, 'field_api_key' ], 'dsg-settings', 'dsg_api_section' );
        add_settings_field( 'dsg_base_url', __( 'Base URL', 'deepseek-generator' ), [ __CLASS__, 'field_base_url' ], 'dsg-settings', 'dsg_api_section' );
        add_settings_field( 'dsg_model', __( '模型', 'deepseek-generator' ), [ __CLASS__, 'field_model' ], 'dsg-settings', 'dsg_api_section' );

        add_settings_section(
            'dsg_params_section',
            __( '默认参数', 'deepseek-generator' ),
            null,
            'dsg-settings'
        );

        add_settings_field( 'dsg_temperature', __( 'Temperature', 'deepseek-generator' ), [ __CLASS__, 'field_temperature' ], 'dsg-settings', 'dsg_params_section' );
        add_settings_field( 'dsg_max_tokens', __( 'Max Tokens', 'deepseek-generator' ), [ __CLASS__, 'field_max_tokens' ], 'dsg-settings', 'dsg_params_section' );
        add_settings_field( 'dsg_top_p', __( 'Top P', 'deepseek-generator' ), [ __CLASS__, 'field_top_p' ], 'dsg-settings', 'dsg_params_section' );

        add_settings_section(
            'dsg_access_section',
            __( '访问控制', 'deepseek-generator' ),
            null,
            'dsg-settings'
        );

        add_settings_field( 'dsg_allow_guests', __( '允许未登录用户', 'deepseek-generator' ), [ __CLASS__, 'field_allow_guests' ], 'dsg-settings', 'dsg_access_section' );
        add_settings_field( 'dsg_rate_limit', __( '频率限制（次/分钟）', 'deepseek-generator' ), [ __CLASS__, 'field_rate_limit' ], 'dsg-settings', 'dsg_access_section' );

        add_settings_section(
            'dsg_smoke_section',
            __( '烟雾背景特效', 'deepseek-generator' ),
            null,
            'dsg-settings'
        );

        add_settings_field( 'dsg_smoke_enabled', __( '启用烟雾背景', 'deepseek-generator' ), [ __CLASS__, 'field_smoke_enabled' ], 'dsg-settings', 'dsg_smoke_section' );
        add_settings_field( 'dsg_smoke_particles', __( '粒子数量', 'deepseek-generator' ), [ __CLASS__, 'field_smoke_particles' ], 'dsg-settings', 'dsg_smoke_section' );
        add_settings_field( 'dsg_smoke_speed', __( '上升速度', 'deepseek-generator' ), [ __CLASS__, 'field_smoke_speed' ], 'dsg-settings', 'dsg_smoke_section' );
        add_settings_field( 'dsg_smoke_spread', __( '扩散幅度', 'deepseek-generator' ), [ __CLASS__, 'field_smoke_spread' ], 'dsg-settings', 'dsg_smoke_section' );
        add_settings_field( 'dsg_smoke_opacity', __( '透明度', 'deepseek-generator' ), [ __CLASS__, 'field_smoke_opacity' ], 'dsg-settings', 'dsg_smoke_section' );
        add_settings_field( 'dsg_smoke_breathe', __( '脉动强度', 'deepseek-generator' ), [ __CLASS__, 'field_smoke_breathe' ], 'dsg-settings', 'dsg_smoke_section' );
        add_settings_field( 'dsg_smoke_color', __( '烟雾颜色', 'deepseek-generator' ), [ __CLASS__, 'field_smoke_color' ], 'dsg-settings', 'dsg_smoke_section' );
    }

    public static function sanitize_settings( $input ): array {
        $old  = get_option( 'dsg_settings', [] );
        $safe = [];

        $raw_key = $input['api_key'] ?? '';
        if ( ! empty( $raw_key ) && $raw_key !== '••••••••' ) {
            $safe['api_key'] = DSG_API::encrypt_api_key( $raw_key );
        } else {
            $safe['api_key'] = $old['api_key'] ?? '';
        }

        $safe['base_url']     = esc_url_raw( $input['base_url'] ?? 'https://api.deepseek.com' );
        $safe['model']        = sanitize_text_field( $input['model'] ?? 'deepseek-v4-pro' );
        $safe['temperature']  = max( 0, min( 2, (float) ( $input['temperature'] ?? 1 ) ) );
        $safe['max_tokens']   = max( 1, (int) ( $input['max_tokens'] ?? 2048 ) );
        $safe['top_p']        = max( 0, min( 1, (float) ( $input['top_p'] ?? 1 ) ) );
        $safe['allow_guests'] = ! empty( $input['allow_guests'] );
        $safe['rate_limit']   = max( 0, (int) ( $input['rate_limit'] ?? 10 ) );

        $safe['smoke_enabled']   = ! empty( $input['smoke_enabled'] );
        $safe['smoke_particles'] = max( 10, min( 300, (int) ( $input['smoke_particles'] ?? 80 ) ) );
        $safe['smoke_speed']     = max( 0.1, min( 3.0, (float) ( $input['smoke_speed'] ?? 0.6 ) ) );
        $safe['smoke_opacity']   = max( 0.05, min( 0.8, (float) ( $input['smoke_opacity'] ?? 0.3 ) ) );
        $safe['smoke_color']     = sanitize_hex_color( $input['smoke_color'] ?? '#4f46e5' ) ?: '#4f46e5';
        $safe['smoke_spread']    = max( 0.3, min( 3.0, (float) ( $input['smoke_spread'] ?? 1.0 ) ) );
        $safe['smoke_breathe']   = max( 0.0, min( 1.0, (float) ( $input['smoke_breathe'] ?? 0.5 ) ) );

        return $safe;
    }

    private static function opt( string $key, $default = '' ) {
        $settings = get_option( 'dsg_settings', [] );
        return $settings[ $key ] ?? $default;
    }

    public static function field_api_key(): void {
        $has_key = ! empty( self::opt( 'api_key' ) );
        printf(
            '<input type="password" name="dsg_settings[api_key]" value="%s" class="regular-text" autocomplete="off" />',
            $has_key ? '••••••••' : ''
        );
        if ( $has_key ) {
            echo '<p class="description">' . esc_html__( '已配置。留空或保留圆点以保持不变，输入新值则替换。', 'deepseek-generator' ) . '</p>';
        }
    }

    public static function field_base_url(): void {
        printf(
            '<input type="url" name="dsg_settings[base_url]" value="%s" class="regular-text" />',
            esc_attr( self::opt( 'base_url', 'https://api.deepseek.com' ) )
        );
    }

    public static function field_model(): void {
        $model = self::opt( 'model', 'deepseek-v4-pro' );
        echo '<select name="dsg_settings[model]">';
        foreach ( [ 'deepseek-v4-pro' => 'DeepSeek V4 Pro', 'deepseek-v4-flash' => 'DeepSeek V4 Flash' ] as $val => $label ) {
            printf( '<option value="%s"%s>%s</option>', esc_attr( $val ), selected( $model, $val, false ), esc_html( $label ) );
        }
        echo '</select>';
    }

    public static function field_temperature(): void {
        printf(
            '<input type="number" name="dsg_settings[temperature]" value="%s" min="0" max="2" step="0.1" class="small-text" />',
            esc_attr( self::opt( 'temperature', 1 ) )
        );
    }

    public static function field_max_tokens(): void {
        printf(
            '<input type="number" name="dsg_settings[max_tokens]" value="%s" min="1" max="131072" step="1" class="small-text" />',
            esc_attr( self::opt( 'max_tokens', 2048 ) )
        );
    }

    public static function field_top_p(): void {
        printf(
            '<input type="number" name="dsg_settings[top_p]" value="%s" min="0" max="1" step="0.05" class="small-text" />',
            esc_attr( self::opt( 'top_p', 1 ) )
        );
    }

    public static function field_allow_guests(): void {
        printf(
            '<label><input type="checkbox" name="dsg_settings[allow_guests]" value="1" %s /> %s</label>',
            checked( self::opt( 'allow_guests' ), true, false ),
            esc_html__( '允许未登录用户使用生成功能', 'deepseek-generator' )
        );
    }

    public static function field_rate_limit(): void {
        printf(
            '<input type="number" name="dsg_settings[rate_limit]" value="%s" min="0" step="1" class="small-text" />',
            esc_attr( self::opt( 'rate_limit', 10 ) )
        );
        echo '<p class="description">' . esc_html__( '每个用户/IP 每分钟最大请求次数，0 表示不限制。', 'deepseek-generator' ) . '</p>';
    }

    public static function field_smoke_enabled(): void {
        printf(
            '<label><input type="checkbox" name="dsg_settings[smoke_enabled]" value="1" %s /> %s</label>',
            checked( self::opt( 'smoke_enabled' ), true, false ),
            esc_html__( '在生成器区块底部渲染烟雾升腾背景动画', 'deepseek-generator' )
        );
    }

    public static function field_smoke_particles(): void {
        printf(
            '<input type="number" name="dsg_settings[smoke_particles]" value="%s" min="10" max="300" step="10" class="small-text" />',
            esc_attr( self::opt( 'smoke_particles', 80 ) )
        );
        echo '<p class="description">' . esc_html__( '越多越厚实，但对性能影响更大。推荐 60-120。', 'deepseek-generator' ) . '</p>';
    }

    public static function field_smoke_speed(): void {
        printf(
            '<input type="number" name="dsg_settings[smoke_speed]" value="%s" min="0.1" max="3.0" step="0.1" class="small-text" />',
            esc_attr( self::opt( 'smoke_speed', 0.6 ) )
        );
        echo '<p class="description">' . esc_html__( '烟雾上升的快慢，1.0 为正常速度。', 'deepseek-generator' ) . '</p>';
    }

    public static function field_smoke_spread(): void {
        printf(
            '<input type="number" name="dsg_settings[smoke_spread]" value="%s" min="0.3" max="3.0" step="0.1" class="small-text" />',
            esc_attr( self::opt( 'smoke_spread', 1.0 ) )
        );
        echo '<p class="description">' . esc_html__( '烟雾水平扩散的宽度，值越大扩散越宽。', 'deepseek-generator' ) . '</p>';
    }

    public static function field_smoke_opacity(): void {
        printf(
            '<input type="number" name="dsg_settings[smoke_opacity]" value="%s" min="0.05" max="0.8" step="0.05" class="small-text" />',
            esc_attr( self::opt( 'smoke_opacity', 0.3 ) )
        );
        echo '<p class="description">' . esc_html__( '烟雾的可见程度，0.05 几乎不可见，0.8 非常浓厚。', 'deepseek-generator' ) . '</p>';
    }

    public static function field_smoke_breathe(): void {
        printf(
            '<input type="number" name="dsg_settings[smoke_breathe]" value="%s" min="0" max="1.0" step="0.05" class="small-text" />',
            esc_attr( self::opt( 'smoke_breathe', 0.5 ) )
        );
        echo '<p class="description">' . esc_html__( '烟雾透明度脉动变化的幅度，0 表示不脉动。', 'deepseek-generator' ) . '</p>';
    }

    public static function field_smoke_color(): void {
        printf(
            '<input type="color" name="dsg_settings[smoke_color]" value="%s" />',
            esc_attr( self::opt( 'smoke_color', '#4f46e5' ) )
        );
    }

    public static function render_settings_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }
        ?>
        <div class="wrap">
            <h1><?php esc_html_e( 'DeepSeek Generator 设置', 'deepseek-generator' ); ?></h1>
            <form method="post" action="options.php">
                <?php
                settings_fields( 'dsg_settings_group' );
                do_settings_sections( 'dsg-settings' );
                submit_button();
                ?>
            </form>
        </div>
        <?php
    }

    /* ── Prompt Template Meta Boxes ── */

    public static function add_prompt_meta_boxes(): void {
        add_meta_box(
            'dsg_prompt_fields',
            __( '模板配置', 'deepseek-generator' ),
            [ __CLASS__, 'render_prompt_meta_box' ],
            'ds_prompt_template',
            'normal',
            'high'
        );
    }

    public static function render_prompt_meta_box( $post ): void {
        wp_nonce_field( 'dsg_save_prompt', 'dsg_prompt_nonce' );

        $system_prompt    = get_post_meta( $post->ID, '_ds_system_prompt', true );
        $user_template    = get_post_meta( $post->ID, '_ds_user_prompt_template', true );
        $output_example   = get_post_meta( $post->ID, '_ds_output_example', true );
        $style_reference  = get_post_meta( $post->ID, '_ds_style_reference', true );
        $style_instruction = get_post_meta( $post->ID, '_ds_style_instruction', true );
        $ref_enabled      = get_post_meta( $post->ID, '_ds_ref_enabled', true );
        $ref_keywords     = get_post_meta( $post->ID, '_ds_ref_keywords', true );
        $ref_tags         = get_post_meta( $post->ID, '_ds_ref_tags', true );
        $ref_limit        = get_post_meta( $post->ID, '_ds_ref_limit', true );
        $temperature      = get_post_meta( $post->ID, '_ds_temperature', true );
        $max_tokens       = get_post_meta( $post->ID, '_ds_max_tokens', true );
        $allow_save       = get_post_meta( $post->ID, '_ds_allow_save', true );
        $show_history     = get_post_meta( $post->ID, '_ds_show_history', true );
        ?>
        <table class="form-table dsg-meta-table">
            <tr>
                <th><label for="ds_system_prompt"><?php esc_html_e( '系统提示词 (System Prompt)', 'deepseek-generator' ); ?></label></th>
                <td>
                    <textarea id="ds_system_prompt" name="ds_system_prompt" rows="5" class="large-text"><?php echo esc_textarea( $system_prompt ); ?></textarea>
                    <p class="description"><?php esc_html_e( '定义 AI 的角色和行为规则。', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ds_user_prompt_template"><?php esc_html_e( '用户提示词模板', 'deepseek-generator' ); ?></label></th>
                <td>
                    <textarea id="ds_user_prompt_template" name="ds_user_prompt_template" rows="5" class="large-text"><?php echo esc_textarea( $user_template ); ?></textarea>
                    <p class="description"><?php esc_html_e( '使用 {{user_input}} 作为用户输入的占位符。例如：请将以下文本翻译为英文：{{user_input}}', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ds_output_example"><?php esc_html_e( '输出样例', 'deepseek-generator' ); ?></label></th>
                <td>
                    <textarea id="ds_output_example" name="ds_output_example" rows="5" class="large-text"><?php echo esc_textarea( $output_example ); ?></textarea>
                    <p class="description"><?php esc_html_e( '展示给用户的示例输出，帮助用户理解该模板的用途。', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ds_style_reference"><?php esc_html_e( '参考文本（风格仿写样本）', 'deepseek-generator' ); ?></label></th>
                <td>
                    <textarea id="ds_style_reference" name="ds_style_reference" rows="4" class="large-text"><?php echo esc_textarea( $style_reference ); ?></textarea>
                    <p class="description"><?php esc_html_e( '用于让 AI 模仿该文本的风格、用词与句式。留空则不启用仿写。', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ds_style_instruction"><?php esc_html_e( '风格/修辞说明', 'deepseek-generator' ); ?></label></th>
                <td>
                    <input type="text" id="ds_style_instruction" name="ds_style_instruction" value="<?php echo esc_attr( $style_instruction ); ?>" class="large-text" />
                    <p class="description"><?php esc_html_e( '如：正式书面、排比比喻、口语短句等。留空则不追加。', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( 'RAG 参考检索', 'deepseek-generator' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ds_ref_enabled" value="1" <?php checked( $ref_enabled, '1' ); ?> />
                        <?php esc_html_e( '启用参考文档检索（RAG）', 'deepseek-generator' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( '勾选后，生成时将从参考文档库中检索相关材料并拼接到提示词，以增强行文逻辑性。', 'deepseek-generator' ); ?></p>

                    <fieldset style="margin-top:10px;padding:10px;border:1px solid #ccd0d4;border-radius:4px;">
                        <legend style="padding:0 6px;font-weight:600;"><?php esc_html_e( '检索配置', 'deepseek-generator' ); ?></legend>

                        <p>
                            <label for="ds_ref_keywords"><?php esc_html_e( '参考关键词', 'deepseek-generator' ); ?></label><br>
                            <input type="text" id="ds_ref_keywords" name="ds_ref_keywords" value="<?php echo esc_attr( $ref_keywords ); ?>" class="large-text" />
                            <span class="description"><?php esc_html_e( '逗号分隔的主题词，用于在参考文档中搜索。留空则仅使用用户输入。', 'deepseek-generator' ); ?></span>
                        </p>

                        <p>
                            <label for="ds_ref_tags"><?php esc_html_e( '参考标签', 'deepseek-generator' ); ?></label><br>
                            <input type="text" id="ds_ref_tags" name="ds_ref_tags" value="<?php echo esc_attr( $ref_tags ); ?>" class="large-text" />
                            <span class="description"><?php esc_html_e( '逗号分隔的标签 slug，筛选参考文档库中带有这些标签的文档。留空则不按标签筛选。', 'deepseek-generator' ); ?></span>
                        </p>

                        <p>
                            <label for="ds_ref_limit"><?php esc_html_e( '最大参考条数', 'deepseek-generator' ); ?></label><br>
                            <input type="number" id="ds_ref_limit" name="ds_ref_limit" value="<?php echo esc_attr( $ref_limit ); ?>" min="1" max="10" step="1" class="small-text" />
                            <span class="description"><?php esc_html_e( '留空默认 3 条。', 'deepseek-generator' ); ?></span>
                        </p>
                    </fieldset>
                </td>
            </tr>
            <tr>
                <th><label for="ds_temperature"><?php esc_html_e( 'Temperature 覆盖', 'deepseek-generator' ); ?></label></th>
                <td>
                    <input type="number" id="ds_temperature" name="ds_temperature" value="<?php echo esc_attr( $temperature ); ?>" min="0" max="2" step="0.1" class="small-text" />
                    <p class="description"><?php esc_html_e( '留空则使用全局默认值。', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><label for="ds_max_tokens"><?php esc_html_e( 'Max Tokens 覆盖', 'deepseek-generator' ); ?></label></th>
                <td>
                    <input type="number" id="ds_max_tokens" name="ds_max_tokens" value="<?php echo esc_attr( $max_tokens ); ?>" min="1" max="131072" step="1" class="small-text" />
                    <p class="description"><?php esc_html_e( '留空则使用全局默认值。', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
            <tr>
                <th><?php esc_html_e( '持久化保存', 'deepseek-generator' ); ?></th>
                <td>
                    <label>
                        <input type="checkbox" name="ds_allow_save" value="1" <?php checked( $allow_save, '1' ); ?> />
                        <?php esc_html_e( '允许用户将生成结果保存到数据库', 'deepseek-generator' ); ?>
                    </label>
                    <br>
                    <label>
                        <input type="checkbox" name="ds_show_history" value="1" <?php checked( $show_history, '1' ); ?> />
                        <?php esc_html_e( '在前端展示已保存的历史生成记录', 'deepseek-generator' ); ?>
                    </label>
                    <p class="description"><?php esc_html_e( '启用后，用户生成文本后可选择保存，其他访客也能看到已保存的内容。', 'deepseek-generator' ); ?></p>
                </td>
            </tr>
        </table>

        <div class="dsg-shortcode-hint" style="margin-top:16px;padding:12px;background:#f0f6fc;border-left:4px solid #2271b1;border-radius:2px;">
            <strong><?php esc_html_e( '使用方法', 'deepseek-generator' ); ?></strong><br>
            <?php
            printf(
                esc_html__( 'Shortcode: %s', 'deepseek-generator' ),
                '<code>[deepseek_gen id="' . $post->ID . '"]</code>'
            );
            ?>
            <br>
            <?php esc_html_e( 'Gutenberg Block: 在编辑器中搜索 "DeepSeek" 并选择对应模板。', 'deepseek-generator' ); ?>
        </div>
        <?php
    }

    public static function save_prompt_meta( int $post_id, $post ): void {
        if ( ! isset( $_POST['dsg_prompt_nonce'] ) || ! wp_verify_nonce( $_POST['dsg_prompt_nonce'], 'dsg_save_prompt' ) ) {
            return;
        }
        if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) {
            return;
        }
        if ( ! current_user_can( 'edit_post', $post_id ) ) {
            return;
        }

        $fields = [
            '_ds_system_prompt'         => 'ds_system_prompt',
            '_ds_user_prompt_template'  => 'ds_user_prompt_template',
            '_ds_output_example'        => 'ds_output_example',
            '_ds_style_reference'       => 'ds_style_reference',
            '_ds_style_instruction'     => 'ds_style_instruction',
        ];
        foreach ( $fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_textarea_field( $_POST[ $post_key ] ) );
            }
        }

        $num_fields = [
            '_ds_temperature' => 'ds_temperature',
            '_ds_max_tokens'  => 'ds_max_tokens',
        ];
        foreach ( $num_fields as $meta_key => $post_key ) {
            $val = $_POST[ $post_key ] ?? '';
            if ( $val === '' ) {
                delete_post_meta( $post_id, $meta_key );
            } else {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $val ) );
            }
        }

        $text_fields = [
            '_ds_ref_keywords' => 'ds_ref_keywords',
            '_ds_ref_tags'     => 'ds_ref_tags',
        ];
        foreach ( $text_fields as $meta_key => $post_key ) {
            if ( isset( $_POST[ $post_key ] ) ) {
                update_post_meta( $post_id, $meta_key, sanitize_text_field( $_POST[ $post_key ] ) );
            }
        }

        $ref_limit_val = $_POST['ds_ref_limit'] ?? '';
        if ( $ref_limit_val === '' ) {
            delete_post_meta( $post_id, '_ds_ref_limit' );
        } else {
            update_post_meta( $post_id, '_ds_ref_limit', sanitize_text_field( $ref_limit_val ) );
        }

        $checkboxes = [
            '_ds_allow_save'  => 'ds_allow_save',
            '_ds_show_history' => 'ds_show_history',
            '_ds_ref_enabled' => 'ds_ref_enabled',
        ];
        foreach ( $checkboxes as $meta_key => $post_key ) {
            update_post_meta( $post_id, $meta_key, empty( $_POST[ $post_key ] ) ? '' : '1' );
        }
    }

    public static function enqueue_admin_assets( string $hook ): void {
        $screen = get_current_screen();
        if ( $screen && in_array( $screen->post_type, [ 'ds_prompt_template', 'ds_reference_doc' ], true ) ) {
            wp_enqueue_style( 'dsg-admin', DSG_PLUGIN_URL . 'assets/css/admin.css', [], DSG_VERSION );
        }
    }
}
