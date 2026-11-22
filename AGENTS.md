# DSSAST CMS 维护说明

每次修改本仓库或正式站前，先阅读本文件。本仓库负责 `dssast-cms/` 中的 WordPress 后台管理插件，正式站插件目录为 `/var/www/html/wp-content/plugins/dssast-cms`。

## 正式站入口

- 官网使用 WordPress，访问地址为 <https://dssast.cn/>。
- WordPress 后台登录地址为 <https://dssast.cn/wp-login.php>。
- 服务器 IP 为 `82.156.205.79`，`dssast.cn` 和 `www.dssast.cn` 均配置为指向该地址。
- HTTPS 证书绑定 `dssast.cn`。浏览器访问官网和后台时使用 `https://dssast.cn`，直接用 IP 访问会导致证书名称不匹配。

## 账号与权限

- SSH 日常维护使用各自的普通账号，便于追溯文件修改。当前维护人的普通账号是 `xuhanyuan`，可用 `ssh xuhanyuan@dssast.cn` 连接。
- SSH 账号 `dssast` 拥有 root 权限，仅在操作确实需要 root 时使用。
- WordPress 日常后台管理使用个人管理员账号 `xuhanyuan`。`dssast` 是共用的 WordPress 管理员账号，与同名 SSH 账号属于不同的登录系统。
- SSH 和 WordPress 的登录凭据通过站点负责人授权的私密渠道获取。仓库只记录账号名称、入口和用途。

## 修改范围

`xuhanyuan` 负责以下功能：

- 赛事信息管理，包括赛事介绍、进度、日期、报名时间和报名链接。
- 学习笔记信息管理，包括编辑、审核、退回和发布。
- 新闻公告管理，包括接入秀米自建网站 API，把通过秀米同步的公众号稿件导入新闻草稿，供管理员审核发布。
- 学习笔记投稿，包括登录后提交、查看审核反馈，以及修改退回稿件并重新提交。

每次修改时，Agent 只能修改与上述职责和本次任务直接相关的代码、配置、文档及测试。保留职责范围外的文件和改动。

后台功能在 CMS 插件或 WordPress 管理后台实现。笔记投稿使用插件提供的表单和短代码。官网前端主题 `/var/www/html/wp-content/themes/dssast` 由前端负责人维护。

## 阶段交付

每个阶段的改动完成并通过测试后，以 `fallingstar56` 身份提交本阶段相关文件，并推送到仓库的 GitHub 远端。将对应的插件版本部署到正式站，用个人普通 SSH 账号 `xuhanyuan` 操作并验收本阶段功能。汇报提交号、线上版本和验收结果。
