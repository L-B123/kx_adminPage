<?php
// CLI-only initialization of a dedicated, local WordPress installation.
if (PHP_SAPI !== 'cli' || empty($argv[1])) { exit(1); }
$runtime = realpath($argv[1]);
if (!$runtime || basename($runtime) !== '.local-test') { throw new RuntimeException('Invalid runtime directory'); }
$site = $runtime . '/wordpress';
$settings = json_decode(file_get_contents($runtime . '/site.json'), true, 512, JSON_THROW_ON_ERROR);
$url = 'http://127.0.0.1:' . (int) $settings['port'];
if ($settings['url'] !== $url) { throw new RuntimeException('Invalid local URL'); }
$config = $site . '/wp-config.php';
if (!file_exists($config)) {
    $values = array(
        'DB_NAME' => 'dssast_local', 'DB_USER' => 'unused', 'DB_PASSWORD' => 'unused',
        'DB_HOST' => 'localhost', 'DB_CHARSET' => 'utf8', 'DB_COLLATE' => '',
        'DB_ENGINE' => 'sqlite', 'DB_DIR' => $runtime . '/database/',
        'DB_FILE' => '.ht.sqlite', 'DB_PATH' => $runtime . '/database/.ht.sqlite',
        'WP_HOME' => $url, 'WP_SITEURL' => $url, 'WP_ENVIRONMENT_TYPE' => 'local',
        'WP_DEBUG' => false, 'DISALLOW_FILE_EDIT' => true,
        'WP_AUTO_UPDATE_CORE' => false, 'AUTOMATIC_UPDATER_DISABLED' => true,
    );
    foreach (array('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT') as $key) {
        $values[$key] = bin2hex(random_bytes(48));
    }
    $text = "<?php\n// Dedicated local installation; do not use for production.\n";
    foreach ($values as $key => $value) { $text .= 'define(' . var_export($key,true) . ', ' . var_export($value,true) . ");\n"; }
    $text .= "\$table_prefix = 'wp_';\nif (!defined('ABSPATH')) { define('ABSPATH', __DIR__ . '/'); }\nrequire_once ABSPATH . 'wp-settings.php';\n";
    if (file_put_contents($config, $text, LOCK_EX) === false) { throw new RuntimeException('Cannot write wp-config.php'); }
}

$credentialsFile = $runtime . '/credentials.json';
if (!file_exists($credentialsFile)) {
    if (file_exists($runtime . '/database/.ht.sqlite')) {
        throw new RuntimeException('Database already exists but credentials.json is missing. Restore the credentials file; setup will not reset existing passwords.');
    }
    $credentials = array(
        'admin' => array('username'=>'dssast_local_admin', 'password'=>bin2hex(random_bytes(16))),
        'student' => array('username'=>'dssast_local_student', 'password'=>bin2hex(random_bytes(16))),
    );
    if (file_put_contents($credentialsFile, json_encode($credentials, JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), LOCK_EX) === false) {
        throw new RuntimeException('Cannot save local credentials');
    }
    @chmod($credentialsFile, 0600);
}
$credentials = json_decode(file_get_contents($credentialsFile), true, 512, JSON_THROW_ON_ERROR);
define('WP_INSTALLING', true);
$_SERVER['HTTP_HOST'] = '127.0.0.1:' . (int) $settings['port'];
$_SERVER['SERVER_NAME'] = '127.0.0.1';
$_SERVER['SERVER_PORT'] = (string) $settings['port'];
$_SERVER['REQUEST_URI'] = '/';
require $site . '/wp-load.php';
if (version_compare($wp_version, '6.6', '<')) { throw new RuntimeException('WordPress 6.6+ required'); }
require_once ABSPATH . 'wp-admin/includes/upgrade.php';
require_once ABSPATH . 'wp-admin/includes/plugin.php';
if (!is_blog_installed()) {
    $result = wp_install('笃实书院科协 · 本地网站', $credentials['admin']['username'], 'admin@example.test', false, '', $credentials['admin']['password']);
    if (is_wp_error($result)) { throw new RuntimeException($result->get_error_message()); }
}
$admin = get_user_by('login', $credentials['admin']['username']);
if (!$admin || !user_can($admin, 'manage_options')) { throw new RuntimeException('Expected local administrator not found'); }
wp_set_current_user($admin->ID);
if (!get_user_by('login', $credentials['student']['username'])) {
    $id = wp_insert_user(array('user_login'=>$credentials['student']['username'], 'user_pass'=>$credentials['student']['password'], 'role'=>'subscriber', 'user_email'=>'student@example.test'));
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
}
$error = activate_plugin('dssast-cms/dssast-cms.php');
if (is_wp_error($error)) { throw new RuntimeException($error->get_error_message()); }
// Plugin activation occurs after init during the first CLI initialization.
dssast_register_competitions();
dssast_register_notes();
dssast_ensure_categories();
update_option('timezone_string', 'Asia/Shanghai');
update_option('blog_public', 0);
update_option('users_can_register', 0);
update_option('permalink_structure', '/%postname%/');
$theme = wp_get_theme();
if (!$theme->exists()) {
    foreach (wp_get_themes() as $candidate) {
        if (!$candidate->errors() && !$candidate->parent()) { switch_theme($candidate->get_stylesheet()); break; }
    }
}
$pages = array(
    'competitions-list' => array('赛事一览', '[dssast_competitions limit="10"]'),
    'note-submit' => array('笔记投稿', '[dssast_note_submit]'),
    'notes-list' => array('学习笔记', '[dssast_notes]'),
    'news-list' => array('新闻', '[dssast_news]'),
    'announcements-list' => array('公告', '[dssast_news category="dssast-announcement"]'),
);
$links = array();
foreach ($pages as $slug => $page) {
    $existing = get_page_by_path($slug);
    $id = $existing ? $existing->ID : wp_insert_post(wp_slash(array('post_type'=>'page', 'post_status'=>'publish', 'post_name'=>$slug, 'post_title'=>$page[0], 'post_content'=>'<!-- wp:shortcode -->' . $page[1] . '<!-- /wp:shortcode -->', 'post_author'=>$admin->ID)), true);
    if (is_wp_error($id)) { throw new RuntimeException($id->get_error_message()); }
    $links[$page[0]] = get_permalink($id);
}
if (!get_page_by_path('local-home')) {
    $content = '<!-- wp:heading --><h2 class="wp-block-heading">笃实书院科协</h2><!-- /wp:heading -->';
    $content .= '<!-- wp:paragraph --><p>本地内容管理网站。管理员可管理赛事、审核笔记、发布新闻公告。</p><!-- /wp:paragraph -->';
    foreach ($links as $label => $link) {
        $content .= '<!-- wp:paragraph --><p><a href="' . esc_url($link) . '">' . esc_html($label) . '</a></p><!-- /wp:paragraph -->';
    }
    $content .= '<!-- wp:paragraph --><p><a href="' . esc_url(admin_url('admin.php?page=dssast-management')) . '">管理后台</a></p><!-- /wp:paragraph -->';
    $home = wp_insert_post(wp_slash(array('post_type'=>'page','post_status'=>'publish','post_name'=>'local-home','post_title'=>'科协首页','post_content'=>$content,'post_author'=>$admin->ID)), true);
    if (is_wp_error($home)) { throw new RuntimeException($home->get_error_message()); }
    update_option('show_on_front', 'page');
    update_option('page_on_front', $home);
}
flush_rewrite_rules(false);
$login = "Local DSSAST site\r\nURL: $url/\r\nAdmin: $url/wp-admin/\r\n\r\n";
foreach ($credentials as $role => $account) { $login .= "$role\r\nUsername: {$account['username']}\r\nPassword: {$account['password']}\r\n\r\n"; }
foreach ($links as $label => $link) { $login .= "$label : $link\r\n"; }
$login .= "\r\nPasswords are created only once. Changing them in WordPress does not update this file.\r\n";
file_put_contents($runtime . '/LOCAL-LOGIN.txt', $login, LOCK_EX);
@chmod($runtime . '/LOCAL-LOGIN.txt', 0600);
echo "WordPress $wp_version initialized. Credentials: .local-test/LOCAL-LOGIN.txt\n";
