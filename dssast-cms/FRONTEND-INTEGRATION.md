# 前台集成与上线交接

## 页面和接口

在能渲染 `the_content()` 的 WordPress 页面模板中添加“短代码”区块，或由主题在页面主体中调用 `do_shortcode()`。正式站的 `wp-content/themes/dssast/` 通过 `page-events.php` 和 `page-notes.php` 中的 `get_template_part( 'index' )` 复用主题正文布局。两个页面通过短代码读取插件内容，笔记页面提供投稿链接。前端同学下次上传主题前，应把这两个模板和 `src/scss/_header.scss` 的窄屏导航改动同步到自己的本地主题仓库。

| 用途 | 短代码 | 主题直接查询时使用 |
| --- | --- | --- |
| 赛事一览 `/events/` | `[dssast_competitions limit="10"]` | `dssast_competition` 类型，`dssast_competition_category` 分类 |
| 笔记列表 `/notes/` | `[dssast_notes]` | `dssast_note` 类型 |
| 笔记投稿 `/note-submit/` | `[dssast_note_submit]` | 在页面内容中渲染，使用 WordPress 登录 |
| 新闻 | `[dssast_news]` | 原生 `post` 类型，`dssast-news` 分类 |
| 公告 | `[dssast_news category="dssast-announcement"]` | 原生 `post` 类型，`dssast-announcement` 分类 |

正式站通过主题导航打开 `/news/` 和 `/announcements/` 的分类归档。0.4.2 在原有插件分类不存在时复用 `news` 和 `announcements`，沿用分类 ID 和现有文章链接。公告短代码也接受 `category="announcements"`。

公开列表使用 `post_status=publish`、`has_password=false`。使用 `get_permalink()` 和 `get_post_type_archive_link()` 获取地址。赛事归档使用 `competitions`，笔记归档使用 `dssast-notes`，单篇笔记继续使用 `notes/<文章别名>`。`/notes/` 由官网栏目页面使用。短代码的 `limit` 限制为 1–50。新闻和公告的“添加”入口预选对应分类，编辑可在发布前调整。秀米新稿归为新闻草稿，后续同步保留编辑手动选定的分类。

投稿页面显示当前用户的投稿和退回意见。将该页面及其查询参数变体从 WordPress 整页缓存、反向代理和 CDN 缓存中排除。公开页面和接口只使用公开字段。审核意见 `dssast_review_reason` 仅供管理后台和稿件作者查看。秀米同步的来源和去重元数据由插件维护。

### 赛事字段

| Meta key | 格式 |
| --- | --- |
| `dssast_status` | `planned`、`open`、`closed`、`completed`、`cancelled` |
| `dssast_date` | `YYYY-MM-DD` |
| `dssast_registration_start` | `YYYY-MM-DDTHH:mm`，站点时区 |
| `dssast_registration_end` | `YYYY-MM-DDTHH:mm`，站点时区 |
| `dssast_registration_url` | HTTP(S) URL |

通过 `get_post_meta( $id, $key, true )` 读取。缺失字段为空。可信服务器代码写入前可调用 `dssast_validate_fields()`，传入五个键并检查返回值。赛事和笔记提供原生内容 REST 接口，赛事业务 meta 未注册为 REST 字段。赛事筛选可用 `tax_query` 和 `meta_query`，前台输出按文本、属性或 URL 分别转义。报名表由赛事负责人提供，插件维护跳转地址。

## 对接人员和确认事项

| 联系谁 | 需要确认和联验的内容 |
| --- | --- |
| 官网 WordPress 管理员与运维 | 测试站与正式站入口、插件安装或更新权限、WordPress/PHP 版本、站点时区、备份和回滚方式。测试站验证插件菜单、媒体和固定链接。 |
| 官网前台／`dssast` 主题负责人 | 将正式站两个栏目模板和窄屏导航改动同步到本地主题仓库。以后更新主题时复测四个栏目和投稿入口。 |
| 赛事负责人和报名表维护人 | 赛事分类、测试赛事的日期和开放时段，正式报名链接与可访问权限，谁负责及时更新赛事状态。 |
| 学习笔记审核负责人和一名学生测试员 | 审核人、退回意见和发布约定。用学生账号投稿，管理员退回，学生重提，管理员发布，再从访客视角检查可见性。 |
| 宣传／新闻负责人和秀米应用管理员 | 新闻与公告归类、已有文章的栏目映射、发布权限、秀米应用及绑定账号。运维通过既有安全渠道配置 App ID 和密钥，联验 HTTPS 的 HTML、图片、通知三个回调、草稿归类以及重复同步。 |
| GitHub 仓库管理员 | 确认 `fallingstar56` 对 `L-B123/kx_adminPage` 的写入权限，以及 `main` 是否接受直接推送或要求走 PR。 |

## 上线验收

1. 在测试站安装发布包，按[本地验收](TESTING.md)走完核心流程。正式站发布前备份数据库和当前插件目录。
2. 在[正式站 WordPress 后台](https://dssast.cn/wp-admin/index.php)上传发布包。如插件已存在，确认替换的是 `dssast-cms`。
3. 用现有内容检查三个侧栏项、赛事单页、新闻和公告列表。再用普通账号验证投稿和缓存隔离，用管理员账号验证退回与发布。
4. 秀米启用时，使用测试稿走完整个同步链路并清理测试数据。发生异常时，用备份的插件包恢复上一版本，检查菜单与页面访问。
