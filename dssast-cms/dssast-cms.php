<?php
/**
 * Plugin Name: DSSAST CMS
 * Description: 笃实书院学生科协赛事、笔记审核与秀米同步。
 * Version: 0.2.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: dssast-cms
 */
defined( 'ABSPATH' ) || exit;
require_once __DIR__ . '/includes/competitions.php';
require_once __DIR__ . '/includes/notes.php';
require_once __DIR__ . '/includes/xiumi.php';
register_activation_hook( __FILE__, function () {
 dssast_register_competitions(); dssast_register_notes();
 foreach ( array( 'dssast-news' => '新闻', 'dssast-announcement' => '公告' ) as $slug => $name ) {
  if ( ! get_term_by( 'slug', $slug, 'category' ) ) { wp_insert_term( $name, 'category', array( 'slug' => $slug ) ); }
 }
 flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, function () { unregister_post_type( 'dssast_competition' ); unregister_post_type( 'dssast_note' ); flush_rewrite_rules(); } );
