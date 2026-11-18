# 前台集成

入口：`dssast-cms.php` 负责生命周期；`includes/competitions.php` 负责内容模型、后台校验及最小展示。无自定义表，使用 WordPress posts/postmeta/terms。

内容类型 `dssast_competition`；分类 `dssast_competition_category`；原生字段 `post_title`、`post_content`、`post_excerpt`；封面使用 `get_the_post_thumbnail()`。公开归档 `/competitions/`，单页链接通过 `get_permalink()` 获取，不硬编码。

## 新增模块（0.2.0）

`includes/notes.php` 负责笔记和审核；`includes/xiumi.php` 负责导入安全处理、回调和绑定管理。插件入口负责新闻分类初始化。

笔记类型为 `dssast_note`，作者是原生 post_author；审核状态使用 pending（待审核）、draft（退回/草稿）、publish（公开）。公开列表务必查询 publish，并排除密码保护内容；不要向访客返回 pending/draft。`dssast_review_reason` 是审核意见，仅在作者自己的投稿页面及管理后台显示，不应出现在公共 API。投稿 Shortcode 为 `[dssast_note_submit]`，不支持附件上传和动态区块。正文中的短代码按文字处理。

新闻和公告查询原生 post，通过 category_name 分别使用 `dssast-news` 或 `dssast-announcement`。秀米也导入原生 post 草稿，管理员审核时选择分类；不会自动发布。`dssast_xiumi_article_id` 为来源 ID；`dssast_xiumi_key`、`dssast_xiumi_hash` 为内部去重字段，前台无需使用。业务系统不要改写内部去重元数据。

笔记类型开启原生 REST 供管理员 Gutenberg 使用；投稿使用 admin-post.php 的专用动作，非管理员没有原生笔记写入权限。页面不得整页缓存。自定义动态区块可以后续调用相同 WP_Query 与字段协议，不应另建一套数据模型。

| Meta key | 格式 |
| --- | --- |
| dssast_status | planned/open/closed/completed/cancelled；空值表示未设置 |
| dssast_date | YYYY-MM-DD |
| dssast_registration_start | YYYY-MM-DDTHH:mm，站点本地时间 |
| dssast_registration_end | 同上 |
| dssast_registration_url | HTTP(S) URL |

字段缺失返回空字符串。所有自定义字段、函数、分类和 Shortcode 均使用 dssast 前缀，核心字段保持原名。

```php
$events = new WP_Query( array(
    'post_type' => 'dssast_competition',
    'post_status' => 'publish',
    'has_password' => false,
    'posts_per_page' => 10,
    'no_found_rows' => true,
) );
foreach ( $events->posts as $event ) {
    echo '<h2>' . esc_html( get_the_title( $event ) ) . '</h2>';
    echo '<p>' . esc_html( get_post_meta( $event->ID, 'dssast_date', true ) ) . '</p>';
}
```

分类筛选使用 `tax_query`；状态筛选使用 `meta_query`，key 为 `dssast_status`。展示字符串使用 `esc_html`，属性使用 `esc_attr`，URL 使用 `esc_url`；不要直接输出元数据。不要把用户请求直接拼入查询或 SQL。

`[dssast_competitions limit="5"]` 显示最新发布赛事，数量限制 1–50，排除密码保护内容，类名 `.dssast-competitions`。不提供主题 CSS。内容类型和分类开启原生 REST 供区块编辑器使用，业务 meta 不通过 REST 暴露或写入；未来扩展需统一权限和验证。可信服务器代码写入应先调用 `dssast_validate_fields()`，传入全部五个键，返回 false 时停止写入；直接调用底层 meta API 不构成本插件的受支持写入接口。
