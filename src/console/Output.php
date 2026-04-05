<?php

namespace nova\console;

/**
 * 现代化命令行输出工具类 —— 无任何外部依赖。
 * 所有方法均为静态方法，可在代码库任意位置直接调用。
 */
class Output
{
    private static ?ConsoleColor $color = null;

    // ─── 颜色辅助 ─────────────────────────────────────────────────────────

    private static function color(): ConsoleColor
    {
        if (self::$color === null) {
            self::$color = new ConsoleColor();
        }
        return self::$color;
    }

    /**
     * 将指定样式应用到文本，若终端不支持颜色则原样返回。
     */
    public static function apply($style, string $text): string
    {
        try {
            return self::color()->apply($style, $text);
        } catch (\Exception $e) {
            return $text;
        }
    }

    // ─── 原始输出 ────────────────────────────────────────────────────────

    /** 输出一行文本（末尾自动换行）。 */
    public static function writeln(string $text = ''): void
    {
        echo $text . "\n";
    }

    /** 输出文本（不换行）。 */
    public static function write(string $text): void
    {
        echo $text;
    }

    // ─── 横幅 ───────────────────────────────────────────────────────────

    /**
     * 输出带圆角边框的品牌横幅。
     *
     * @param string $title   工具名称
     * @param string $version 版本号
     */
    public static function banner(string $title, string $version): void
    {
        $l1 = "  ⚡  $title   $version  ";
        $l2 = "  Modern PHP Development CLI  ";
        $width = max(mb_strlen($l1), mb_strlen($l2)) + 2;
        $l1p = str_pad($l1, $width, ' ', STR_PAD_BOTH);
        $l2p = str_pad($l2, $width, ' ', STR_PAD_BOTH);
        $border = str_repeat('─', $width);

        self::writeln(self::apply('light_cyan', '╭' . $border . '╮'));
        self::writeln(
            self::apply('light_cyan', '│') .
            self::apply(['bold', 'white'], $l1p) .
            self::apply('light_cyan', '│')
        );
        self::writeln(
            self::apply('light_cyan', '│') .
            self::apply('light_gray', $l2p) .
            self::apply('light_cyan', '│')
        );
        self::writeln(self::apply('light_cyan', '╰' . $border . '╯'));
    }

    // ─── 区块标题 ───────────────────────────────────────────────────────

    /**
     * 输出加粗黄色的区块标题，标题下方附带分隔线。
     *
     * @param string $title 区块名称
     */
    public static function section(string $title): void
    {
        self::writeln();
        self::writeln(self::apply(['bold', 'light_yellow'], " $title "));
        self::writeln(self::apply('light_gray', ' ' . str_repeat('─', 48)));
    }

    // ─── 分隔线 ──────────────────────────────────────────────────────────

    /**
     * 输出一条暗色水平分隔线。
     *
     * @param int $width 分隔线字符数，默认 48
     */
    public static function divider(int $width = 48): void
    {
        self::writeln(self::apply('light_gray', ' ' . str_repeat('─', $width)));
    }

    // ─── 用法说明 ───────────────────────────────────────────────────────

    /**
     * 输出命令用法说明行。
     *
     * @param string $text 用法文本，例如 "nova <command> [options]"
     */
    public static function usage(string $text): void
    {
        self::writeln(
            '  ' .
            self::apply('light_gray', 'Usage:') .
            ' ' .
            self::apply(['bold', 'white'], $text)
        );
    }

    // ─── 命令 / 选项行 ──────────────────────────────────────────────────

    /**
     * 输出顶级命令行（绿色命令名 + 白色描述）。
     *
     * @param string $cmd  命令名称
     * @param string $desc 命令描述
     * @param int    $pad  命令名称列宽，默认 16
     */
    public static function commandRow(string $cmd, string $desc, int $pad = 16): void
    {
        echo '  ' .
            self::apply('light_green', str_pad($cmd, $pad)) .
            self::apply('white', $desc) .
            "\n";
    }

    /**
     * 输出缩进的子命令行（青色命令名 + 灰色描述）。
     *
     * @param string $cmd  子命令名称
     * @param string $desc 子命令描述
     * @param int    $pad  命令名称列宽，默认 22
     */
    public static function subCommandRow(string $cmd, string $desc, int $pad = 22): void
    {
        echo '    ' .
            self::apply('light_cyan', str_pad($cmd, $pad)) .
            self::apply('light_gray', $desc) .
            "\n";
    }

    // ─── 内联标签徽章 ──────────────────────────────────────────────────

    /**
     * 返回一个带背景色的内联标签字符串（不直接输出）。
     *
     * @param string $label 标签文字
     * @param string $style 背景样式，默认蓝色
     */
    public static function badge(string $label, string $style = 'bg_blue'): string
    {
        return self::apply($style, " $label ");
    }

    // ─── 状态消息 ──────────────────────────────────────────────────────

    /** 输出蓝色信息提示（前缀 ℹ）。 */
    public static function info(string $msg, bool $newLine = true): void
    {
        echo self::apply('light_blue',  ' ℹ ') .
             self::apply('white', $msg) .
             ($newLine ? "\n" : '');
    }

    /** 输出绿色成功消息（前缀 ✓）。 */
    public static function success(string $msg, bool $newLine = true): void
    {
        echo self::apply('light_green', ' ✓ ') .
             self::apply('white', $msg) .
             ($newLine ? "\n" : '');
    }

    /** 输出黄色警告消息（前缀 ⚠）。 */
    public static function warn(string $msg, bool $newLine = true): void
    {
        echo self::apply('light_yellow', ' ⚠ ') .
             self::apply('white', $msg) .
             ($newLine ? "\n" : '');
    }

    /** 输出红色错误消息（前缀 ✗）。 */
    public static function error(string $msg, bool $newLine = true): void
    {
        echo self::apply('light_red', ' ✗ ') .
             self::apply('white', $msg) .
             ($newLine ? "\n" : '');
    }

    /** 输出紫色步骤消息（前缀 ▶），常用于命令执行阶段提示。 */
    public static function step(string $msg, bool $newLine = true): void
    {
        echo self::apply('light_gray', ' ▶ ') .
             self::apply(['dark', 'light_gray'], $msg) .
             ($newLine ? "\n" : '');
    }

    /** 输出命令执行行，提示符与命令正文使用不同颜色。 */
    public static function commandLine(string $symbol, string $command, bool $subtle = false): void
    {
        $symbolStyle = $subtle ? 'dark_gray' : 'light_cyan';
        $commandStyle = $subtle ? ['dark', 'light_gray'] : 'light_gray';

        echo ' ' .
             self::apply($symbolStyle, $symbol) .
             ' ' .
             self::apply($commandStyle, $command) .
             "\n";
    }

    /** 输出命令标准输出内容（低亮度中性色）。 */
    public static function commandStdout(string $msg, bool $newLine = true): void
    {
        echo self::apply(['dark', 'light_gray'], "   $msg") .
             ($newLine ? "\n" : '');
    }

    /** 输出命令错误输出内容（红色警示）。 */
    public static function commandStderr(string $msg, bool $newLine = true): void
    {
        echo self::apply('light_red', "   $msg") .
             ($newLine ? "\n" : '');
    }

    /** 输出暗灰色辅助信息，常用于命令输出的原始内容。 */
    public static function muted(string $msg, bool $newLine = true): void
    {
        echo self::apply(['dark', 'light_gray'], "   $msg") .
             ($newLine ? "\n" : '');
    }

    // ─── 文本框 ────────────────────────────────────────────────────────

    /**
     * 用方角边框包裹多行文本并输出。
     *
     * @param string $msg   文本内容，支持 \n 换行
     * @param string $style 边框颜色样式，默认亮青色
     */
    public static function box(string $msg, string $style = 'light_cyan'): void
    {
        $lines  = explode("\n", $msg);
        $maxLen = max(array_map('mb_strlen', $lines));
        $inner  = $maxLen + 4;

        self::writeln(self::apply($style, '┌' . str_repeat('─', $inner) . '┐'));
        foreach ($lines as $line) {
            echo self::apply($style, '│') .
                '  ' . str_pad($line, $maxLen) . '  ' .
                self::apply($style, '│') . "\n";
        }
        self::writeln(self::apply($style, '└' . str_repeat('─', $inner) . '┘'));
    }

    // ─── 交互式提示 ────────────────────────────────────────────────────

    /**
     * 显示一个带默认值的交互式输入提示，并返回用户输入。
     * 若用户输入 "exit" 则打印取消提示并终止程序。
     *
     * @param string $msg     提示文字
     * @param string $default 默认值（直接回车时使用）
     * @return string 用户输入或默认值
     */
    public static function prompt(string $msg, string $default = ''): string
    {
        $defaultText = $default !== ''
            ? self::apply('light_gray', " ($default)")
            : '';

        echo self::apply('light_cyan', ' ? ') .
             self::apply('white', $msg) .
             $defaultText .
             self::apply('light_gray', ' › ');

        $handle   = fopen('php://stdin', 'r');
        $line     = fgets($handle);
        fclose($handle);

        $received = trim($line);

        if ($received === 'exit') {
            self::warn("Operation cancelled.");
            exit(0);
        }

        return $received === '' ? $default : $received;
    }

    // ─── 工作目录指示 ──────────────────────────────────────────────────

    /**
     * 输出当前工作目录的高亮提示行。
     *
     * @param string $dir 工作目录路径
     */
    public static function workingDir(string $dir): void
    {
        self::writeln(
            self::apply('light_gray', ' 📁 ') .
            self::apply('light_gray', 'cwd › ') .
            self::apply('light_cyan', $dir)
        );
    }

    // ─── 滚动日志（docker compose 风格）────────────────────────────────

    /** @var string[] 当前窗口中缓存的行 */
    private static array $liveBuffer   = [];

    /** @var int 滚动窗口最大行数 */
    private static int   $liveMaxLines = 5;

    /** @var int 上一次渲染实际输出的行数（用于回退光标） */
    private static int   $liveRendered = 0;

    /**
     * 开始一个滚动日志区域，重置内部缓冲状态。
     *
     * @param int $maxLines 同时可见的最大行数，默认 5
     */
    public static function liveLogBegin(int $maxLines = 5): void
    {
        self::$liveBuffer   = [];
        self::$liveMaxLines = $maxLines;
        self::$liveRendered = 0;
    }

    /**
     * 向滚动日志追加一行输出。
     * 始终保留最新 $maxLines 行，超出部分自动向上清除。
     *
     * @param string $line 要追加的文本行（可包含 ANSI 转义码，内部会自动剥离再显示）
     */
    public static function liveLog(string $line): void
    {
        // 剥离 ANSI 转义码，防止光标位置计算出错
        $clean = preg_replace('/\033\[[0-9;]*[A-Za-z]/', '', $line);
        $clean = rtrim($clean);

        // 超长行截断，保证不折行破坏布局
        $maxWidth = self::terminalWidth() - 6;
        if (mb_strlen($clean) > $maxWidth) {
            $clean = mb_substr($clean, 0, $maxWidth - 1) . '…';
        }

        self::$liveBuffer[] = $clean;
        if (count(self::$liveBuffer) > self::$liveMaxLines) {
            array_shift(self::$liveBuffer);
        }

        // 将光标上移到上一次渲染的起点
        if (self::$liveRendered > 0) {
            echo "\033[" . self::$liveRendered . "A";
        }

        // 逐行清除当前行内容并重新绘制
        foreach (self::$liveBuffer as $bufLine) {
            echo "\r\033[2K" . self::apply('light_gray', "   $bufLine") . "\n";
        }

        self::$liveRendered = count(self::$liveBuffer);
    }

    /**
     * 结束滚动日志区域，重置内部状态。
     * 已渲染的行会保留在屏幕上，后续输出将从下一行开始。
     */
    public static function liveLogEnd(): void
    {
        self::$liveBuffer   = [];
        self::$liveRendered = 0;
    }

    /**
     * 获取当前终端列宽，不可获取时返回 120。
     * - Unix/macOS：通过 tput cols 读取
     * - Windows：通过 mode con 读取
     */
    private static function terminalWidth(): int
    {
        static $width = null;
        if ($width === null) {
            if (PHP_OS_FAMILY === 'Windows') {
                // Windows：解析 "mode con" 输出中的 Columns 字段
                $out = @shell_exec('mode con');
                if ($out && preg_match('/Columns[:\s]+(\d+)/i', $out, $m)) {
                    $width = (int)$m[1];
                } else {
                    $width = 120;
                }
            } else {
                $cols  = (int)@shell_exec('tput cols 2>/dev/null');
                $width = $cols > 20 ? $cols : 120;
            }
        }
        return $width;
    }
}

