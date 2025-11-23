/**
 * 主题切换功能（暗色模式）
 */
(function() {
    'use strict';
    
    const THEME_KEY = 'dofun-theme';
    const THEME_DARK = 'dark';
    const THEME_LIGHT = 'light';
    
    // 检测系统偏好
    function getSystemTheme() {
        if (window.matchMedia && window.matchMedia('(prefers-color-scheme: dark)').matches) {
            return THEME_DARK;
        }
        return THEME_LIGHT;
    }
    
    // 获取当前主题
    function getCurrentTheme() {
        const saved = localStorage.getItem(THEME_KEY);
        if (saved === THEME_DARK || saved === THEME_LIGHT) {
            return saved;
        }
        return getSystemTheme();
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
        
        // 监听系统主题变化
        if (window.matchMedia) {
            const mediaQuery = window.matchMedia('(prefers-color-scheme: dark)');
            mediaQuery.addEventListener('change', function(e) {
                // 如果用户没有手动设置过主题，跟随系统
                if (!localStorage.getItem(THEME_KEY)) {
                    setTheme(e.matches ? THEME_DARK : THEME_LIGHT);
                }
            });
        }
    });
    
    // 导出到全局
    window.ThemeToggle = {
        set: setTheme,
        toggle: toggleTheme,
        get: getCurrentTheme
    };
})();

