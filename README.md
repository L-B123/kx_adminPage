# DSSAST CMS

DSSAST CMS 是笃实书院学生科协官网的 WordPress 内容管理插件。本仓库包含插件源码、Windows 本地开发脚本和上线交接文档。[科协官网](https://dssast.cn/) · [正式站后台](https://dssast.cn/wp-admin/)

## 功能

| 后台入口 | 用途 |
| --- | --- |
| **内容管理** | 查看待审核笔记和已发布赛事的数量，搜索、分页查看笔记、新闻和公告。新闻与公告使用 WordPress 原生文章和分类。 |
| **赛事** | 维护赛事介绍、分类、状态、举办日期、报名时间和链接。赛事状态和文章发布状态可分别设置。 |
| **学习笔记** | 学生登录前台后投稿。管理员可以发布，或填写审核意见并退回，学生修改后可重新提交。 |
| **工具 → 秀米同步** | 配置并绑定秀米应用，把稿件同步为新闻草稿，供编辑审核发布。 |

## 安装到已有 WordPress

插件要求 **WordPress 6.6 或以上**、**PHP 7.4 或以上**。秀米图片同步需要 GD 或 Imagick。

1. 将仓库中的 `dssast-cms/` 目录复制到站点的 `wp-content/plugins/`，或者使用下面的命令从当前 Git 提交打包，再在 WordPress 后台的「插件 → 安装插件 → 上传插件」中上传 ZIP。

   ```sh
   git archive --format=zip --output=dssast-cms.zip HEAD dssast-cms/
   ```

2. 启用 **DSSAST CMS**，以管理员身份检查「内容管理」「赛事」「学习笔记」三个侧栏入口。
3. 在前台页面添加相应短代码。更新正式站前，先备份数据库和当前插件目录，在测试站验证后沿用 `dssast-cms` 插件目录更新。[上线与回滚说明](dssast-cms/FRONTEND-INTEGRATION.md#上线验收)

## 本地启动与测试

在仓库根目录打开 **Windows PowerShell**，运行以下命令。首次启动会下载 PHP、WordPress 和 SQLite 适配插件，创建本地站点及管理员、学生测试账号。

```powershell
powershell -NoProfile -ExecutionPolicy Bypass -File .\local-dev\setup-local.ps1
```

启动后访问本地后台。账号和页面地址在 Git 忽略的 `.local-test/LOCAL-LOGIN.txt` 中。修改插件源码后，重新运行安装脚本以同步本地站点中的插件副本。[本地后台](http://127.0.0.1:8097/wp-admin/) · [启动、停止与排错](dssast-cms/LOCAL-TEST-GUIDE.md)

已有隔离的本地 WordPress 环境时，启用插件、设置 `WP_ENVIRONMENT_TYPE=local`，并确认用户 ID `1` 为测试管理员。集成测试会写入测试数据，请用独立测试库运行。

```sh
php dssast-cms/tests/integration.php /path/to/wordpress/wp-load.php
```

使用 Windows 本地开发脚本安装后，可按文档中的 PowerShell 命令复跑测试。[验收记录](dssast-cms/TESTING.md)

## 前台接入

在支持正文渲染的 WordPress 页面中添加「短代码」区块。投稿页及其带查询参数的地址应绕过整页缓存、反向代理缓存和 CDN 缓存。

| 用途 | 短代码 | 正式站入口 |
| --- | --- | --- |
| 赛事列表 | `[dssast_competitions limit="10"]` | [赛事](https://dssast.cn/events/) |
| 公开笔记 | `[dssast_notes]` | [学习笔记](https://dssast.cn/notes/) |
| 笔记投稿 | `[dssast_note_submit]` | [笔记投稿](https://dssast.cn/note-submit/) |
| 新闻 | `[dssast_news]` | 由前台团队确定 |
| 公告 | `[dssast_news category="dssast-announcement"]` | 由前台团队确定 |

## 目录与文档

| 路径 | 内容 |
| --- | --- |
| [`dssast-cms/dssast-cms.php`](dssast-cms/dssast-cms.php) | 插件入口、版本与加载逻辑 |
| [`dssast-cms/includes/`](dssast-cms/includes/) | 后台管理、赛事、笔记和秀米业务逻辑 |
| [`dssast-cms/assets/`](dssast-cms/assets/) | 后台和前台样式 |
| [`dssast-cms/tests/`](dssast-cms/tests/) | WordPress 集成测试 |
| [`local-dev/`](local-dev/) | 本地站点安装和运行脚本 |

- [管理员手册](dssast-cms/ADMIN-GUIDE.md)介绍赛事发布、笔记审核、新闻和公告管理。
- [前台集成与上线交接](dssast-cms/FRONTEND-INTEGRATION.md)列出主题改动、赛事字段、负责人和上线检查项。
- [秀米同步配置](dssast-cms/XIUMI.md)说明应用绑定、回调地址和服务器端密钥配置。
