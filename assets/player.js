/* Smart MCQ Step 5. Answers never contain correct-answer keys or scoring data. */
(function () {
    'use strict';
    if (!window.smcqPlayerSettings) return;
    var settings = window.smcqPlayerSettings;
    var create = function (tag, klass, content) {
        var node = document.createElement(tag);
        if (klass) node.className = klass;
        if (content !== undefined) node.textContent = String(content);
        return node;
    };
    var makeButton = function (label, klass, handler) {
        var button = create('button', klass, label);
        button.type = 'button';
        button.addEventListener('click', handler);
        return button;
    };
    var request = async function (path, method, body) {
        var options = {
            method: method || 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: { 'X-WP-Nonce': settings.nonce }
        };
        if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        var response = await fetch(settings.root + path, options);
        var data = await response.json();
        if (!response.ok) {
            var error = new Error((data && data.message) || settings.messages.error);
            error.status = response.status;
            throw error;
        }
        return data;
    };

    document.querySelectorAll('.smcq-player[data-exam-id]').forEach(function (root) {
        var examId = Number(root.getAttribute('data-exam-id'));
        var status = root.querySelector('.smcq-player-status');
        var body = root.querySelector('.smcq-player-body');
        var active = null;
        var index = 0;
        var timeOffset = 0;
        var timerId = null;
        var busy = false;
        var unsaved = false;
        var ended = false;
        var saveError = '';

        function message(text, error) {
            status.textContent = text || '';
            status.classList.toggle('smcq-error', Boolean(error));
        }
        function stopTimer() {
            if (timerId !== null) clearInterval(timerId);
            timerId = null;
        }
        function clearBody() {
            while (body.firstChild) body.removeChild(body.firstChild);
        }
        function formatTime(ms) {
            var seconds = Math.max(0, Math.ceil(ms / 1000));
            var hours = Math.floor(seconds / 3600);
            var minutes = Math.floor((seconds % 3600) / 60);
            var rem = seconds % 60;
            var mmss = String(minutes).padStart(2, '0') + ':' + String(rem).padStart(2, '0');
            return hours ? hours + ':' + mmss : mmss;
        }
        async function load() {
            message(settings.messages.loading);
            clearBody();
            try {
                var state = await request('exams/' + examId + '/state');
                if (state.status === 'active') showAttempt(state);
                else showReady(state);
            } catch (error) { message(error.message, true); }
        }
        function showReady(state) {
            active = null;
            ended = true;
            stopTimer();
            clearBody();
            body.appendChild(create('h2', 'smcq-exam-title', state.examTitle || 'Examination'));
            if (state.instructions) body.appendChild(create('p', 'smcq-exam-instructions', state.instructions));
            if (state.message) body.appendChild(create('p', 'smcq-note', state.message));
            body.appendChild(create('p', 'smcq-meta', 'Duration: ' + state.duration + ' min · Attempts: ' + state.attemptsUsed + (state.attemptLimit ? ' / ' + state.attemptLimit : ' (unlimited)')));
            if (state.canStart) {
                body.appendChild(makeButton('Start Examination', 'smcq-button smcq-primary', async function () {
                    if (busy) return;
                    busy = true;
                    message(settings.messages.loading);
                    try { showAttempt(await request('exams/' + examId + '/start', 'POST', {})); }
                    catch (error) { message(error.message, true); }
                    finally { busy = false; }
                }));
                message('Ready to begin. Your countdown starts when you press Start Examination.');
            } else {
                message(state.message || 'This examination is not available for a new attempt.');
            }
        }
        function showAttempt(state) {
            if (state.status !== 'active') { showFinished(state); return; }
            active = state;
            index = 0;
            ended = false;
            saveError = '';
            unsaved = false;
            timeOffset = state.serverNow - Date.now();
            stopTimer();
            timerId = setInterval(tick, 1000);
            render();
            tick();
        }
        function tick() {
            if (!active || ended) return;
            var remaining = active.deadline - (Date.now() + timeOffset);
            var timer = root.querySelector('.smcq-timer');
            if (timer) timer.textContent = 'Time left: ' + formatTime(remaining);
            if (remaining <= 0) {
                finalize(false);
            }
        }
        function render() {
            if (!active || ended) return;
            clearBody();
            var questions = active.questions;
            if (!questions || !questions.length) { message('This attempt has no available questions.', true); return; }
            var question = questions[index];
            var answered = Object.keys(active.answers).length;
            var header = create('div', 'smcq-player-header');
            header.appendChild(create('h2', 'smcq-exam-title', active.examTitle || 'Examination'));
            header.appendChild(create('div', 'smcq-timer'));
            body.appendChild(header);
            body.appendChild(create('p', 'smcq-progress', 'Question ' + (index + 1) + ' of ' + questions.length + ' · Answered: ' + answered));
            var form = create('fieldset', 'smcq-question');
            form.appendChild(create('legend', '', question.title));
            question.options.forEach(function (option) {
                var label = create('label', 'smcq-option');
                var input = create('input');
                input.type = 'radio';
                input.name = 'smcq-choice-' + active.attemptId;
                input.value = option.key;
                input.checked = active.answers[String(question.id)] === option.key;
                input.disabled = busy || unsaved;
                input.addEventListener('change', function () { saveAnswer(question.id, option.key); });
                label.appendChild(input);
                label.appendChild(create('span', '', option.label + '. ' + option.text));
                form.appendChild(label);
            });
            body.appendChild(form);
            var actions = create('div', 'smcq-player-actions');
            var prev = makeButton('← Previous', 'smcq-button', function () { if (index > 0) { index--; render(); } });
            prev.disabled = index === 0;
            actions.appendChild(prev);
            var clear = makeButton('Clear Answer', 'smcq-button', function () { saveAnswer(question.id, null); });
            clear.disabled = busy || unsaved || active.answers[String(question.id)] === undefined;
            actions.appendChild(clear);
            var next = makeButton('Next →', 'smcq-button', function () { if (index + 1 < questions.length) { index++; render(); } });
            next.disabled = index + 1 >= questions.length;
            actions.appendChild(next);
            body.appendChild(actions);
            var navigator = create('div', 'smcq-navigator');
            navigator.setAttribute('aria-label', 'Question navigator');
            questions.forEach(function (item, i) {
                var nav = makeButton(String(i + 1), 'smcq-nav-item' + (i === index ? ' is-current' : '') + (active.answers[String(item.id)] ? ' is-answered' : ''), function () { index = i; render(); });
                nav.setAttribute('aria-label', 'Question ' + (i + 1) + (active.answers[String(item.id)] ? ', answered' : ', unanswered'));
                if (i === index) nav.setAttribute('aria-current', 'step');
                navigator.appendChild(nav);
            });
            body.appendChild(navigator);
            var finish = makeButton('Submit Examination', 'smcq-button smcq-primary smcq-submit', function () { finalize(true); });
            finish.disabled = busy || unsaved;
            body.appendChild(finish);
            if (!unsaved) message(saveError || settings.messages.saved, Boolean(saveError));
            tick();
        }
        async function saveAnswer(questionId, choice) {
            if (!active || ended || busy || unsaved) return;
            unsaved = true;
            busy = true;
            saveError = '';
            message(settings.messages.saving);
            render();
            try {
                var result = await request('attempts/' + active.attemptId + '/answer', 'POST', {
                    questionId: questionId, choice: choice, version: active.version
                });
                active.answers = result.answers;
                active.version = result.version;
                timeOffset = result.serverNow - Date.now();
                saveError = '';
                message(settings.messages.saved);
            } catch (error) {
                saveError = error.status === 409 ? settings.messages.conflict : error.message;
                message(saveError, true);
                if (error.status === 409) { stopTimer(); ended = true; body.appendChild(makeButton('Reload Attempt', 'smcq-button', load)); }
            } finally {
                unsaved = false;
                busy = false;
                if (!ended) render();
            }
        }
        async function finalize(confirmFirst) {
            if (!active || ended || busy || unsaved) return;
            if (confirmFirst && !window.confirm(settings.messages.confirm)) return;
            busy = true;
            message('Submitting examination…');
            try {
                var result = await request('attempts/' + active.attemptId + '/submit', 'POST', {});
                showFinished(result);
            } catch (error) { message(error.message, true); }
            finally { busy = false; }
        }
        function showFinished(result) {
            ended = true;
            active = null;
            unsaved = false;
            stopTimer();
            clearBody();
            body.appendChild(create('h2', '', 'Examination submitted'));
            var text = result.completionReason === 'timeout' ? settings.messages.expired : settings.messages.finished;
            body.appendChild(create('p', 'smcq-note', text));
            body.appendChild(makeButton('Back to Exam', 'smcq-button', load));
            message('Attempt recorded.');
        }
        window.addEventListener('beforeunload', function (event) {
            if (unsaved) { event.preventDefault(); event.returnValue = settings.messages.unsaved; }
        });
        load();
    });
})();