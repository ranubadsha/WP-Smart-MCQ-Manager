/* Private question editor: type-dependent completeness hints. */
(function () {
    'use strict';
    function update() {
        var type = (document.getElementById('smcq_type') || {}).value || 'single';
        var multi = document.getElementById('smcq_multi_choices');
        var single = document.getElementById('smcq_correct');
        if (multi) multi.hidden = type !== 'multiple';
        if (single && single.parentNode) single.parentNode.hidden = type === 'multiple';
        ['C', 'D'].forEach(function (letter) {
            var field = document.getElementById('smcq_option_' + letter);
            if (field && field.parentElement) field.parentElement.hidden = type === 'true_false';
        });
        var options = (type === 'true_false' ? ['A', 'B'] : ['A', 'B', 'C', 'D']).map(function (key) { return document.getElementById('smcq_option_' + key); });
        var keys = document.querySelectorAll('input[name="smcq_correct_keys[]"]:checked');
        var title = document.getElementById('title');
        var hint = document.getElementById('smcq-answer-readiness');
        var media = document.getElementById('smcq_media_id');
        var passage = document.getElementById('smcq_passage');
        var answer = type === 'multiple' ? keys.length >= 2 : single && Boolean(single.value) && (type !== 'true_false' || ['A', 'B'].indexOf(single.value) !== -1);
        var ready = title && title.value.trim() && options.every(function (x) { return x && x.value.trim(); }) && answer;
        if (type === 'passage') ready = ready && passage && passage.value.trim();
        if (type === 'image' || type === 'audio') ready = ready && media && Number(media.value) > 0;
        if (hint) {
            hint.textContent = ready ? '✓ Answer fields appear complete. Save to validate attachments.' : 'Complete the question, required options, answer, and applicable passage/media fields.';
            hint.style.color = ready ? '#008a20' : '#9a4900';
        }
    }
    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('#title, #smcq_type, #smcq_correct, #smcq_passage, #smcq_media_id, [id^="smcq_option_"], input[name="smcq_correct_keys[]"]').forEach(function (el) {
            el.addEventListener('input', update); el.addEventListener('change', update);
        });
        update();
    });
}());