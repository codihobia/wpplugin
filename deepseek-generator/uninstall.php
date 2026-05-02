<?php
/**
 * Uninstall handler — runs when the plugin is deleted via WordPress admin.
 */

if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

delete_option( 'dsg_settings' );
delete_option( 'dsg_db_version' );

global $wpdb;
$wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}dsg_outputs" );

$templates = get_posts( [
    'post_type'      => 'ds_prompt_template',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );

foreach ( $templates as $id ) {
    wp_delete_post( $id, true );
}

$ref_docs = get_posts( [
    'post_type'      => 'ds_reference_doc',
    'posts_per_page' => -1,
    'post_status'    => 'any',
    'fields'         => 'ids',
] );

foreach ( $ref_docs as $id ) {
    wp_delete_post( $id, true );
}

$ref_terms = get_terms( [
    'taxonomy'   => 'ds_ref_tag',
    'hide_empty' => false,
    'fields'     => 'ids',
] );
if ( ! is_wp_error( $ref_terms ) ) {
    foreach ( $ref_terms as $term_id ) {
        wp_delete_term( $term_id, 'ds_ref_tag' );
    }
}

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
