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

  // 检查当前题目是否已作答
  function isCurrentQuestionAnswered() {
    const currentBlock = blocks[current - 1];
    if (!currentBlock) return false;
    
    const radios = currentBlock.querySelectorAll('input[type="radio"][name^="q["]');
    for (let i = 0; i < radios.length; i++) {
      if (radios[i].checked) {
        return true;
      }
    }
    return false;
  }

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
      // 上一题按钮始终可用（除非是第一题）
      btnPrev.disabled = false;
      btnPrev.classList.remove('btn-disabled');
    }

    if (current === total) {
      btnNext.style.display = 'none';
      btnSubmit.style.display = 'inline-flex';
    } else {
      btnNext.style.display = 'inline-flex';
      btnSubmit.style.display = 'none';
      
      // 检查当前题目是否已作答
      const isAnswered = isCurrentQuestionAnswered();
      if (isAnswered) {
        // 已作答：下一题按钮点亮并启用
        btnNext.disabled = false;
        btnNext.classList.remove('btn-disabled');
      } else {
        // 未作答：下一题按钮灰色并禁用
        btnNext.disabled = true;
        btnNext.classList.add('btn-disabled');
      }
    }

    // 更新"已作答"统计
    const form = document.getElementById('quiz-form');
    const allRadios = form.querySelectorAll('input[type="radio"][name^="q["]');
    const answeredQuestions = new Set();
    allRadios.forEach(function(radio) {
      if (radio.checked) {
        answeredQuestions.add(radio.name);
      }
    });
    const answered = answeredQuestions.size;
    if (progress) {
      progress.textContent = answered + ' / ' + total + ' 题已作答';
    }
    
    // 更新提交按钮状态
    if (btnSubmit) {
      const isComplete = answered >= total;
      if (isComplete) {
        btnSubmit.disabled = false;
        btnSubmit.classList.remove('btn-disabled');
      } else {
        btnSubmit.disabled = true;
        btnSubmit.classList.add('btn-disabled');
      }
    }
    
    // 如果主脚本的 updateProgress 函数存在，也调用它（用于更新全局进度条）
    if (typeof window.updateProgress === 'function') {
      window.updateProgress();
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
        // 立即更新按钮状态
        updateVisible();
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
      // 检查当前题目是否已作答
      if (!isCurrentQuestionAnswered()) {
        // 未作答时显示提醒
        if (typeof window.showToast === 'function') {
          window.showToast('请先选择答案后再继续下一题哦。');
        } else {
          // 如果 showToast 不可用，使用 alert 作为后备
          alert('请先选择答案后再继续下一题哦。');
        }
        return;
      }
      // 已作答，允许跳转到下一题
      goto(current + 1);
    });
  }

  // 初始化：只显示第一题
  updateVisible();
});
