/**
 * 主题切换功能（暗色模式）
 */
(function() {
    'use strict';
    
    const THEME_KEY = 'dofun-theme';
    const THEME_DARK = 'dark';
    const THEME_LIGHT = 'light';
    
    // 检测系统偏好（已弃用，默认使用亮色模式）
    function getSystemTheme() {
        // 不再跟随系统偏好，默认返回亮色模式
        return THEME_LIGHT;
    }
    
    // 获取当前主题
    function getCurrentTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === THEME_DARK || saved === THEME_LIGHT) {
            return saved;
        }
        // 默认返回亮色模式
        return THEME_LIGHT;
    }
    
    // 设置主题
    function setTheme(theme) {
        const root = document.documentElement;
        if (theme === THEME_DARK) {
            root.setAttribute('data-theme', THEME_DARK);
        } else {
            root.removeAttribute('data-theme');
        }
        localStorage.setItem(THEME_KEY, theme);
        
        // 触发自定义事件
        window.dispatchEvent(new CustomEvent('themechange', { detail: { theme } }));
    }
    
    // 切换主题
    function toggleTheme() {
        const current = getCurrentTheme();
        const newTheme = current === THEME_DARK ? THEME_LIGHT : THEME_DARK;
        setTheme(newTheme);
        return newTheme;
    }
    
    // 初始化
    document.addEventListener('DOMContentLoaded', function() {
        const theme = getCurrentTheme();
        setTheme(theme);
        
        // 不再监听系统主题变化，默认使用亮色模式
        // 如果用户没有手动设置过主题，保持亮色模式
    });
    
    // 导出到全局
    window.ThemeToggle = {
        set: setTheme,
        toggle: toggleTheme,
        get: getCurrentTheme
    };
})();

