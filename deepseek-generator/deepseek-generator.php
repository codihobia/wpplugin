<?php
/**
 * Plugin Name: DeepSeek AI Generator
 * Plugin URI:  https://github.com/your-repo/deepseek-generator
 * Description: 调用 DeepSeek API 生成文本，支持 Shortcode 和 Gutenberg Block，可自定义提示词模板和输出样例。
 * Version:     1.0.0
 * Author:      Your Name
 * Author URI:  https://your-site.com
 * License:     GPL-2.0-or-later
 * Text Domain: deepseek-generator
 * Domain Path: /languages
 * Requires at least: 6.0
 * Requires PHP: 7.4
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'DSG_VERSION', '1.0.0' );
define( 'DSG_PLUGIN_FILE', __FILE__ );
define( 'DSG_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'DSG_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'DSG_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

require_once DSG_PLUGIN_DIR . 'includes/class-api.php';
require_once DSG_PLUGIN_DIR . 'includes/class-admin.php';
require_once DSG_PLUGIN_DIR . 'includes/class-retriever.php';
require_once DSG_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once DSG_PLUGIN_DIR . 'includes/class-block.php';
require_once DSG_PLUGIN_DIR . 'includes/class-history.php';

register_activation_hook( __FILE__, 'dsg_activate' );
register_deactivation_hook( __FILE__, 'dsg_deactivate' );

function dsg_activate() {
    DSG_Admin::register_prompt_cpt();
    DSG_Admin::register_reference_cpt();
    DSG_History::create_table();
    flush_rewrite_rules();

    if ( ! get_option( 'dsg_settings' ) ) {
        update_option( 'dsg_settings', [
            'api_key'      => '',
            'base_url'     => 'https://api.deepseek.com',
            'model'        => 'deepseek-v4-pro',
            'temperature'  => 1,
            'max_tokens'   => 2048,
            'top_p'        => 1,
            'allow_guests' => false,
            'rate_limit'   => 10,
            'smoke_enabled'  => false,
            'smoke_particles' => 80,
            'smoke_speed'     => 0.6,
            'smoke_opacity'   => 0.3,
            'smoke_color'     => '#4f46e5',
            'smoke_spread'    => 1.0,
            'smoke_breathe'   => 0.5,
            'smoke_mask_bottom' => 0.8,
            'smoke_mask_mid'    => 0.4,
            'smoke_mask_top'    => 0.1,
            'danmaku_enabled'      => false,
            'danmaku_interval'     => 3000,
            'danmaku_min_size'     => 1.2,
            'danmaku_max_size'     => 3.0,
            'danmaku_colors'       => 'rgba(255,255,255,0.5),rgba(255,255,255,0.3),rgba(200,200,255,0.4),rgba(255,220,200,0.35)',
            'danmaku_opacity_min'  => 0.15,
            'danmaku_opacity_max'  => 0.4,
            'danmaku_duration_min' => 8000,
            'danmaku_duration_max' => 15000,
            'danmaku_fade_in'      => 1500,
            'danmaku_fade_out'     => 2000,
            'danmaku_max_count'    => 6,
            'danmaku_drift_range'  => 60,
        ] );
    }
}

function dsg_deactivate() {
    flush_rewrite_rules();
}

add_action( 'init', [ 'DSG_Admin', 'register_prompt_cpt' ] );
add_action( 'init', [ 'DSG_Admin', 'register_reference_cpt' ] );
add_action( 'admin_menu', [ 'DSG_Admin', 'add_menu_page' ] );
add_action( 'admin_init', [ 'DSG_Admin', 'register_settings' ] );
add_action( 'add_meta_boxes', [ 'DSG_Admin', 'add_prompt_meta_boxes' ] );
add_action( 'save_post_ds_prompt_template', [ 'DSG_Admin', 'save_prompt_meta' ], 10, 2 );
add_action( 'admin_enqueue_scripts', [ 'DSG_Admin', 'enqueue_admin_assets' ] );

add_action( 'wp_ajax_deepseek_generate', [ 'DSG_API', 'ajax_handler' ] );
add_action( 'wp_ajax_nopriv_deepseek_generate', [ 'DSG_API', 'ajax_handler_nopriv' ] );

add_action( 'wp_ajax_dsg_save_output', [ 'DSG_History', 'ajax_save' ] );
add_action( 'wp_ajax_nopriv_dsg_save_output', [ 'DSG_History', 'ajax_save_nopriv' ] );
add_action( 'wp_ajax_dsg_load_outputs', [ 'DSG_History', 'ajax_load' ] );
add_action( 'wp_ajax_nopriv_dsg_load_outputs', [ 'DSG_History', 'ajax_load_nopriv' ] );

add_action( 'init', [ 'DSG_Block', 'register' ] );

DSG_Shortcode::init();
