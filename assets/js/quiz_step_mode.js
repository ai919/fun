document.addEventListener('DOMContentLoaded', function () {
  const wrapper = document.querySelector('.quiz-questions-wrapper.step-mode');
  if (!wrapper) return; // 非逐题模式直接退出

  const blocks = Array.from(wrapper.querySelectorAll('.quiz-question-block'));
  if (!blocks.length) return;

  const total = blocks.length;
  let current = 1;

  const btnPrev   = document.getElementById('btn-prev-question');
  const btnNext   = document.getElementById('btn-next-question');
  const btnSubmit = document.getElementById('btn-submit-quiz');
  const progress  = document.getElementById('quiz-progress-text');

  function updateVisible() {
    blocks.forEach(block => {
      const step = parseInt(block.getAttribute('data-step'), 10);
      block.style.display = (step === current) ? 'block' : 'none';
    });

    // 按钮状态
    if (current === 1) {
      btnPrev.style.display = 'none';
    } else {
      btnPrev.style.display = 'inline-flex';
    }

    if (current === total) {
      btnNext.style.display = 'none';
      btnSubmit.style.display = 'inline-flex';
    } else {
      btnNext.style.display = 'inline-flex';
      btnSubmit.style.display = 'none';
    }

    // 更新“已作答”统计
    const form = document.getElementById('quiz-form');
    const answered = form.querySelectorAll('.quiz-question-block input[type="radio"]:checked').length;
    if (progress) {
      progress.textContent = answered + ' / ' + total + ' 题已作答';
    }
  }

  function goto(step) {
    if (step < 1 || step > total) return;
    current = step;
    updateVisible();
    window.scrollTo({ top: wrapper.offsetTop - 40, behavior: 'smooth' });
  }

  // 选项点击后自动到下一题
  blocks.forEach(block => {
    block.addEventListener('change', function (e) {
      const target = e.target;
      if (target && target.matches('input[type="radio"]')) {
        // 轻微延迟，给用户一点反馈时间
        setTimeout(function () {
          if (current < total) {
            goto(current + 1);
          } else {
            // 最后一题，自动滚动到按钮位置
            const footer = document.querySelector('.quiz-step-footer');
            if (footer) {
              window.scrollTo({ top: footer.offsetTop - 40, behavior: 'smooth' });
            }
          }
        }, 120);
      }
    });
  });

  // 上一题 / 下一题 按钮
  if (btnPrev) {
    btnPrev.addEventListener('click', function () {
      goto(current - 1);
    });
  }

  if (btnNext) {
    btnNext.addEventListener('click', function () {
      goto(current + 1);
    });
  }

  // 初始化：只显示第一题
  updateVisible();
});
