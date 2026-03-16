# DeepSeek AI Generator — WordPress Plugin

一款集成 DeepSeek API 的 WordPress 插件，提供可嵌入任意页面的 AI 文本生成界面，支持自定义提示词模板、输出样例、流式输出，以及生成记录的持久化保存与公开展示。

## 功能特性

- **DeepSeek API 集成** — 支持 `deepseek-chat` (V3) 与 `deepseek-reasoner` (R1) 两种模型
- **Shortcode & Gutenberg Block** — 两种嵌入方式，适配经典编辑器和块编辑器
- **SSE 流式输出** — 实时逐字展示生成结果，体验流畅
- **自定义提示词模板** — 后台管理 System Prompt、User Prompt 模板（支持 `{{user_input}}` 占位符）、输出样例
- **模板级参数覆盖** — 每个模板可单独设置 temperature、max_tokens
- **Markdown 渲染** — 生成结果自动渲染 Markdown（基于 marked.js）
- **复制 & 重新生成** — 一键复制结果或重新生成
- **Light / Dark 主题** — CSS 变量驱动，可通过 Shortcode 属性或 Block 设置切换
- **生成记录持久化** — 用户可将生成结果保存到数据库，其他访客也能看到
- **后台记录管理** — 管理员可筛选、搜索、隐藏、删除已保存的生成记录
- **RAG 参考检索** — 从参考文档库中按关键词/标签检索相关材料，拼接到提示词以增强行文逻辑性和意象联系
- **安全机制** — API Key AES-256 加密存储、nonce 验证、频率限制（per user/IP）、访客访问控制

## 环境要求

| 项目 | 最低版本 |
|---|---|
| PHP | 7.4 |
| WordPress | 6.0 |
| PHP 扩展 | `openssl`、`curl` |

## 安装

1. 将 `deepseek-generator` 文件夹上传至 `wp-content/plugins/`
2. 在 WordPress 后台 **插件** 页面启用 **DeepSeek AI Generator**
3. 进入 **DeepSeek AI → 设置**，填写 API Key 并保存

## 配置

### 全局设置（DeepSeek AI → 设置）

| 字段 | 说明 |
|---|---|
| API Key | DeepSeek 平台的 API Key，加密存储 |
| Base URL | API 基础地址，默认 `https://api.deepseek.com` |
| 模型 | `deepseek-chat` 或 `deepseek-reasoner` |
| Temperature | 采样温度，0–2 |
| Max Tokens | 最大生成 token 数 |
| Top P | 核采样参数，0–1 |
| 允许未登录用户 | 是否允许访客使用生成功能 |
| 频率限制 | 每用户/IP 每分钟最大请求次数，0 为不限制 |

### 提示词模板（DeepSeek AI → 提示词模板）

每个模板包含以下字段：

| 字段 | 说明 |
|---|---|
| 标题 | 模板名称，显示在前端界面标题栏 |
| 系统提示词 | System Prompt，定义 AI 角色和行为 |
| 用户提示词模板 | 使用 `{{user_input}}` 作为用户输入占位符 |
| 输出样例 | 展示给用户的示例输出文本 |
| 参考文本（风格仿写样本） | 用于让 AI 模仿风格、用词与句式的参考段落，留空则不启用 |
| 风格/修辞说明 | 语气与修辞要求，如正式书面、排比比喻、口语短句等，留空则不追加 |
| Temperature 覆盖 | 留空则使用全局值 |
| Max Tokens 覆盖 | 留空则使用全局值 |
| 允许保存 | 勾选后用户可将生成结果保存到数据库 |
| 展示历史 | 勾选后前端页面展示该模板的历史生成记录 |
| RAG 参考检索 | 勾选启用后，生成时自动从参考文档库检索相关材料拼入提示词 |
| 参考关键词 | 逗号分隔的主题词，用于在参考文档库中搜索。留空则仅使用用户输入 |
| 参考标签 | 逗号分隔的标签 slug，按标签筛选参考文档 |
| 最大参考条数 | 检索返回的最大参考文档数，留空默认 3 条 |

### 参考文档库（DeepSeek AI → 参考文档库）

参考文档库用于存放高质量范文、意象示例或主题背景说明，供 RAG 检索使用。每篇参考文档包含：

| 字段 | 说明 |
|---|---|
| 标题 | 简要描述主题或用途 |
| 正文 | 参考内容（支持 WordPress 编辑器） |
| 参考标签 | 自定义分类 `ds_ref_tag`，用于筛选 |

生成时，若模板启用了 RAG，插件将根据模板预设关键词/标签及用户输入，从参考文档库中检索若干篇最相关的文档，以结构化方式拼接到 System Prompt 中，引导 AI 学习其逻辑结构和意象衔接方式。

## 使用方式

### Shortcode

```
[deepseek_gen id="123" placeholder="请输入你的问题..." button_text="生成" theme="light"]
```

| 属性 | 默认值 | 说明 |
|---|---|---|
| `id` | （必填） | 提示词模板的 Post ID |
| `placeholder` | `请输入你的内容...` | 输入框占位文本 |
| `button_text` | `生成` | 按钮文字 |
| `theme` | `light` | `light` 或 `dark` |

### Gutenberg Block

在块编辑器中搜索 **DeepSeek**，插入 **DeepSeek AI Generator** 块，在右侧面板中选择模板并配置参数。

## 目录结构

```
deepseek-generator/
├── deepseek-generator.php        # 插件入口：常量、加载、钩子注册
├── uninstall.php                 # 卸载时清理所有数据
├── README.md
├── includes/
│   ├── class-api.php             # DeepSeek API 通信（普通/流式）、AJAX 处理
│   ├── class-admin.php           # 后台设置页、提示词模板 CPT & Meta Box
│   ├── class-retriever.php        # RAG 参考检索：关键词/标签检索参考文档库
│   ├── class-shortcode.php       # [deepseek_gen] Shortcode 注册与 HTML 渲染
│   ├── class-block.php           # Gutenberg Block 注册与 PHP render_callback
│   └── class-history.php         # 生成记录：自定义表、CRUD、AJAX、后台管理页
├── assets/
│   ├── js/
│   │   ├── frontend.js           # 前端交互（SSE 流式、保存、历史加载）
│   │   └── block-editor.js       # Gutenberg 编辑器侧配置面板
│   └── css/
│       ├── frontend.css          # 前端界面样式（含 light/dark 主题）
│       └── admin.css             # 后台管理样式
├── blocks/
│   └── deepseek-generator/
│       └── block.json            # Block 元数据定义
└── languages/                    # 国际化文件目录
```

## 数据存储

| 数据 | 存储位置 |
|---|---|
| 全局设置 | `wp_options` → `dsg_settings` |
| 提示词模板 | 自定义文章类型 `ds_prompt_template` + `wp_postmeta` |
| 参考文档库 | 自定义文章类型 `ds_reference_doc` + 自定义分类 `ds_ref_tag` |
| 生成记录 | 自定义表 `{prefix}_dsg_outputs` |
| 频率限制计数 | WordPress Transients |

### 生成记录表结构

| 列 | 类型 | 说明 |
|---|---|---|
| `id` | BIGINT PK | 自增主键 |
| `template_id` | BIGINT | 关联的模板 ID |
| `user_id` | BIGINT | 操作用户 ID，0 表示访客 |
| `user_input` | TEXT | 用户输入文本 |
| `output` | LONGTEXT | AI 生成的输出文本 |
| `status` | VARCHAR(20) | `published` 或 `hidden` |
| `created_at` | DATETIME | 创建时间 |

## 安全说明

- **API Key** 使用 AES-256-CBC 加密存储，密钥派生自 WordPress 的 `SECURE_AUTH_SALT`
- 所有 AJAX 请求均通过 `wp_nonce` 验证
- 频率限制基于用户 ID（已登录）或 IP 哈希（访客），通过 WordPress Transients 实现
- 保存到数据库的输出内容经过 `wp_kses_post()` 过滤，防止 XSS
- 访客访问可在全局设置中开关控制

## 开发说明

本插件使用纯原生 JavaScript（无 npm 构建依赖），Gutenberg Block 的编辑器脚本通过 `block.json` 的 `editorScript` 字段直接引用。如需二次开发：

- PHP 入口类均为静态方法，通过 WordPress 钩子系统连接
- 前端 CSS 使用 CSS 自定义属性（变量），修改主题只需覆盖 `.dsg-generator` 上的变量
- Markdown 渲染依赖 CDN 引入的 [marked.js](https://github.com/markedjs/marked)
- 自定义表的升级通过 `dsg_db_version` option 配合 `dbDelta()` 管理

## 许可证

GPL-2.0-or-later
