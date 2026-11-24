# TVBox / 影视仓 / OK影视接入

## 配置地址

`https://tv.lihansong.top/tvbox.json`

在客户端的“配置地址 / 远程配置 / 配置源”入口添加上面的地址。不同分支版本的菜单名称可能略有差异。

## 接口说明

配置使用 MacCMS V10 原生视频 Provider API，不修改原生接口，也不额外依赖 Spider/JAR：

- API：`https://tv.lihansong.top/api.php/provide/vod/`
- 类型：`1`（CMS API）
- 搜索、快速搜索、分类筛选：已在配置中启用

可在浏览器检查接口是否开启：

- 视频列表：`https://tv.lihansong.top/api.php/provide/vod/?ac=list`
- 搜索：`https://tv.lihansong.top/api.php/provide/vod/?wd=关键词`
- 详情：`https://tv.lihansong.top/api.php/provide/vod/?ac=detail&ids=替换为实际影片ID`

## 后台检查

1. 在苹果 CMS 后台确认“视频 API”已开启。
2. 如未使用接口域名授权，保持视频 API 的收费/授权限制关闭；若启用授权，需按后台要求配置允许域名。
3. 影片需处于正常上架状态，并已填写可用播放线路和播放地址。
4. 若影片封面是相对路径，在视频 API 的图片地址设置中填写站点可访问的 HTTPS 根地址或图片 CDN 地址。

## 兼容范围

这是最基础的 TVBox 系 CMS API 配置，适用于支持标准 TVBox JSON 配置与 CMS API 的 TVBox、影视仓及 OK影视分支。各分支的扩展字段和播放器行为可能不同，仍需用实际 APK 导入并测试分类、列表、搜索、详情和多线路播放。

此配置只提供影视 CMS 数据源，不包含直播源、解析服务器或播放器内核。
