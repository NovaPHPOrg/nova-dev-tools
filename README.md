# Nova Dev Tools 使用文档（面向 AI 开发协作）

这是一份“使用 `nova.phar` 管理业务项目”的文档，不涉及 `nova-dev-tools` 自身维护与二次开发。

## 1. 这个工具能做什么

`nova` 是一个 PHP CLI，主要用于：

- 初始化 Nova 项目（`init`）
- 管理插件（`plugin`）
- 管理 UI 组件（`ui`）
- 运行项目测试（`test`）
- 打包项目（`build`）
- 更新/修复子模块索引（`update` / `refresh`）
- 代码格式化（`fix`）

## 2. 使用前准备

- PHP 版本满足工具要求（建议 `8.3+`）
- 已安装 `git`
- 若初始化时选择 Composer，需安装 `composer`
- 准备好 `nova.phar`

检查环境：

```bash
php -v
git --version
composer --version
```

## 3. 快速开始（创建一个新项目）

```bash
mkdir -p ./assets
cd ./assets
cp ../nova-dev-tools/nova.phar ./nova.phar
php nova.phar init
```

交互项建议：

- 项目名称：仅使用小写字母、数字、下划线、中划线
- 使用 NovaUI：按需选择 `y/n`
- 使用 Composer：只有项目确实需要时再选 `y`

初始化完成后建议检查：

```bash
git --no-pager status
cat .gitmodules
```

## 4. 常用命令

```bash
php nova.phar help
php nova.phar version
php nova.phar init
php nova.phar build
php nova.phar test
php nova.phar test User
php nova.phar fix
php nova.phar update
php nova.phar refresh
php nova.phar plugin list
php nova.phar plugin add <plugin-name>
php nova.phar plugin remove <plugin-name>
php nova.phar ui init
php nova.phar ui list
php nova.phar ui add <component-name>
php nova.phar ui remove <component-name>
```

## 5. AI 协作推荐用法（重点）

你可以让 AI 直接执行下面这种目标导向任务：

### 场景 A：初始化项目并启用 UI

```text
在当前目录使用 nova.phar 初始化项目：
1) 项目名使用当前目录名
2) 启用 NovaUI
3) 启用 Composer
4) 初始化后检查 git 状态和 .gitmodules
5) 输出每一步结果与失败原因
```

### 场景 B：安装插件与 UI 组件

```text
使用 nova.phar 为当前项目安装：
- 插件：<plugin-a> <plugin-b>
- UI 组件：<component-a>
安装后执行 git --no-pager submodule status 并汇报结果。
```

### 场景 C：打包与测试

```text
在当前项目执行：
1) php nova.phar test
2) php nova.phar build
3) 输出打包产物位置和测试结果摘要
```

## 6. 常见问题与修复

### 6.1 composer.json 的 `name` 不合法

现象：

- `"./composer.json" does not match the expected JSON schema`

修复：

1. 打开 `composer.json`
2. 确认 `name` 是 `vendor/package` 格式（例如 `nova/assets`）

### 6.2 `src/vendor/composer` 无法创建

现象：

- `.../src/vendor/composer does not exist and could not be created`

修复：

1. 检查 `composer.json` 的 `config.vendor-dir`，应为 `src/vendor`（不要写成 `/src/vendor`）
2. 确认你在项目根目录执行命令
3. 必要时先创建目录：

```bash
mkdir -p src/vendor
composer install
```

### 6.3 `.gitmodules` 相关错误

现象：

- `fatal: please make sure that the .gitmodules file is in the working tree`

修复：

```bash
touch .gitmodules
git add .gitmodules
```

然后重新执行子模块相关命令。

### 6.4 `proc_open` / 工作目录错误

现象：

- `proc_open(): posix_spawn() failed`
- `工作目录不存在`

修复：

1. 确保命令在项目根目录执行
2. 检查 `git` 是否可用
3. 检查 PHP 是否禁用 `proc_open`

```bash
which git
php -i | grep disable_functions
```

## 7. 命令速查

- 新建项目：`php nova.phar init`
- 初始化 UI：`php nova.phar ui init`
- 列出插件：`php nova.phar plugin list`
- 安装插件：`php nova.phar plugin add <name>`
- 列出组件：`php nova.phar ui list`
- 安装组件：`php nova.phar ui add <name>`
- 运行测试：`php nova.phar test`
- 打包项目：`php nova.phar build`

---

如果你希望，我可以继续给这份文档补一版“从 0 到上线”的实战流程（含 Nginx、部署目录、更新策略），仍然只站在“使用者”视角，不写任何维护者内容。
