<?php

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

class DSG_History {

    public static function table_name(): string {
        global $wpdb;
        return $wpdb->prefix . 'dsg_outputs';
    }

    public static function create_table(): void {
        global $wpdb;
        $table   = self::table_name();
        $charset = $wpdb->get_charset_collate();

        $sql = "CREATE TABLE {$table} (
            id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
            template_id bigint(20) unsigned NOT NULL,
            user_id bigint(20) unsigned DEFAULT 0,
            user_input text NOT NULL,
            output longtext NOT NULL,
            status varchar(20) DEFAULT 'published',
            created_at datetime DEFAULT CURRENT_TIMESTAMP,
            PRIMARY KEY  (id),
            KEY idx_template_status (template_id, status),
            KEY idx_created (created_at)
        ) {$charset};";

        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );

        update_option( 'dsg_db_version', '1.0' );
    }

    public static function insert( int $template_id, string $user_input, string $output, int $user_id = 0 ): int {
        global $wpdb;
        $wpdb->insert( self::table_name(), [
            'template_id' => $template_id,
            'user_id'     => $user_id,
            'user_input'  => $user_input,
            'output'      => $output,
            'status'      => 'published',
            'created_at'  => current_time( 'mysql' ),
        ], [ '%d', '%d', '%s', '%s', '%s', '%s' ] );
        delete_transient( 'dsg_danmaku_sentences' );
        return (int) $wpdb->insert_id;
    }

    public static function delete( int $id ): bool {
        global $wpdb;
        $result = (bool) $wpdb->delete( self::table_name(), [ 'id' => $id ], [ '%d' ] );
        if ( $result ) {
            delete_transient( 'dsg_danmaku_sentences' );
        }
        return $result;
    }

    public static function bulk_delete( array $ids ): int {
        global $wpdb;
        if ( empty( $ids ) ) {
            return 0;
        }
        $ids_placeholder = implode( ',', array_map( 'intval', $ids ) );
        $count = (int) $wpdb->query( "DELETE FROM " . self::table_name() . " WHERE id IN ({$ids_placeholder})" );
        if ( $count > 0 ) {
            delete_transient( 'dsg_danmaku_sentences' );
        }
        return $count;
    }

    public static function update_status( int $id, string $status ): bool {
        global $wpdb;
        return (bool) $wpdb->update(
            self::table_name(),
            [ 'status' => $status ],
            [ 'id' => $id ],
            [ '%s' ],
            [ '%d' ]
        );
    }

    public static function get_by_template( int $template_id, int $per_page = 20, int $page = 1 ): array {
        global $wpdb;
        $table  = self::table_name();
        $offset = ( $page - 1 ) * $per_page;

        $rows = $wpdb->get_results( $wpdb->prepare(
            "SELECT * FROM {$table} WHERE template_id = %d AND status = 'published' ORDER BY created_at DESC LIMIT %d OFFSET %d",
            $template_id, $per_page, $offset
        ), ARRAY_A );

        return $rows ?: [];
    }

    public static function count_by_template( int $template_id ): int {
        global $wpdb;
        $table = self::table_name();
        return (int) $wpdb->get_var( $wpdb->prepare(
            "SELECT COUNT(*) FROM {$table} WHERE template_id = %d AND status = 'published'",
            $template_id
        ) );
    }

    public static function get_all_paginated( array $args = [] ): array {
        global $wpdb;
        $table    = self::table_name();
        $per_page = (int) ( $args['per_page'] ?? 20 );
        $page     = (int) ( $args['page'] ?? 1 );
        $offset   = ( $page - 1 ) * $per_page;
        $search   = $args['search'] ?? '';
        $template = (int) ( $args['template_id'] ?? 0 );
        $status   = $args['status'] ?? '';

        $where = [];
        $vals  = [];

        if ( $template > 0 ) {
            $where[] = 'template_id = %d';
            $vals[]  = $template;
        }
        if ( $status ) {
            $where[] = 'status = %s';
            $vals[]  = $status;
        }
        if ( $search ) {
            $where[] = '(user_input LIKE %s OR output LIKE %s)';
            $vals[]  = '%' . $wpdb->esc_like( $search ) . '%';
            $vals[]  = '%' . $wpdb->esc_like( $search ) . '%';
        }

        $where_sql = $where ? 'WHERE ' . implode( ' AND ', $where ) : '';

        $count_query = "SELECT COUNT(*) FROM {$table} {$where_sql}";
        $data_query  = "SELECT * FROM {$table} {$where_sql} ORDER BY created_at DESC LIMIT %d OFFSET %d";

        $vals_count = $vals;
        $vals_data  = array_merge( $vals, [ $per_page, $offset ] );

        $total = $vals_count
            ? (int) $wpdb->get_var( $wpdb->prepare( $count_query, ...$vals_count ) )
            : (int) $wpdb->get_var( $count_query );

        $rows = $vals_data
            ? $wpdb->get_results( $wpdb->prepare( $data_query, ...$vals_data ), ARRAY_A )
            : $wpdb->get_results( $wpdb->prepare( $data_query, $per_page, $offset ), ARRAY_A );

        return [
            'items' => $rows ?: [],
            'total' => $total,
        ];
    }

    public static function drop_table(): void {
        global $wpdb;
        $wpdb->query( "DROP TABLE IF EXISTS " . self::table_name() );
        delete_option( 'dsg_db_version' );
    }

    /* ── AJAX Handlers ── */

    public static function ajax_save(): void {
        check_ajax_referer( 'dsg_nonce', 'nonce' );

        $template_id = (int) ( $_POST['template_id'] ?? 0 );
        $user_input  = sanitize_textarea_field( $_POST['user_input'] ?? '' );
        $output      = wp_kses_post( $_POST['output'] ?? '' );

        if ( ! $template_id || get_post_type( $template_id ) !== 'ds_prompt_template' ) {
            wp_send_json_error( __( '无效的模板。', 'deepseek-generator' ), 400 );
        }

        $allow_save = get_post_meta( $template_id, '_ds_allow_save', true );
        if ( empty( $allow_save ) ) {
            wp_send_json_error( __( '该模板未启用保存功能。', 'deepseek-generator' ), 403 );
        }

        if ( empty( $output ) ) {
            wp_send_json_error( __( '无内容可保存。', 'deepseek-generator' ), 400 );
        }

        $user_id   = is_user_logged_in() ? get_current_user_id() : 0;
        $record_id = self::insert( $template_id, $user_input, $output, $user_id );

        if ( ! $record_id ) {
            wp_send_json_error( __( '保存失败。', 'deepseek-generator' ), 500 );
        }

        wp_send_json_success( [
            'id'         => $record_id,
            'created_at' => current_time( 'Y-m-d H:i' ),
            'user_name'  => $user_id ? get_userdata( $user_id )->display_name : __( '访客', 'deepseek-generator' ),
        ] );
    }

    public static function ajax_save_nopriv(): void {
        $settings = get_option( 'dsg_settings', [] );
        if ( empty( $settings['allow_guests'] ) ) {
            wp_send_json_error( __( '请先登录。', 'deepseek-generator' ), 403 );
        }
        self::ajax_save();
    }

    public static function ajax_load(): void {
        check_ajax_referer( 'dsg_nonce', 'nonce' );

        $template_id = (int) ( $_GET['template_id'] ?? $_POST['template_id'] ?? 0 );
        $page        = max( 1, (int) ( $_GET['page'] ?? $_POST['page'] ?? 1 ) );
        $per_page    = 10;

        if ( ! $template_id ) {
            wp_send_json_error( __( '缺少模板 ID。', 'deepseek-generator' ), 400 );
        }

        $show_history = get_post_meta( $template_id, '_ds_show_history', true );
        if ( empty( $show_history ) ) {
            wp_send_json_success( [ 'items' => [], 'total' => 0, 'pages' => 0 ] );
        }

        $items = self::get_by_template( $template_id, $per_page, $page );
        $total = self::count_by_template( $template_id );

        $formatted = array_map( function ( $row ) {
            $user_name = $row['user_id']
                ? ( get_userdata( $row['user_id'] )->display_name ?? __( '用户', 'deepseek-generator' ) )
                : __( '访客', 'deepseek-generator' );
            return [
                'id'         => (int) $row['id'],
                'user_input' => $row['user_input'],
                'output'     => $row['output'],
                'user_name'  => $user_name,
                'created_at' => mysql2date( 'Y-m-d H:i', $row['created_at'] ),
            ];
        }, $items );

        wp_send_json_success( [
            'items' => $formatted,
            'total' => $total,
            'pages' => ceil( $total / $per_page ),
        ] );
    }

    public static function ajax_load_nopriv(): void {
        self::ajax_load();
    }

    /* ── Admin: WP_List_Table for managing records ── */

    public static function render_admin_page(): void {
        if ( ! current_user_can( 'manage_options' ) ) {
            return;
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'delete' && isset( $_GET['record_id'] ) ) {
            check_admin_referer( 'dsg_delete_record_' . $_GET['record_id'] );
            self::delete( (int) $_GET['record_id'] );
            echo '<div class="notice notice-success"><p>' . esc_html__( '记录已删除。', 'deepseek-generator' ) . '</p></div>';
        }

        if ( isset( $_POST['action'] ) && $_POST['action'] === 'bulk_delete' && ! empty( $_POST['record_ids'] ) ) {
            check_admin_referer( 'dsg_bulk_action' );
            $ids = array_map( 'intval', (array) $_POST['record_ids'] );
            $count = self::bulk_delete( $ids );
            echo '<div class="notice notice-success"><p>' . sprintf( esc_html__( '已删除 %d 条记录。', 'deepseek-generator' ), $count ) . '</p></div>';
        }

        if ( isset( $_GET['action'] ) && $_GET['action'] === 'toggle_status' && isset( $_GET['record_id'] ) ) {
            check_admin_referer( 'dsg_toggle_record_' . $_GET['record_id'] );
            global $wpdb;
            $row = $wpdb->get_row( $wpdb->prepare( "SELECT status FROM " . self::table_name() . " WHERE id = %d", (int) $_GET['record_id'] ), ARRAY_A );
            if ( $row ) {
                $new_status = $row['status'] === 'published' ? 'hidden' : 'published';
                self::update_status( (int) $_GET['record_id'], $new_status );
            }
            echo '<div class="notice notice-success"><p>' . esc_html__( '状态已更新。', 'deepseek-generator' ) . '</p></div>';
        }

        $per_page    = 20;
        $current_page = max( 1, (int) ( $_GET['paged'] ?? 1 ) );
        $search      = sanitize_text_field( $_GET['s'] ?? '' );
        $filter_tpl  = (int) ( $_GET['template_id'] ?? 0 );
        $filter_status = sanitize_text_field( $_GET['status'] ?? '' );

        $result = self::get_all_paginated( [
            'per_page'    => $per_page,
            'page'        => $current_page,
            'search'      => $search,
            'template_id' => $filter_tpl,
            'status'      => $filter_status,
        ] );

        $items      = $result['items'];
        $total      = $result['total'];
        $total_pages = ceil( $total / $per_page );

        $templates = get_posts( [
            'post_type'      => 'ds_prompt_template',
            'posts_per_page' => -1,
            'post_status'    => 'publish',
            'orderby'        => 'title',
            'order'          => 'ASC',
        ] );

        ?>
        <div class="wrap">
            <h1><?php esc_html_e( '生成记录管理', 'deepseek-generator' ); ?></h1>

            <form method="get" style="margin-bottom:16px;display:flex;gap:8px;align-items:center;flex-wrap:wrap;">
                <input type="hidden" name="page" value="dsg-history" />
                <select name="template_id">
                    <option value=""><?php esc_html_e( '所有模板', 'deepseek-generator' ); ?></option>
                    <?php foreach ( $templates as $t ) : ?>
                        <option value="<?php echo esc_attr( $t->ID ); ?>" <?php selected( $filter_tpl, $t->ID ); ?>>
                            <?php echo esc_html( $t->post_title ); ?>
                        </option>
                    <?php endforeach; ?>
                </select>
                <select name="status">
                    <option value=""><?php esc_html_e( '所有状态', 'deepseek-generator' ); ?></option>
                    <option value="published" <?php selected( $filter_status, 'published' ); ?>><?php esc_html_e( '已发布', 'deepseek-generator' ); ?></option>
                    <option value="hidden" <?php selected( $filter_status, 'hidden' ); ?>><?php esc_html_e( '已隐藏', 'deepseek-generator' ); ?></option>
                </select>
                <input type="search" name="s" value="<?php echo esc_attr( $search ); ?>" placeholder="<?php esc_attr_e( '搜索内容...', 'deepseek-generator' ); ?>" />
                <button type="submit" class="button"><?php esc_html_e( '筛选', 'deepseek-generator' ); ?></button>
            </form>

            <form method="post">
                <?php wp_nonce_field( 'dsg_bulk_action' ); ?>
                <div class="tablenav top">
                    <div class="alignleft actions bulkactions">
                        <select name="action">
                            <option value="-1"><?php esc_html_e( '批量操作', 'deepseek-generator' ); ?></option>
                            <option value="bulk_delete"><?php esc_html_e( '删除', 'deepseek-generator' ); ?></option>
                        </select>
                        <button type="submit" class="button action"><?php esc_html_e( '应用', 'deepseek-generator' ); ?></button>
                    </div>
                    <span class="displaying-num"><?php printf( esc_html__( '共 %d 条', 'deepseek-generator' ), $total ); ?></span>
                </div>

                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <td class="manage-column column-cb check-column"><input type="checkbox" /></td>
                            <th style="width:40px;">ID</th>
                            <th><?php esc_html_e( '模板', 'deepseek-generator' ); ?></th>
                            <th><?php esc_html_e( '用户输入', 'deepseek-generator' ); ?></th>
                            <th><?php esc_html_e( '生成输出', 'deepseek-generator' ); ?></th>
                            <th style="width:80px;"><?php esc_html_e( '作者', 'deepseek-generator' ); ?></th>
                            <th style="width:70px;"><?php esc_html_e( '状态', 'deepseek-generator' ); ?></th>
                            <th style="width:130px;"><?php esc_html_e( '时间', 'deepseek-generator' ); ?></th>
                            <th style="width:100px;"><?php esc_html_e( '操作', 'deepseek-generator' ); ?></th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php if ( empty( $items ) ) : ?>
                            <tr><td colspan="9"><?php esc_html_e( '暂无记录。', 'deepseek-generator' ); ?></td></tr>
                        <?php else : ?>
                            <?php foreach ( $items as $row ) : ?>
                                <tr>
                                    <th class="check-column"><input type="checkbox" name="record_ids[]" value="<?php echo esc_attr( $row['id'] ); ?>" /></th>
                                    <td><?php echo esc_html( $row['id'] ); ?></td>
                                    <td><?php echo esc_html( get_the_title( $row['template_id'] ) ?: '#' . $row['template_id'] ); ?></td>
                                    <td><span title="<?php echo esc_attr( $row['user_input'] ); ?>"><?php echo esc_html( mb_strimwidth( $row['user_input'], 0, 60, '...' ) ); ?></span></td>
                                    <td><span title="<?php echo esc_attr( wp_strip_all_tags( $row['output'] ) ); ?>"><?php echo esc_html( mb_strimwidth( wp_strip_all_tags( $row['output'] ), 0, 80, '...' ) ); ?></span></td>
                                    <td>
                                        <?php
                                        if ( $row['user_id'] ) {
                                            $u = get_userdata( $row['user_id'] );
                                            echo esc_html( $u ? $u->display_name : '#' . $row['user_id'] );
                                        } else {
                                            esc_html_e( '访客', 'deepseek-generator' );
                                        }
                                        ?>
                                    </td>
                                    <td>
                                        <?php if ( $row['status'] === 'published' ) : ?>
                                            <span style="color:#16a34a;"><?php esc_html_e( '已发布', 'deepseek-generator' ); ?></span>
                                        <?php else : ?>
                                            <span style="color:#9ca3af;"><?php esc_html_e( '已隐藏', 'deepseek-generator' ); ?></span>
                                        <?php endif; ?>
                                    </td>
                                    <td><?php echo esc_html( mysql2date( 'Y-m-d H:i', $row['created_at'] ) ); ?></td>
                                    <td>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsg-history&action=toggle_status&record_id=' . $row['id'] ), 'dsg_toggle_record_' . $row['id'] ) ); ?>"
                                           class="button button-small">
                                            <?php echo $row['status'] === 'published' ? esc_html__( '隐藏', 'deepseek-generator' ) : esc_html__( '发布', 'deepseek-generator' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( wp_nonce_url( admin_url( 'admin.php?page=dsg-history&action=delete&record_id=' . $row['id'] ), 'dsg_delete_record_' . $row['id'] ) ); ?>"
                                           class="button button-small"
                                           onclick="return confirm('<?php esc_attr_e( '确定删除此记录？', 'deepseek-generator' ); ?>');">
                                            <?php esc_html_e( '删除', 'deepseek-generator' ); ?>
                                        </a>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>

                <?php if ( $total_pages > 1 ) : ?>
                    <div class="tablenav bottom">
                        <div class="tablenav-pages">
                            <?php
                            $page_links = paginate_links( [
                                'base'      => add_query_arg( 'paged', '%#%' ),
                                'format'    => '',
                                'total'     => $total_pages,
                                'current'   => $current_page,
                                'prev_text' => '&laquo;',
                                'next_text' => '&raquo;',
                            ] );
                            echo $page_links;
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </form>
        </div>
        <?php
    }
}
