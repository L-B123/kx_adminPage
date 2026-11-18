<?php
defined( 'ABSPATH' ) || exit;

// Keep business forms usable when block-editor assets or REST requests fail.
add_filter( 'use_block_editor_for_post_type', function ( $enabled, $type ) {
 return in_array( $type, array( 'dssast_competition', 'dssast_note' ), true ) ? false : $enabled;
}, 100, 2 );

function dssast_ensure_categories() {
 foreach ( array( 'dssast-news' => '新闻', 'dssast-announcement' => '公告' ) as $slug => $name ) {
  if ( ! get_term_by( 'slug', $slug, 'category' ) ) { wp_insert_term( $name, 'category', array( 'slug' => $slug ) ); }
 }
}
add_action( 'init', function () {
 if ( '0.3.0' !== get_option( 'dssast_schema_version' ) ) {
  dssast_ensure_categories();
  if ( get_term_by( 'slug', 'dssast-news', 'category' ) && get_term_by( 'slug', 'dssast-announcement', 'category' ) ) { update_option( 'dssast_schema_version', '0.3.0', false ); }
 }
}, 20 );

add_action( 'admin_menu', function () {
 add_menu_page( '内容管理', '内容管理', 'manage_options', 'dssast-management', 'dssast_management_page', 'dashicons-welcome-write-blog', 25 );
} );
function dssast_management_page() {
 if ( ! current_user_can( 'manage_options' ) ) { return; }
 echo '<div class="wrap"><h1>内容管理</h1><p>赛事介绍与报名、学习笔记审核、新闻公告及秀米同步。</p>';
 $links = array( '赛事列表' => 'edit.php?post_type=dssast_competition', '添加赛事' => 'post-new.php?post_type=dssast_competition', '笔记管理' => 'edit.php?post_type=dssast_note', '添加笔记' => 'post-new.php?post_type=dssast_note', '待审核笔记' => 'edit.php?post_type=dssast_note&post_status=pending' );
 foreach ( array( 'dssast-news' => '新闻', 'dssast-announcement' => '公告' ) as $slug => $label ) {
  $term = get_term_by( 'slug', $slug, 'category' );
  if ( $term ) { $links[$label . '管理'] = 'edit.php?category_name=' . $slug; $links['添加' . $label] = 'post-new.php?dssast_category=' . $term->term_id; }
 }
 $links['秀米同步设置'] = 'tools.php?page=dssast-xiumi';
 foreach ( $links as $label => $url ) { echo '<p><a class="button" href="' . esc_url( admin_url( $url ) ) . '">' . esc_html( $label ) . '</a></p>'; }
 echo '<p>秀米稿件同步为草稿，审核发布后展示。前台可使用 [dssast_competitions]、[dssast_notes]、[dssast_news]；公告使用 [dssast_news category="dssast-announcement"]。</p></div>';
}
add_filter( 'default_category', function ( $default ) {
 if ( ! is_admin() || ! current_user_can( 'manage_options' ) || ! isset( $_GET['dssast_category'] ) || ! is_string( $_GET['dssast_category'] ) ) { return $default; }
 $term = get_term( absint( $_GET['dssast_category'] ), 'category' );
 return $term && ! is_wp_error( $term ) && in_array( $term->slug, array( 'dssast-news', 'dssast-announcement' ), true ) ? $term->term_id : $default;
} );

function dssast_content_list( $atts, $content, $tag ) {
 $atts = shortcode_atts( array( 'limit' => 10, 'category' => 'dssast-news' ), $atts, $tag );
 $args = array( 'post_type' => 'dssast_notes' === $tag ? 'dssast_note' : 'post', 'post_status' => 'publish', 'has_password' => false, 'posts_per_page' => max( 1, min( 50, absint( $atts['limit'] ) ) ), 'no_found_rows' => true );
 if ( 'post' === $args['post_type'] ) { $args['category_name'] = in_array( $atts['category'], array( 'dssast-news', 'dssast-announcement' ), true ) ? $atts['category'] : 'dssast-news'; }
 $query = new WP_Query( $args ); $html = '<div class="dssast-content-list">';
 foreach ( $query->posts as $post ) { $html .= '<article><h2><a href="' . esc_url( get_permalink( $post ) ) . '">' . esc_html( get_the_title( $post ) ) . '</a></h2><p>' . esc_html( wp_trim_words( wp_strip_all_tags( $post->post_excerpt ?: strip_shortcodes( $post->post_content ) ), 40 ) ) . '</p></article>'; }
 return $html . ( $query->posts ? '' : '<p>暂无内容。</p>' ) . '</div>';
}
add_shortcode( 'dssast_notes', 'dssast_content_list' );
add_shortcode( 'dssast_news', 'dssast_content_list' );

add_filter( 'the_content', function ( $content ) {
 if ( ! is_singular( 'dssast_competition' ) || ! in_the_loop() || ! is_main_query() || post_password_required() ) { return $content; }
 $html = '<section class="dssast-details"><h2>赛事信息</h2>';
 foreach ( dssast_fields() as $field => $label ) {
  $value = get_post_meta( get_the_ID(), 'dssast_' . $field, true );
  if ( ! is_string( $value ) || '' === $value ) { continue; }
  if ( 'registration_url' === $field ) { $html .= '<p><a rel="nofollow" href="' . esc_url( $value, array( 'http', 'https' ) ) . '">前往报名</a></p>'; }
  else { $html .= '<p>' . esc_html( $label . '：' . ( 'status' === $field ? ( dssast_statuses()[$value] ?? '未设置' ) : str_replace( 'T', ' ', $value ) ) ) . '</p>'; }
 }
 return $content . $html . '</section>';
} );
