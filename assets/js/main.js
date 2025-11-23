document.addEventListener('DOMContentLoaded', function () {
    var quizForm = document.getElementById('quiz-form');
    if (!quizForm) return;

    // 表单提交加载状态处理
    var submitButtons = quizForm.querySelectorAll('button[type="submit"]');
    var originalButtonTexts = {};
    
    submitButtons.forEach(function(btn) {
        originalButtonTexts[btn.id || btn.className] = btn.textContent || btn.innerHTML;
    });

    function setLoadingState(isLoading) {
        submitButtons.forEach(function(btn) {
            if (isLoading) {
                btn.disabled = true;
                btn.setAttribute('aria-busy', 'true');
                var btnKey = btn.id || btn.className;
                btn.dataset.originalText = btn.textContent || btn.innerHTML;
                btn.innerHTML = '<span class="btn-loading-spinner"></span> 提交中...';
                btn.classList.add('btn-loading');
            } else {
                btn.disabled = false;
                btn.removeAttribute('aria-busy');
                var btnKey = btn.id || btn.className;
                var originalText = btn.dataset.originalText || originalButtonTexts[btnKey];
                if (originalText) {
                    btn.innerHTML = originalText;
                }
                btn.classList.remove('btn-loading');
            }
        });
    }

    let submitTimeout = null;
    let isSubmitting = false;

    quizForm.addEventListener('submit', function(e) {
        // 防止重复提交
        if (isSubmitting) {
            e.preventDefault();
            return false;
        }

        // 验证表单
        if (!quizForm.checkValidity()) {
            return;
        }
        
        isSubmitting = true;
        setLoadingState(true);
        
        // 清理之前的超时定时器
        if (submitTimeout !== null) {
            clearTimeout(submitTimeout);
            submitTimeout = null;
        }
        
        // 如果表单提交失败（网络错误等），恢复按钮状态
        // 注意：如果提交成功会跳转，所以这里主要是处理失败情况
        submitTimeout = setTimeout(function() {
            // 如果10秒后还在当前页面，说明可能提交失败，恢复按钮
            if (document.getElementById('quiz-form')) {
                isSubmitting = false;
                setLoadingState(false);
            }
        }, 10000); // 从5秒改为10秒，给服务器更多响应时间
    });

    // 页面卸载时清理定时器
    window.addEventListener('beforeunload', function() {
        if (submitTimeout !== null) {
            clearTimeout(submitTimeout);
            submitTimeout = null;
        }
    });

    // 只有 step_by_step 模式下，题目容器才会带 .question-step
    var stepBlocks = quizForm.querySelectorAll('.question-step');
    if (!stepBlocks.length) return; // single_page 模式直接退出

    var totalSteps = stepBlocks.length;

    function showStep(stepIndex) {
        stepBlocks.forEach(function (block) {
            var blockStep = parseInt(block.getAttribute('data-step'), 10);
            if (blockStep === stepIndex) {
                block.classList.add('active');
            } else {
                block.classList.remove('active');
            }
        });
        window.scrollTo({
            top: quizForm.offsetTop - 20,
            behavior: 'smooth'
        });
    }

    // 点击 上一题 / 下一题 按钮
    quizForm.addEventListener('click', function (e) {
        var target = e.target;

        if (target.classList.contains('btn-next')) {
            e.preventDefault();
            var currentBlock = target.closest('.question-step');
            if (!currentBlock) return;

            var hasAnswer = currentBlock.querySelector('input[type="radio"]:checked, input[type="checkbox"]:checked, select option:checked');
            if (!hasAnswer) {
                currentBlock.classList.add('shake');
                setTimeout(function () {
                currentBlock.classList.remove('shake');
            }, 400);
            return;
        }

        var next = parseInt(target.getAttribute('data-next'), 10);
            if (!isNaN(next)) {
                showStep(next);
            }
        }

        if (target.classList.contains('btn-prev')) {
            e.preventDefault();
            var prev = parseInt(target.getAttribute('data-prev'), 10);
            if (!isNaN(prev)) {
                showStep(prev);
            }
        }
    });

    // 选项自动跳转到下一题：仅针对 radio
    quizForm.addEventListener('change', function (e) {
        var target = e.target;
        if (target.tagName.toLowerCase() !== 'input') return;
        if (target.type !== 'radio') return;

        var currentBlock = target.closest('.question-step');
        if (!currentBlock) return;

        var currentStep = parseInt(currentBlock.getAttribute('data-step'), 10);
        if (isNaN(currentStep)) return;

        // 最后一题不自动提交，停留等待用户手动提交
        if (currentStep >= totalSteps) {
            return;
        }

        var nextStep = currentStep + 1;
        showStep(nextStep);
    });
});
