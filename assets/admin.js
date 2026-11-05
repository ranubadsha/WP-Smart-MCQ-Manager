/* Live readiness hint in the private WordPress question editor. */
(function () {
    'use strict';
    function check() {
        var hint = document.getElementById('smcq-answer-readiness');
        if (!hint) { return; }
        var title = document.getElementById('title');
        var correct = document.getElementById('smcq_correct');
        var options = ['A', 'B', 'C', 'D'].map(function (letter) {
            return document.getElementById('smcq_option_' + letter);
        });
        var ready = title && title.value.trim() && correct && correct.value && options.every(function (option) {
            return option && option.value.trim();
        });
        hint.textContent = ready ? '✓ All four options and a correct answer are provided.' : 'Complete the title, all four options and correct answer to make this question exam-ready.';
        hint.style.color = ready ? '#008a20' : '#9a4900';
    }
    document.addEventListener('DOMContentLoaded', function () {
        ['title', 'smcq_correct', 'smcq_option_A', 'smcq_option_B', 'smcq_option_C', 'smcq_option_D'].forEach(function (id) {
            var input = document.getElementById(id);
            if (input) { input.addEventListener('input', check); input.addEventListener('change', check); }
        });
        check();
    });
}());