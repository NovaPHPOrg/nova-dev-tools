<?php

namespace nova\console;

/**
 * 终端 ANSI 颜色与样式渲染工具类。
 *
 * 支持标准前景色、背景色、文字样式（加粗、斜体、下划线等）
 * 以及 256 色扩展模式，同时兼容 Windows / Unix 双平台。
 */
class ConsoleColor
{
    /** 256 色模式下前景色的 ANSI 类型码。 */
    const int FOREGROUND = 38;

    /** 256 色模式下背景色的 ANSI 类型码。 */
    const int BACKGROUND = 48;

    /** 匹配 256 色样式名称的正则表达式，如 "color_196" / "bg_color_46"。 */
    const string COLOR256_REGEXP = '~^(bg_)?color_([0-9]{1,3})$~';

    /** ANSI 重置样式的转义码值。 */
    const int RESET_STYLE = 0;

    /**
     * 当前终端是否支持 ANSI 颜色输出。
     * @var bool
     */
    private bool $supportsAnsi;

    /**
     * 内置样式名称到 ANSI 转义码的映射表。
     * 键为样式名，值为对应的 SGR 参数字符串；null 表示该样式无实际效果。
     * @var array
     */
    private array $styles = array(
        // ─── 特殊 ────────────────────────────────────────────────
        'none'      => null,  // 无样式
        'bold'      => '1',   // 加粗
        'dark'      => '2',   // 暗淡
        'italic'    => '3',   // 斜体
        'underline' => '4',   // 下划线
        'blink'     => '5',   // 闪烁
        'reverse'   => '7',   // 反色（前景与背景互换）
        'concealed' => '8',   // 隐藏文字

        // ─── 前景色 ───────────────────────────────────────────────
        'default'      => '39',
        'black'        => '30',
        'red'          => '31',
        'green'        => '32',
        'yellow'       => '33',
        'blue'         => '34',
        'magenta'      => '35',
        'cyan'         => '36',
        'light_gray'   => '37',

        // ─── 亮前景色 ─────────────────────────────────────────────
        'dark_gray'      => '90',
        'light_red'      => '31',
        'light_green'    => '32',
        'light_yellow'   => '33',
        'light_blue'     => '34',
        'light_magenta'  => '35',
        'light_cyan'     => '36',
        'white'          => '37',

        // ─── 背景色 ───────────────────────────────────────────────
        'bg_default'    => '49',
        'bg_black'      => '40',
        'bg_red'        => '41',
        'bg_green'      => '42',
        'bg_yellow'     => '43',
        'bg_blue'       => '44',
        'bg_magenta'    => '45',
        'bg_cyan'       => '46',
        'bg_light_gray' => '47',

        // ─── 亮背景色 ─────────────────────────────────────────────
        'bg_dark_gray'     => '100',
        'bg_light_red'     => '41',
        'bg_light_green'   => '42',
        'bg_light_yellow'  => '43',
        'bg_light_blue'    => '44',
        'bg_light_magenta' => '45',
        'bg_light_cyan'    => '46',
        'bg_white'         => '47',
    );

    /** 构造函数：自动检测当前终端是否支持 ANSI 颜色。 */
    public function __construct()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            $this->supportsAnsi =
                (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT)) ||
                getenv('ANSICON') !== false ||
                getenv('ConEmuANSI') === 'ON';
        } else {
            $this->supportsAnsi = function_exists('posix_isatty') && @posix_isatty(STDOUT);
        }
    }

    /**
     * 将指定样式应用到文本，并返回带 ANSI 转义序列的字符串。
     * 若终端不支持，则原样返回文本。
     *
     * @param array|string $style 样式名称或样式名称数组
     * @param string $text  要渲染的文本
     * @return string 渲染后的字符串
     * @throws InvalidStyleException    样式名称不存在时抛出
     * @throws \InvalidArgumentException $style 类型不合法时抛出
     */
    public function apply(array|string $style, string $text): string
    {
        if (!$this->supportsAnsi) {
            return $text;
        }

        if (is_string($style)) {
            $style = array($style);
        }
        if (!is_array($style)) {
            throw new \InvalidArgumentException("Style must be string or array.");
        }

        $sequences = array();

        foreach ($style as $s) {
            if ($this->isValidStyle($s)) {
                $sequences[] = $this->styleSequence($s);
            } else {
                throw new InvalidStyleException($s);
            }
        }

        $sequences = array_filter($sequences, function ($val) {
            return $val !== null;
        });

        if (empty($sequences)) {
            return $text;
        }

        return $this->escSequence(implode(';', $sequences)) . $text . $this->escSequence(self::RESET_STYLE);
    }


    /**
     * 将单个样式名称转换为对应的 ANSI SGR 参数字符串。
     * 若为 256 色样式且终端支持，则生成扩展序列；否则返回 null。
     *
     * @param string $style 样式名称
     * @return string|null
     */
    private function styleSequence(string $style): ?string
    {
        if (array_key_exists($style, $this->styles)) {
            return $this->styles[$style];
        }

        if (DIRECTORY_SEPARATOR === '\\') {
            $supports256 = function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT);
        } else {
            $supports256 = str_contains((string)getenv('TERM'), '256color');
        }

        if (!$supports256) {
            return null;
        }

        preg_match(self::COLOR256_REGEXP, $style, $matches);

        $type = $matches[1] === 'bg_' ? self::BACKGROUND : self::FOREGROUND;
        $value = $matches[2];

        return "$type;5;$value";
    }

    /**
     * 判断给定样式名称是否合法（内置样式或合法的 256 色格式）。
     *
     * @param string $style 样式名称
     * @return bool
     */
    private function isValidStyle(string $style): bool
    {
        return array_key_exists($style, $this->styles) || preg_match(self::COLOR256_REGEXP, $style);
    }

    /**
     * 生成 ANSI 转义序列字符串，格式为 "\033[{value}m"。
     *
     * @param int|string $value SGR 参数值
     * @return string
     */
    private function escSequence(int|string $value): string
    {
        return "\033[{$value}m";
    }
}

