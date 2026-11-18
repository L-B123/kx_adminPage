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
 foreach ( dssast_management_views() as $key => $view ) {
  add_submenu_page( 'dssast-management', $view[0], $view[0], 'manage_options', 'dssast-view-' . $key, function () use ( $key ) { dssast_management_list( $key ); } );
 }
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
 $links['笔记管理'] = 'admin.php?page=dssast-view-notes';
 $links['待审核笔记'] = 'admin.php?page=dssast-view-pending';
 $links['新闻管理'] = 'admin.php?page=dssast-view-news';
 $links['公告管理'] = 'admin.php?page=dssast-view-announcements';
 foreach ( array( '赛事' => array( '赛事列表', '添加赛事' ), '学习笔记' => array( '笔记管理', '待审核笔记', '添加笔记' ), '新闻' => array( '新闻管理', '添加新闻' ), '公告' => array( '公告管理', '添加公告' ), '内容同步' => array( '秀米同步设置' ) ) as $heading => $items ) {
  echo '<h2>' . esc_html( $heading ) . '</h2><p>';
  foreach ( $items as $label ) { if ( isset( $links[$label] ) ) { echo '<a class="button" style="margin:0 12px 8px 0" href="' . esc_url( admin_url( $links[$label] ) ) . '">' . esc_html( $label ) . '</a>'; } }
  echo '</p>';
 }
 echo '<p>秀米稿件同步为草稿，审核发布后展示。前台可使用 [dssast_competitions]、[dssast_notes]、[dssast_news]；公告使用 [dssast_news category="dssast-announcement"]。</p></div>';
}
function dssast_management_views() {
 return array( 'notes' => array( '全部笔记', 'dssast_note', '' ), 'pending' => array( '待审核笔记', 'dssast_note', '' ), 'news' => array( '新闻管理', 'post', 'dssast-news' ), 'announcements' => array( '公告管理', 'post', 'dssast-announcement' ) );
}
function dssast_management_list( $key ) {
 if ( ! current_user_can( 'manage_options' ) ) { return; }
 $views = dssast_management_views();
 if ( ! isset( $views[$key] ) ) { return; }
 $view = $views[$key];
 $page = isset( $_GET['paged'] ) && is_string( $_GET['paged'] ) ? max( 1, absint( $_GET['paged'] ) ) : 1;
 $args = array( 'post_type' => $view[1], 'post_status' => 'pending' === $key ? 'pending' : array( 'publish', 'pending', 'draft', 'future', 'private' ), 'posts_per_page' => 20, 'paged' => $page );
 if ( $view[2] ) { $args['category_name'] = $view[2]; }
 $query = new WP_Query( $args );
 echo '<div class="wrap"><h1>' . esc_html( $view[0] ) . '</h1><p>' . esc_html( 'pending' === $key ? '仅显示等待审核的学生投稿；退回的草稿和已发布笔记不在此列。' : '显示本栏目全部草稿、待审核、已发布、定时和私密内容，不包含回收站。' ) . '</p>';
 echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=dssast-management' ) ) . '">返回内容管理</a></p>';
 if ( 'dssast_note' === $view[1] ) { echo '<p><a href="' . esc_url( admin_url( 'admin.php?page=dssast-view-notes' ) ) . '">全部笔记</a> | <a href="' . esc_url( admin_url( 'admin.php?page=dssast-view-pending' ) ) . '">待审核笔记</a></p>'; }
 echo '<table class="widefat striped"><thead><tr><th>标题</th><th>作者</th><th>状态</th><th>日期</th><th>操作</th></tr></thead><tbody>';
 foreach ( $query->posts as $item ) {
  $status = get_post_status_object( $item->post_status );
  echo '<tr><td>' . esc_html( $item->post_title ?: '（无标题）' ) . '</td><td>' . esc_html( get_the_author_meta( 'display_name', $item->post_author ) ) . '</td><td>' . esc_html( $status ? $status->label : $item->post_status ) . '</td><td>' . esc_html( $item->post_date ) . '</td><td>';
  if ( current_user_can( 'edit_post', $item->ID ) ) { echo '<a href="' . esc_url( get_edit_post_link( $item->ID, 'raw' ) ) . '">' . esc_html( 'pending' === $key ? '审核' : '编辑' ) . '</a>'; }
  echo '</td></tr>';
 }
 if ( ! $query->posts ) { echo '<tr><td colspan="5">' . esc_html( 'pending' === $key ? '暂无待审核笔记。' : '本栏目暂无内容。' ) . '</td></tr>'; }
 echo '</tbody></table><p>' . esc_html( '共 ' . $query->found_posts . ' 条' ) . '</p>';
 echo wp_kses_post( paginate_links( array( 'base' => add_query_arg( array( 'page' => 'dssast-view-' . $key, 'paged' => '%#%' ), admin_url( 'admin.php' ) ), 'format' => '', 'current' => $page, 'total' => $query->max_num_pages ) ) );
 echo '</div>';
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
