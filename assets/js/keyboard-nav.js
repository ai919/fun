/**
 * 键盘导航支持
 */
document.addEventListener('DOMContentLoaded', function() {
    // 选项键盘导航
    const optionLabels = document.querySelectorAll('.quiz-option-item[role="radio"]');
    
    optionLabels.forEach(function(label) {
        const radio = label.querySelector('input[type="radio"]');
        if (!radio) return;
        
        // 点击标签时更新aria-checked
        label.addEventListener('click', function() {
            updateAriaChecked();
        });
        
        // 键盘导航
        label.addEventListener('keydown', function(e) {
            if (e.key === 'Enter' || e.key === ' ') {
                e.preventDefault();
                radio.checked = true;
                radio.dispatchEvent(new Event('change', { bubbles: true }));
                updateAriaChecked();
            } else if (e.key === 'ArrowDown' || e.key === 'ArrowUp') {
                e.preventDefault();
                navigateOptions(label, e.key === 'ArrowDown' ? 1 : -1);
            }
        });
    });
    
    function updateAriaChecked() {
        optionLabels.forEach(function(label) {
            const radio = label.querySelector('input[type="radio"]');
            if (radio) {
                label.setAttribute('aria-checked', radio.checked ? 'true' : 'false');
            }
        });
    }
    
    function navigateOptions(currentLabel, direction) {
        const labels = Array.from(optionLabels);
        const currentIndex = labels.indexOf(currentLabel);
        const nextIndex = currentIndex + direction;
        
        if (nextIndex >= 0 && nextIndex < labels.length) {
            labels[nextIndex].focus();
        }
    }
    
    // 表单提交快捷键 (Ctrl/Cmd + Enter)
    const forms = document.querySelectorAll('form');
    forms.forEach(function(form) {
        form.addEventListener('keydown', function(e) {
            if ((e.ctrlKey || e.metaKey) && e.key === 'Enter') {
                e.preventDefault();
                const submitBtn = form.querySelector('button[type="submit"]');
                if (submitBtn && !submitBtn.disabled) {
                    submitBtn.click();
                }
            }
        });
    });
    
    // 跳过链接（无障碍）
    const skipLink = document.createElement('a');
    skipLink.href = '#main-content';
    skipLink.textContent = '跳转到主要内容';
    skipLink.className = 'skip-link';
    skipLink.style.cssText = 'position: absolute; left: -9999px; z-index: 999; padding: 8px; background: #000; color: #fff;';
    skipLink.addEventListener('focus', function() {
        this.style.left = '0';
    });
    skipLink.addEventListener('blur', function() {
        this.style.left = '-9999px';
    });
    document.body.insertBefore(skipLink, document.body.firstChild);
    
    // 初始化aria-checked状态
    updateAriaChecked();
});

