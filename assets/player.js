/* Smart MCQ Step 10. Private exam player and optional tab monitoring. */
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
    var request = async function (path, method, body, clientId) {
        var options = {
            method: method || 'GET', credentials: 'same-origin', cache: 'no-store',
            headers: { 'X-WP-Nonce': settings.nonce }
        };
        if (clientId) options.headers['X-SMCQ-Client-Id'] = clientId;
        if (body !== undefined) {
            options.headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(body);
        }
        var response = await fetch(settings.root + path, options);
        var data = await response.json();
        if (!response.ok) {
            var error = new Error((data && data.message) || settings.messages.error);
            error.status = response.status;
            error.code = data && data.code;
            error.attemptId = data && data.data && data.data.attemptId;
            throw error;
        }
        return data;
    };

    function addPracticeBookmark(container, item) {
        if (!item.practiceEligible) return;
        var save = makeButton('☆ Bookmark for Revision', 'smcq-button smcq-review-bookmark', async function () {
            save.disabled = true;
            try {
                await request('practice/bookmarks/' + item.questionId, 'POST', {});
                save.textContent = '★ Saved for Revision';
            } catch (error) {
                save.textContent = error.message;
                save.disabled = false;
            }
        });
        container.appendChild(save);
    }

    function addIssueReport(parent, item, attemptId) {
        if (!attemptId || !item.questionId) return;
        parent.appendChild(makeButton('Report a Question Error', 'smcq-button smcq-report-issue', function () {
            var reason = window.prompt('Issue type: incorrect_key, unclear_wording, incorrect_explanation, missing_information, other', 'incorrect_key');
            if (reason === null) return;
            if (['incorrect_key', 'unclear_wording', 'incorrect_explanation', 'missing_information', 'other'].indexOf(reason) === -1) { window.alert('Choose one of the listed issue types.'); return; }
            var details = window.prompt('Describe the issue (15–2,000 characters).');
            if (details === null) return;
            if (details.length < 15 || details.length > 2000) { window.alert('Description must contain 15–2,000 characters.'); return; }
            request('attempts/' + attemptId + '/report-question', 'POST', { questionId: item.questionId, reason: reason, details: details }).then(function () {
                window.alert('Question issue submitted for administrator review.');
            }).catch(function (e) { window.alert(e.message || 'Unable to report the question.'); });
        }));
    }

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
        var heartbeatId = null;
        var hadFullscreen = false;
        var clientId = (function () {
            var key = 'smcq-client-id-' + examId;
            try {
                var known = sessionStorage.getItem(key);
                if (known && /^[a-f0-9]{32}$/.test(known)) return known;
            } catch (e) { /* Private browsing may restrict storage. */ }
            if (!window.crypto || !window.crypto.getRandomValues) return '';
            var bytes = new Uint8Array(16);
            window.crypto.getRandomValues(bytes);
            var token = Array.prototype.map.call(bytes, function (n) { return ('0' + n.toString(16)).slice(-2); }).join('');
            try { sessionStorage.setItem(key, token); } catch (e) { /* Session valid for this page load. */ }
            return token;
        })();
        function playerRequest(path, method, data) { return request(path, method, data, clientId); }
        function stopHeartbeat() {
            if (heartbeatId !== null) clearInterval(heartbeatId);
            heartbeatId = null;
        }
        function startHeartbeat() {
            stopHeartbeat();
            if (!active || !active.security || !active.security.singleTab) return;
            heartbeatId = setInterval(async function () {
                if (!active || ended) return;
                try {
                    var response = await playerRequest('attempts/' + active.attemptId + '/ping', 'POST', {});
                    timeOffset = response.serverNow - Date.now();
                } catch (error) { if (error.code === 'smcq_session_conflict') handleConflict(error); }
            }, 45000);
        }
        function handleConflict(error) {
            active = null; ended = true; stopTimer(); stopHeartbeat(); clearBody();
            message(error.message || 'This exam is active in another tab.', true);
            body.appendChild(create('p', 'smcq-note', 'Only one tab can edit this attempt. You may continue here, which will close the other tab’s editing session.'));
            if (error.attemptId) {
                body.appendChild(makeButton('Continue this attempt here', 'smcq-button smcq-primary', async function () {
                    try {
                        await playerRequest('attempts/' + error.attemptId + '/takeover', 'POST', {});
                        await load();
                    } catch (err) { message(err.message, true); }
                }));
            }
            body.appendChild(makeButton('Reload Examination', 'smcq-button', load));
        }

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
                var state = await playerRequest('exams/' + examId + '/state');
                if (state.status === 'active') showAttempt(state);
                else showReady(state);
            } catch (error) { if (error.code === 'smcq_session_conflict') handleConflict(error); else message(error.message, true); }
        }
        function showReady(state) {
            active = null;
            ended = true;
            stopTimer(); stopHeartbeat();
            clearBody();
            body.appendChild(create('h2', 'smcq-exam-title', state.examTitle || 'Examination'));
            if (state.instructions) body.appendChild(create('p', 'smcq-exam-instructions', state.instructions));
            if (state.message) body.appendChild(create('p', 'smcq-note', state.message));
            if (state.security && state.security.monitor) body.appendChild(create('p', 'smcq-security-notice', 'Exam notice: tab visibility changes and fullscreen exits are logged for administrator review. These events do not automatically affect your score.'));
            if (state.security && state.security.singleTab) body.appendChild(create('p', 'smcq-security-notice', 'Only one browser tab or device may edit this attempt at a time.'));
            body.appendChild(create('p', 'smcq-meta', 'Duration: ' + state.duration + ' min · Attempts: ' + state.attemptsUsed + (state.attemptLimit ? ' / ' + state.attemptLimit : ' (unlimited)')));
            if (state.lastAttemptId) {
                body.appendChild(makeButton('View Latest Result', 'smcq-button smcq-result-link', function () {
                    viewResult(state.lastAttemptId);
                }));
            }
            if (state.canStart) {
                body.appendChild(makeButton('Start Examination', 'smcq-button smcq-primary', async function () {
                    if (busy) return;
                    busy = true;
                    message(settings.messages.loading);
                    try { showAttempt(await playerRequest('exams/' + examId + '/start', 'POST', {})); }
                    catch (error) { if (error.code === 'smcq_session_conflict') handleConflict(error); else message(error.message, true); }
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
            startHeartbeat();
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
        function addQuestionContext(target, question) {
            if (question.passage) target.appendChild(create('blockquote', 'smcq-passage', question.passage));
            if (question.media) {
                var kind = question.type === 'audio' ? 'audio' : 'image';
                var url;
                try { url = new URL(question.media, window.location.href); } catch (e) { return; }
                if (url.protocol !== 'https:' && url.protocol !== 'http:') return;
                var el = document.createElement(kind === 'audio' ? 'audio' : 'img');
                el.src = url.href;
                if (kind === 'audio') { el.controls = true; el.preload = 'none'; }
                else { el.alt = 'Question illustration'; el.loading = 'lazy'; }
                el.className = 'smcq-question-media';
                target.appendChild(el);
            }
        }
        function chosenKeys(answer) { return typeof answer === 'string' ? answer.split(',') : []; }
        function addReviewOptions(target, item) {
            var correct = chosenKeys(item.correctAnswer), yours = chosenKeys(item.yourAnswer);
            var list = create('ol', 'smcq-review-options');
            item.options.forEach(function (option) {
                var isCorrect = correct.indexOf(option.key) !== -1;
                var isChosen = yours.indexOf(option.key) !== -1;
                list.appendChild(create('li', 'smcq-review-option' + (isCorrect ? ' is-correct' : '') + (isChosen && !isCorrect ? ' is-wrong' : ''), option.label + '. ' + option.text + (isCorrect ? ' ✓ Correct' : '') + (isChosen ? ' · Your answer' : '')));
            });
            target.appendChild(list);
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
            if (active.security && active.security.monitor) body.appendChild(create('p', 'smcq-security-notice', 'Security monitoring enabled: leaving this tab is logged for review, not an automatic penalty.'));
            if (root.requestFullscreen) {
                header.appendChild(makeButton('Fullscreen', 'smcq-button smcq-fullscreen', function () {
                    root.requestFullscreen().catch(function () { message('Fullscreen mode is unavailable in this browser.', true); });
                }));
            }
            body.appendChild(create('p', 'smcq-progress', 'Question ' + (index + 1) + ' of ' + questions.length + ' · Answered: ' + answered));
            var form = create('fieldset', 'smcq-question');
            form.appendChild(create('legend', '', question.title));
            addQuestionContext(form, question);
            if (question.type === 'multiple') form.appendChild(create('p', 'smcq-note', 'Select all correct options, then select Save Selected Answers.'));
            question.options.forEach(function (option) {
                var label = create('label', 'smcq-option');
                var input = create('input');
                input.type = question.type === 'multiple' ? 'checkbox' : 'radio';
                input.name = 'smcq-choice-' + active.attemptId;
                input.value = option.key;
                input.checked = chosenKeys(active.answers[String(question.id)]).indexOf(option.key) !== -1;
                input.disabled = busy || unsaved;
                if (question.type !== 'multiple') input.addEventListener('change', function () { saveAnswer(question.id, option.key); });
                label.appendChild(input);
                label.appendChild(create('span', '', option.label + '. ' + option.text));
                form.appendChild(label);
            });
            if (question.type === 'multiple') {
                form.appendChild(makeButton('Save Selected Answers', 'smcq-button smcq-primary', function () {
                    var selected = Array.prototype.slice.call(form.querySelectorAll('input[type="checkbox"]:checked')).map(function (input) { return input.value; });
                    selected.sort();
                    saveAnswer(question.id, selected.length ? selected.join(',') : null);
                }));
            }
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
                var result = await playerRequest('attempts/' + active.attemptId + '/answer', 'POST', {
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
                if (error.code === 'smcq_session_conflict') handleConflict(error);
                else if (error.status === 409) { stopTimer(); stopHeartbeat(); ended = true; body.appendChild(makeButton('Reload Attempt', 'smcq-button', load)); }
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
                var result = await playerRequest('attempts/' + active.attemptId + '/submit', 'POST', {});
                showFinished(result);
            } catch (error) { if (error.code === 'smcq_session_conflict') handleConflict(error); else message(error.message, true); }
            finally { busy = false; }
        }
        function gradeCard(result) {
            var container = create('div', 'smcq-result');
            if (!result || !result.grade) {
                container.appendChild(create('p', 'smcq-error', 'This result is temporarily unavailable. Please reload.'));
                return container;
            }
            var grade = result.grade;
            container.appendChild(create('h2', 'smcq-exam-title', result.examTitle || 'Exam Result'));
            container.appendChild(create('div', 'smcq-result-status ' + (grade.passed ? 'smcq-pass' : 'smcq-fail'),
                (grade.passed ? 'PASSED' : 'NOT PASSED') + ' · ' + grade.score + ' / ' + grade.maxMarks + ' marks'));
            var summary = create('dl', 'smcq-result-grid');
            [
                ['Score percentage', grade.percentage + '%'],
                ['Passing percentage', grade.passingPercentage + '%'],
                ['Correct answers', grade.correct],
                ['Wrong answers', grade.wrong],
                ['Unanswered', grade.unanswered],
                ['Accuracy (of attempted)', grade.accuracy + '%'],
                ['Positive marks', grade.positiveMarks],
                ['Negative deduction', '−' + grade.negativeMarks],
                ['Completion', result.completionReason === 'timeout' ? 'Time expired' : 'Submitted']
            ].forEach(function (entry) {
                summary.appendChild(create('dt', '', entry[0]));
                summary.appendChild(create('dd', '', entry[1]));
            });
            container.appendChild(summary);
            if (result.review && result.review.length) {
                container.appendChild(create('h3', '', 'Detailed Answer Review'));
                result.review.forEach(function (item, idx) {
                    var details = create('details', 'smcq-review smcq-review-' + item.status);
                    var heading = create('summary', '', 'Q' + (idx + 1) + ': ' + item.title + ' — ' + item.status + ' (' + item.earnedMarks + ' marks)');
                    details.appendChild(heading);
                    addQuestionContext(details, item);
                    addReviewOptions(details, item);
                    if (!item.yourAnswer) details.appendChild(create('p', '', 'You did not answer this question.'));
                    if (item.explanation) details.appendChild(create('p', 'smcq-explanation', 'Explanation: ' + item.explanation));
                    addPracticeBookmark(details, item);
                    addIssueReport(details, item, result.attemptId);
                    container.appendChild(details);
                });
            }
            return container;
        }
        async function viewResult(attemptId) {
            ended = true;
            active = null;
            stopTimer(); stopHeartbeat();
            clearBody();
            message('Loading result…');
            try {
                showFinished(await playerRequest('attempts/' + attemptId + '/result'));
            } catch (error) {
                message(error.message, true);
                body.appendChild(makeButton('Back to Exam', 'smcq-button', load));
            }
        }
        function showFinished(result) {
            ended = true;
            active = null;
            unsaved = false;
            stopTimer(); stopHeartbeat();
            clearBody();
            if (result && result.grade) {
                body.appendChild(gradeCard(result));
                message(result.completionReason === 'timeout' ? settings.messages.expired : settings.messages.finished);
            } else {
                body.appendChild(create('h2', '', 'Examination submitted'));
                body.appendChild(create('p', 'smcq-note', 'Your attempt was recorded.'));
                if (result && result.attemptId) {
                    body.appendChild(makeButton('Load Result', 'smcq-button', function () { viewResult(result.attemptId); }));
                }
                message('Attempt recorded.');
            }
            body.appendChild(makeButton('Back to Exam', 'smcq-button', load));
        }
        document.addEventListener('visibilitychange', function () {
            if (!active || ended || !active.security || !active.security.monitor) return;
            playerRequest('attempts/' + active.attemptId + '/activity', 'POST', { event: document.hidden ? 'tab_hidden' : 'tab_visible' }).catch(function () {});
        });
        document.addEventListener('fullscreenchange', function () {
            var nowFullscreen = document.fullscreenElement === root;
            if (hadFullscreen && !nowFullscreen && active && !ended && active.security && active.security.monitor) {
                playerRequest('attempts/' + active.attemptId + '/activity', 'POST', { event: 'fullscreen_exit' }).catch(function () {});
            }
            hadFullscreen = nowFullscreen;
        });
        window.addEventListener('beforeunload', function (event) {
            if (unsaved) { event.preventDefault(); event.returnValue = settings.messages.unsaved; }
        });
        load();
    });

    document.querySelectorAll('.smcq-history').forEach(function (root) {
        var body = root.querySelector('.smcq-history-body');
        var page = 1;
        function clear() { while (body.firstChild) body.removeChild(body.firstChild); }
        function resultDetails(result) {
            var g = result.grade;
            clear();
            body.appendChild(create('h3', '', result.examTitle || 'Exam Result'));
            body.appendChild(create('p', g.passed ? 'smcq-pass smcq-history-summary' : 'smcq-fail smcq-history-summary',
                (g.passed ? 'PASSED' : 'NOT PASSED') + ' — ' + g.score + '/' + g.maxMarks + ' marks (' + g.percentage + '%)'));
            body.appendChild(create('p', '', 'Correct: ' + g.correct + ' · Wrong: ' + g.wrong + ' · Unanswered: ' + g.unanswered + ' · Accuracy: ' + g.accuracy + '%'));
            (result.review || []).forEach(function (item, i) {
                var box = create('details', 'smcq-review smcq-review-' + item.status);
                box.appendChild(create('summary', '', (i + 1) + '. ' + item.title + ' — ' + item.status));
                addQuestionContext(box, item);
                addReviewOptions(box, item);
                if (item.explanation) box.appendChild(create('p', 'smcq-explanation', 'Explanation: ' + item.explanation));
                addPracticeBookmark(box, item);
                addIssueReport(box, item, result.attemptId);
                body.appendChild(box);
            });
            body.appendChild(makeButton('← Exam History', 'smcq-button', function () { loadHistory(page); }));
        }
        function prettyDate(sql) {
            if (!sql) return '—';
            var time = new Date(sql.replace(' ', 'T') + 'Z');
            return isNaN(time.getTime()) ? sql : time.toLocaleString();
        }
        async function loadHistory(number) {
            page = number;
            clear();
            body.appendChild(create('p', '', 'Loading examination history…'));
            try {
                var data = await request('history?page=' + page);
                clear();
                if (!data.entries.length) body.appendChild(create('p', '', 'No completed examinations found.'));
                data.entries.forEach(function (item) {
                    var card = create('div', 'smcq-history-entry');
                    card.appendChild(create('strong', '', item.examTitle || ('Exam #' + item.examId)));
                    card.appendChild(create('p', '', prettyDate(item.submittedAt) + ' · ' +
                        (item.grade ? item.grade.score + '/' + item.grade.maxMarks + ' marks · ' + (item.grade.passed ? 'Passed' : 'Not passed') : 'Grade unavailable')));
                    card.appendChild(makeButton('View Result & Answers', 'smcq-button', async function () {
                        clear();
                        body.appendChild(create('p', '', 'Loading result…'));
                        try { resultDetails(await request('attempts/' + item.attemptId + '/result')); }
                        catch (error) { clear(); body.appendChild(create('p', 'smcq-error', error.message)); body.appendChild(makeButton('Back to History', 'smcq-button', function () { loadHistory(page); })); }
                    }));
                    body.appendChild(card);
                });
                var pager = create('div', 'smcq-history-pager');
                if (page > 1) pager.appendChild(makeButton('← Previous', 'smcq-button', function () { loadHistory(page - 1); }));
                if (data.hasMore) pager.appendChild(makeButton('Next →', 'smcq-button', function () { loadHistory(page + 1); }));
                body.appendChild(pager);
            } catch (error) { clear(); body.appendChild(create('p', 'smcq-error', error.message)); }
        }
        loadHistory(1);
    });
})();