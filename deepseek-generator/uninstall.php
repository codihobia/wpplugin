<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'dsg_settings' );

$templates = get_posts( [
    'post_type'      => 'ds_prompt_template',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );

foreach ( $templates as $id ) {
    wp_delete_post( $id, true );
}

global $wpdb;
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->postmeta} WHERE meta_key LIKE %s",
        '_ds_%'
    )
);

$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_dsg_rate_%'
    )
);
$wpdb->query(
    $wpdb->prepare(
        "DELETE FROM {$wpdb->options} WHERE option_name LIKE %s",
        '_transient_timeout_dsg_rate_%'
    )
);
