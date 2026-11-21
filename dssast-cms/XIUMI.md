# 秀米同步配置

实现依据：[秀米官方接口文档 2.0.10](https://ent.xiumius.cn/)，核对日期 2026-11-18。此文件不包含真实密钥。

在服务器环境变量（推荐）或 wp-config.php 常量中配置 `DSSAST_XIUMI_APP_ID` 和 `DSSAST_XIUMI_SECRET`。不要提交真实值到 Git。默认未配置时接口关闭。生产必须 HTTPS，并确保代理将 Authorization 转发给 PHP；仅 `WP_ENVIRONMENT_TYPE=local` 允许 HTTP。

管理员进入“工具 → 秀米同步”，取得三个 POST 接口地址，配置到秀米应用后台。点击绑定按钮，将当前管理员关联到秀米；收到 bind 通知后才能同步。仅允许绑定仍拥有 manage_options 的账号。应用注册和账号绑定由秀米应用管理员负责，公开 HTTPS 回调地址及 Authorization 转发由站点运维负责。

接口：`/wp-json/dssast/v1/xiumi/html`、`image`、`notify`。按官方协议校验 Authorization 的 secret 方案；这是服务器回调，不使用浏览器 Nonce。绑定按钮使用 WordPress 权限和 Nonce。partner_user_id 使用随机标识，不直接暴露 WP 用户编号。

图文先校验整批参数，逐篇写入原生新闻草稿，后续同步保留编辑手动调整的分类。按应用、绑定用户、article_id 去重。同内容跳过，变更只更新草稿；已发布/审核中/私密/回收站内容发生变更时返回 409，不覆盖。批次不是数据库事务，后续文章失败时前面的草稿会保留，可重新同步。正文经过 KSES，移除 HTML 注释和短代码执行语法；SVG、脚本及部分复杂秀米排版会被删除。封面仅使用已上传的本人媒体，不抓取外部 URL。

图片字段为 img-upload；限制 5 MiB、2000 万像素，仅 JPEG/PNG/GIF/WebP，解码重新编码后存入原生媒体库；需要 GD 或 Imagick。动画可能扁平化。按用户和原始内容 SHA256 去重。上传图片与 WordPress 普通媒体一样具有公开 URL；不要上传私密素材。无远程文件下载，不引入 SSRF 抓取入口。

通知支持 bind、unbind、secret_reset、err_html、err_image。secret_reset 用旧密钥认证后立即禁用该密钥；管理员需在服务器配置新密钥。错误通知按载荷指纹保留一天去重。后台最多保留 100 条时间和错误类型，不保存密钥、正文、原始错误消息或个人标识；暂不提供 trackid 关联视图。

接口失败使用 WordPress REST 错误体及非 2xx 状态，成功 code 为数字 0。并发写入使用数据库 option 锁；正常完成及异常释放。进程被强制杀死可能留下锁，管理员确认没有进行中的请求后可通过 WP-CLI 删除 `dssast_xiumi_lock_<用户ID>`、`dssast_image_lock_<用户ID>` 或 `dssast_note_lock_<用户ID>`。不要在请求运行中删除锁。

需在反向代理设置请求大小、速率和超时限制。插件提供内容大小限制及投稿频率检查，不替代服务器流量控制。
