<?php
/**
 * Plugin Name: DSSAST CMS
 * Description: 笃实书院学生科协赛事、笔记审核与秀米同步。
 * Version: 0.4.0
 * Requires at least: 6.6
 * Requires PHP: 7.4
 * Text Domain: dssast-cms
 */
defined( 'ABSPATH' ) || exit;
define( 'DSSAST_CMS_FILE', __FILE__ );
require_once __DIR__ . '/includes/competitions.php';
require_once __DIR__ . '/includes/notes.php';
require_once __DIR__ . '/includes/xiumi.php';
require_once __DIR__ . '/includes/management.php';
require_once __DIR__ . '/includes/admin.php';
register_activation_hook( __FILE__, function () {
 dssast_register_competitions(); dssast_register_notes();
 dssast_ensure_categories();
 flush_rewrite_rules();
} );
register_deactivation_hook( __FILE__, function () { unregister_post_type( 'dssast_competition' ); unregister_post_type( 'dssast_note' ); flush_rewrite_rules(); } );
