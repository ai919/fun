<?php
/**
 * 国际化支持框架
 * 支持多语言错误消息和界面文本
 */

class I18n {
    private static $currentLang = 'zh-CN';
    private static $translations = [];
    private static $loaded = false;

    /**
     * 设置当前语言
     */
    public static function setLanguage(string $lang): void {
        self::$currentLang = $lang;
        self::loadTranslations();
    }

    /**
     * 获取当前语言
     */
    public static function getLanguage(): string {
        return self::$currentLang;
    }

    /**
     * 从浏览器或会话中检测语言
     */
    public static function detectLanguage(): string {
        // 优先从会话获取
        if (session_status() === PHP_SESSION_ACTIVE && isset($_SESSION['lang'])) {
            return $_SESSION['lang'];
        }

        // 从浏览器Accept-Language头检测
        if (isset($_SERVER['HTTP_ACCEPT_LANGUAGE'])) {
            $langs = explode(',', $_SERVER['HTTP_ACCEPT_LANGUAGE']);
            foreach ($langs as $lang) {
                $lang = trim(explode(';', $lang)[0]);
                if (in_array($lang, ['zh-CN', 'zh-TW', 'en-US', 'en'])) {
                    return $lang === 'zh' ? 'zh-CN' : ($lang === 'en' ? 'en-US' : $lang);
                }
            }
        }

        return 'zh-CN'; // 默认中文
    }

    /**
     * 加载翻译文件
     */
    private static function loadTranslations(): void {
        if (self::$loaded && isset(self::$translations[self::$currentLang])) {
            return;
        }

        $langFile = __DIR__ . '/../lang/' . self::$currentLang . '.php';
        if (file_exists($langFile)) {
            self::$translations[self::$currentLang] = require $langFile;
        } else {
            // 回退到中文
            $fallbackFile = __DIR__ . '/../lang/zh-CN.php';
            if (file_exists($fallbackFile)) {
                self::$translations[self::$currentLang] = require $fallbackFile;
            } else {
                self::$translations[self::$currentLang] = [];
            }
        }
        self::$loaded = true;
    }

    /**
     * 翻译文本
     */
    public static function translate(string $key, array $params = []): string {
        self::loadTranslations();
        
        $translation = self::$translations[self::$currentLang][$key] ?? $key;
        
        // 替换参数
        if (!empty($params)) {
            foreach ($params as $paramKey => $paramValue) {
                $translation = str_replace('{' . $paramKey . '}', $paramValue, $translation);
            }
        }
        
        return $translation;
    }

    /**
     * 翻译文本（简写）
     */
    public static function t(string $key, array $params = []): string {
        return self::translate($key, $params);
    }

    /**
     * 获取所有支持的语言
     */
    public static function getSupportedLanguages(): array {
        return [
            'zh-CN' => '简体中文',
            'zh-TW' => '繁體中文',
            'en-US' => 'English',
        ];
    }
}

