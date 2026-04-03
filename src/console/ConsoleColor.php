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
    const FOREGROUND = 38;

    /** 256 色模式下背景色的 ANSI 类型码。 */
    const BACKGROUND = 48;

    /** 匹配 256 色样式名称的正则表达式，如 "color_196" / "bg_color_46"。 */
    const COLOR256_REGEXP = '~^(bg_)?color_([0-9]{1,3})$~';

    /** ANSI 重置样式的转义码值。 */
    const RESET_STYLE = 0;

    /**
     * 当前终端是否支持 ANSI 颜色输出。
     * @var bool
     */
    private $isSupported;

    /**
     * 是否强制启用样式输出（忽略终端检测结果）。
     * @var bool
     */
    private $forceStyle = false;

    /**
     * 内置样式名称到 ANSI 转义码的映射表。
     * 键为样式名，值为对应的 SGR 参数字符串；null 表示该样式无实际效果。
     * @var array
     */
    private $styles = array(
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
        'light_red'      => '91',
        'light_green'    => '92',
        'light_yellow'   => '93',
        'light_blue'     => '94',
        'light_magenta'  => '95',
        'light_cyan'     => '96',
        'white'          => '97',

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
        'bg_light_red'     => '101',
        'bg_light_green'   => '102',
        'bg_light_yellow'  => '103',
        'bg_light_blue'    => '104',
        'bg_light_magenta' => '105',
        'bg_light_cyan'    => '106',
        'bg_white'         => '107',
    );

    /**
     * 用户自定义主题（样式组合）映射表。
     * 键为主题名，值为样式名称数组。
     * @var array
     */
    private $themes = array();

    /** 构造函数：自动检测当前终端是否支持 ANSI 颜色。 */
    public function __construct()
    {
        $this->isSupported = $this->isSupported();
    }

    /**
     * 将指定样式应用到文本，并返回带 ANSI 转义序列的字符串。
     * 若终端不支持且未强制启用，则原样返回文本。
     *
     * @param string|array $style 样式名称或样式名称数组
     * @param string       $text  要渲染的文本
     * @return string 渲染后的字符串
     * @throws InvalidStyleException    样式名称不存在时抛出
     * @throws \InvalidArgumentException $style 类型不合法时抛出
     */
    public function apply($style, $text)
    {
        if (!$this->isStyleForced() && !$this->isSupported()) {
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
            if (isset($this->themes[$s])) {
                $sequences = array_merge($sequences, $this->themeSequence($s));
            } else if ($this->isValidStyle($s)) {
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
     * 强制启用或关闭样式输出（绕过终端自动检测）。
     *
     * @param bool $forceStyle true 表示强制启用
     */
    public function setForceStyle($forceStyle)
    {
        $this->forceStyle = (bool) $forceStyle;
    }

    /**
     * 返回当前是否处于强制样式模式。
     *
     * @return bool
     */
    public function isStyleForced()
    {
        return $this->forceStyle;
    }

    /**
     * 批量设置自定义主题，覆盖已有的全部主题。
     *
     * @param array $themes 主题映射，格式为 ['主题名' => '样式名或样式数组', ...]
     * @throws InvalidStyleException
     * @throws \InvalidArgumentException
     */
    public function setThemes(array $themes)
    {
        $this->themes = array();
        foreach ($themes as $name => $styles) {
            $this->addTheme($name, $styles);
        }
    }

    /**
     * 添加单个自定义主题（样式组合）。
     *
     * @param string       $name   主题名称
     * @param array|string $styles 样式名或样式名数组
     * @throws \InvalidArgumentException
     * @throws InvalidStyleException
     */
    public function addTheme($name, $styles)
    {
        if (is_string($styles)) {
            $styles = array($styles);
        }
        if (!is_array($styles)) {
            throw new \InvalidArgumentException("Style must be string or array.");
        }

        foreach ($styles as $style) {
            if (!$this->isValidStyle($style)) {
                throw new InvalidStyleException($style);
            }
        }

        $this->themes[$name] = $styles;
    }

    /**
     * 返回所有已注册的自定义主题。
     *
     * @return array
     */
    public function getThemes()
    {
        return $this->themes;
    }

    /**
     * 判断指定主题是否已注册。
     *
     * @param string $name 主题名称
     * @return bool
     */
    public function hasTheme($name)
    {
        return isset($this->themes[$name]);
    }

    /**
     * 移除指定的自定义主题。
     *
     * @param string $name 主题名称
     */
    public function removeTheme($name)
    {
        unset($this->themes[$name]);
    }

    /**
     * 检测当前终端是否支持 ANSI 颜色输出。
     * Windows 下检测 VT100 / ANSICON / ConEmu；Unix 下使用 posix_isatty()。
     *
     * @return bool
     */
    public function isSupported()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            if (function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT)) {
                return true;
            } elseif (getenv('ANSICON') !== false || getenv('ConEmuANSI') === 'ON') {
                return true;
            }
            return false;
        } else {
            return function_exists('posix_isatty') && @posix_isatty(STDOUT);
        }
    }

    /**
     * 检测当前终端是否支持 256 色扩展模式。
     * Windows 下依赖 VT100 支持；Unix 下检查 $TERM 环境变量。
     *
     * @return bool
     */
    public function are256ColorsSupported()
    {
        if (DIRECTORY_SEPARATOR === '\\') {
            return function_exists('sapi_windows_vt100_support') && @sapi_windows_vt100_support(STDOUT);
        } else {
            return strpos(getenv('TERM'), '256color') !== false;
        }
    }

    /**
     * 返回所有内置样式的名称列表。
     *
     * @return array
     */
    public function getPossibleStyles()
    {
        return array_keys($this->styles);
    }

    /**
     * 将主题展开为对应的 ANSI 转义码序列数组。
     *
     * @param string $name 主题名称
     * @return string[]
     */
    private function themeSequence($name)
    {
        $sequences = array();
        foreach ($this->themes[$name] as $style) {
            $sequences[] = $this->styleSequence($style);
        }
        return $sequences;
    }

    /**
     * 将单个样式名称转换为对应的 ANSI SGR 参数字符串。
     * 若为 256 色样式且终端支持，则生成扩展序列；否则返回 null。
     *
     * @param string $style 样式名称
     * @return string|null
     */
    private function styleSequence($style)
    {
        if (array_key_exists($style, $this->styles)) {
            return $this->styles[$style];
        }

        if (!$this->are256ColorsSupported()) {
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
    private function isValidStyle($style)
    {
        return array_key_exists($style, $this->styles) || preg_match(self::COLOR256_REGEXP, $style);
    }

    /**
     * 生成 ANSI 转义序列字符串，格式为 "\033[{value}m"。
     *
     * @param string|int $value SGR 参数值
     * @return string
     */
    private function escSequence($value)
    {
        return "\033[{$value}m";
    }
}

