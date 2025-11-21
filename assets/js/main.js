document.addEventListener('DOMContentLoaded', function () {
    var quizForm = document.getElementById('quiz-form');
    if (!quizForm) return;

    var stepBlocks = quizForm.querySelectorAll('.question-step');
    if (!stepBlocks.length) return; // single_page

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
});
