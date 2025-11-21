document.addEventListener('DOMContentLoaded', function () {
    var quizForm = document.getElementById('quiz-form');
    if (!quizForm) return;

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
