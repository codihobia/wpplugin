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
require_once DSG_PLUGIN_DIR . 'includes/class-shortcode.php';
require_once DSG_PLUGIN_DIR . 'includes/class-block.php';
require_once DSG_PLUGIN_DIR . 'includes/class-history.php';

register_activation_hook( __FILE__, 'dsg_activate' );
register_deactivation_hook( __FILE__, 'dsg_deactivate' );

function dsg_activate() {
    DSG_Admin::register_prompt_cpt();
    DSG_History::create_table();
    flush_rewrite_rules();

    if ( ! get_option( 'dsg_settings' ) ) {
        update_option( 'dsg_settings', [
            'api_key'      => '',
            'base_url'     => 'https://api.deepseek.com',
            'model'        => 'deepseek-chat',
            'temperature'  => 1,
            'max_tokens'   => 2048,
            'top_p'        => 1,
            'allow_guests' => false,
            'rate_limit'   => 10,
        ] );
    }
}

function dsg_deactivate() {
    flush_rewrite_rules();
}

add_action( 'init', [ 'DSG_Admin', 'register_prompt_cpt' ] );
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
