# Nova Dev Tools 命令使用手册（给 AI 直接执行）

这份文档只回答一件事：`nova.phar` 每个命令怎么用。

## 1. 先决条件

- 在项目根目录执行命令（`nova.phar` 所在目录）
- 已安装 `php`、`git`
- 使用 Composer 功能时需要 `composer`

```bash
php -v
git --version
composer --version
```

## 2. 命令总览

```bash
php nova.phar help
php nova.phar version
php nova.phar init
php nova.phar build
php nova.phar test [TestName...]
php nova.phar fix
php nova.phar update
php nova.phar refresh
php nova.phar plugin list
php nova.phar plugin add <plugin-name> [plugin-name...]
php nova.phar plugin remove <plugin-name> [plugin-name...]
php nova.phar ui init
php nova.phar ui list
php nova.phar ui add <component-name> [component-name...]
php nova.phar ui remove <component-name> [component-name...]
```

## 3. 初始化项目

用途：创建 Nova 项目骨架并初始化 Git。

```bash
php nova.phar init
```

交互输入：

- 项目名称：仅允许小写字母/数字/下划线/中划线
- 使用 NovaUI：`y` 会额外执行 `ui init`
- 使用 Composer：`y` 会生成 `composer.json` 并执行 `composer install`

初始化后建议马上检查：

```bash
git --no-pager status
git --no-pager submodule status
cat .gitmodules
```

## 4. 模块管理（重点）

这里的“模块”分两类：

- 插件模块：`plugin`
- UI 组件模块：`ui`

### 4.1 插件模块检索

```bash
php nova.phar plugin list
```

说明：输出的是可安装插件名（例如 `task`、`auth`）。

### 4.2 插件模块安装

单个安装：

```bash
php nova.phar plugin add task
```

批量安装：

```bash
php nova.phar plugin add task auth cache
```

安装后核对：

```bash
git --no-pager submodule status
ls src/nova/plugin
```

### 4.3 插件模块卸载

```bash
php nova.phar plugin remove task
```

批量卸载：

```bash
php nova.phar plugin remove task auth
```

### 4.4 UI 框架初始化与组件检索

初始化 UI（首次仅执行一次）：

```bash
php nova.phar ui init
```

检索可安装 UI 组件：

```bash
php nova.phar ui list
```

### 4.5 UI 组件安装/卸载

安装单个组件：

```bash
php nova.phar ui add table
```

批量安装：

```bash
php nova.phar ui add table chart form
```

卸载组件：

```bash
php nova.phar ui remove table
```

## 5. 测试：用例怎么写，怎么跑（重点）

`test` 命令会扫描 `tests/*Test.php`，并执行每个测试类的 `test()` 方法。

### 5.1 目录和命名规则

- 测试文件必须放在 `tests/`
- 文件名必须是 `*Test.php`（例如 `UserTest.php`）
- 类命名空间必须是 `tests`
- 类名必须与文件名一致（不带 `.php`）
- 推荐继承 `nova\\commands\\test\\TestCase`

### 5.2 最小测试用例模板

```php
<?php

namespace tests;

use nova\commands\test\TestCase;

class UserTest extends TestCase
{
	public function test()
	{
		$this->checkString("ok", "ok");
	}
}
```

### 5.3 运行测试

运行全部测试：

```bash
php nova.phar test
```

运行指定测试（不带 `Test.php` 后缀）：

```bash
php nova.phar test User
```

运行多个指定测试：

```bash
php nova.phar test User Order Payment
```

### 5.4 可用断言辅助方法（TestCase）

- `checkObj($obj1, $obj2)`
- `checkArray($arr1, $arr2)`
- `checkString($str1, $str2)`
- `checkInt($int1, $int2)`
- `checkFloat($float1, $float2)`
- `checkBool($bool1, $bool2)`
- `checkNull($value)`

## 6. 构建、格式化、更新

打包项目：

```bash
php nova.phar build
```

格式化代码并提交：

```bash
php nova.phar fix
```

更新所有子模块：

```bash
php nova.phar update
```

重建 `.gitmodules` 并刷新 git 索引：

```bash
php nova.phar refresh
```

## 7. 常见错误速修

### 7.1 Composer 包名格式错误

错误特征：

- `does not match the expected JSON schema`

处理：确认 `composer.json` 的 `name` 是 `vendor/package` 格式，例如 `nova/assets`。

### 7.2 `src/vendor/composer` 无法创建

处理步骤：

```bash
mkdir -p src/vendor
composer install
```

并检查 `composer.json` 中 `config.vendor-dir` 是 `src/vendor`（不要以 `/` 开头）。

### 7.3 `.gitmodules` 缺失

```bash
touch .gitmodules
git add .gitmodules
```

然后重试对应的 `plugin/ui` 命令。

### 7.4 `proc_open` 或工作目录错误

```bash
which git
php -i | grep disable_functions
```

确认：在项目根目录执行命令，且 PHP 未禁用 `proc_open`。

## 8. 给 AI 的直接任务模板

### 模板 1：检索并安装模块

```text
在当前项目执行以下流程：
1) php nova.phar plugin list
2) 从列表中安装插件：task auth
3) php nova.phar ui list
4) 安装 UI 组件：table
5) 输出 git --no-pager submodule status 的结果
```

### 模板 2：编写并运行测试

```text
在 tests 目录创建 UserTest.php：
- namespace tests
- class UserTest extends nova\commands\test\TestCase
- 在 test() 中至少调用一次 checkString
创建后执行：
1) php nova.phar test User
2) php nova.phar test
并输出执行结果。
```

