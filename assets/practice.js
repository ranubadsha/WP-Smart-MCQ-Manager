/* Step 8: Private, progressive revision. Never embed or infer secret answer keys. */
(function () {
    'use strict';
    var settings = window.smcqPracticeSettings;
    if (!settings) return;
    function el(tag, klass, text) {
        var node = document.createElement(tag);
        if (klass) node.className = klass;
        if (text !== undefined) node.textContent = String(text);
        return node;
    }
    function button(label, action, cls) {
        var node = el('button', cls || 'smcq-practice-button', label);
        node.type = 'button';
        node.addEventListener('click', action);
        return node;
    }
    async function api(path, method, payload) {
        var init = { method: method || 'GET', credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': settings.nonce } };
        if (payload !== undefined) {
            init.headers['Content-Type'] = 'application/json';
            init.body = JSON.stringify(payload);
        }
        var response = await fetch(settings.root + path, init);
        var result;
        try { result = await response.json(); } catch (error) { result = {}; }
        if (!response.ok) {
            var err = new Error(result.message || 'The request failed. Please retry.');
            err.status = response.status;
            throw err;
        }
        return result;
    }
    var labels = { wrong: 'Retry Wrong Answers', bookmarks: 'Bookmarked Questions', weak: 'Weak Topics', mixed: 'Mixed Revision', daily: 'Daily Quiz', weekly: 'Weekly Quiz', custom: 'Custom Topic Practice' };
    document.querySelectorAll('.smcq-practice').forEach(function (root) {
        var notice = root.querySelector('.smcq-practice-message');
        var body = root.querySelector('.smcq-practice-body');
        var current = null;
        var currentIndex = 0;
        var busy = false;
        var savedBookmarks = {};
        function message(txt, error) {
            notice.textContent = txt || '';
            notice.classList.toggle('smcq-practice-error', !!error);
        }
        function clear() { body.replaceChildren(); }
        function info() {
            if (busy) return;
            current = null;
            message('Loading your revision dashboard…');
            clear();
            api('practice/overview').then(showOverview).catch(function (error) { message(error.message, true); });
        }
        function showOverview(summary) {
            savedBookmarks = {};
            (summary.bookmarkIds || []).forEach(function (id) { savedBookmarks[id] = true; });
            clear();
            message('Choose a mode to start or resume personal revision.');
            var intro = el('div', 'smcq-practice-cards');
            intro.appendChild(el('div', 'smcq-practice-card', 'Historical wrong answers: ' + summary.wrong));
            intro.appendChild(el('div', 'smcq-practice-card', 'Bookmarks: ' + summary.bookmarks));
            intro.appendChild(el('div', 'smcq-practice-card', 'Weak topics: ' + (summary.weakTopics || []).length));
            body.appendChild(intro);
            if (summary.weakTopics && summary.weakTopics.length) {
                var section = el('div', 'smcq-practice-panel');
                section.appendChild(el('h3', '', 'Topics to Revise'));
                var ul = el('ul');
                summary.weakTopics.slice(0, 5).forEach(function (item) {
                    ul.appendChild(el('li', '', item.name + ' — ' + item.accuracy + '% accuracy from ' + item.attempted + ' answered questions'));
                });
                section.appendChild(ul);
                body.appendChild(section);
            }
            var form = el('div', 'smcq-practice-panel');
            form.appendChild(el('h3', '', 'Create Practice Set'));
            var countLabel = el('label', 'smcq-practice-count', 'Questions per session (1–20): ');
            var count = el('input');
            count.type = 'number'; count.min = '1'; count.max = '20'; count.value = '10';
            countLabel.appendChild(count);
            form.appendChild(countLabel);
            var subjectLabel = el('label', 'smcq-practice-count', 'Subject for Custom Practice: ');
            var subjectInput = el('select');
            subjectInput.appendChild(new Option('Any subject', '0'));
            (summary.subjects || []).forEach(function (s) { subjectInput.appendChild(new Option(s.name, String(s.id))); });
            subjectLabel.appendChild(subjectInput); form.appendChild(subjectLabel);
            var difficultyLabel = el('label', 'smcq-practice-count', 'Difficulty: ');
            var difficultyInput = el('select');
            ['Any', 'easy', 'medium', 'hard'].forEach(function (v) { difficultyInput.appendChild(new Option(v === 'Any' ? 'Any difficulty' : v, v === 'Any' ? '' : v)); });
            difficultyLabel.appendChild(difficultyInput); form.appendChild(difficultyLabel);
            var modes = el('div', 'smcq-practice-modes');
            Object.keys(labels).forEach(function (mode) {
                modes.appendChild(button(labels[mode], async function () {
                    if (busy) return;
                    var n = Number(count.value);
                    if (!Number.isInteger(n) || n < 1 || n > 20) { message('Choose between 1 and 20 questions.', true); return; }
                    busy = true;
                    message('Preparing ' + labels[mode] + '…');
                    try { showSession(await api('practice/start', 'POST', { mode: mode, count: n, subject: mode === 'custom' ? Number(subjectInput.value) : 0, difficulty: mode === 'custom' ? difficultyInput.value : '' })); }
                    catch (error) { message(error.message, true); }
                    finally { busy = false; }
                }));
            });
            form.appendChild(modes);
            body.appendChild(form);
            body.appendChild(el('p', 'smcq-practice-note', 'Only questions explicitly released by an administrator can be practiced. Wrong-answer mode uses your most recently answered status in the last ' + summary.sampleLimit + ' exam attempts.'));
            var resumes = el('div', 'smcq-practice-panel');
            resumes.appendChild(el('h3', '', 'Resume an Unfinished Session'));
            Object.keys(labels).forEach(function (mode) {
                resumes.appendChild(button('Resume ' + labels[mode], async function () {
                    if (busy) return;
                    busy = true;
                    try { showSession(await api('practice/state?mode=' + encodeURIComponent(mode))); }
                    catch (error) { message(error.status === 404 ? 'No unfinished session found for this mode.' : error.message, error.status !== 404); }
                    finally { busy = false; }
                }, 'smcq-practice-button smcq-secondary'));
            });
            body.appendChild(resumes);
            if (summary.trend && summary.trend.length) {
                var table = el('div', 'smcq-practice-panel');
                table.appendChild(el('h3', '', 'Recent Practice Progress'));
                var list = el('ol', 'smcq-practice-trend');
                summary.trend.forEach(function (row) {
                    list.appendChild(el('li', '', labels[row.mode] + ': ' + row.correct + '/' + row.total + ' correct (' + row.accuracy + '% of attempted)'));
                });
                table.appendChild(list);
                body.appendChild(table);
            }
        }
        function showSession(data) {
            current = data;
            var questions = current.questions || [];
            var unattempted = questions.findIndex(function (q) { return !Object.prototype.hasOwnProperty.call(current.answers || {}, String(q.id)); });
            currentIndex = unattempted < 0 ? 0 : unattempted;
            render();
        }
        function render() {
            if (!current) return;
            clear();
            var questions = current.questions || [];
            if (!questions.length) { message('This session has no available questions.', true); return; }
            var answered = Object.keys(current.answers || {}).length;
            message(labels[current.mode] + ' · ' + answered + ' / ' + questions.length + ' answered' + (current.status === 'completed' ? ' · Completed' : ''));
            var header = el('div', 'smcq-practice-controls');
            header.appendChild(button('← Practice Menu', info, 'smcq-practice-button smcq-secondary'));
            if (current.status === 'active') {
                header.appendChild(button('Finish Session', async function () {
                    if (busy || !window.confirm('Finish practice? Unanswered questions will count as skipped.')) return;
                    busy = true;
                    try { showSession(await api('practice/finish', 'POST', { sessionId: current.sessionId })); }
                    catch (error) { message(error.message, true); }
                    finally { busy = false; }
                }, 'smcq-practice-button'));
            }
            body.appendChild(header);
            if (current.grade) {
                var grade = current.grade;
                body.appendChild(el('div', 'smcq-practice-grade', 'Session finished: ' + grade.correct + ' correct, ' + grade.wrong + ' wrong, ' + grade.unanswered + ' skipped · ' + grade.accuracy + '% accuracy'));
                body.appendChild(button('Start Another Practice', info, 'smcq-practice-button'));
            }
            var nav = el('div', 'smcq-practice-nav');
            questions.forEach(function (q, i) {
                var answeredQ = Object.prototype.hasOwnProperty.call(current.answers || {}, String(q.id));
                var btn = button(String(i + 1), function () { currentIndex = i; render(); }, 'smcq-practice-number' + (answeredQ ? ' is-answered' : '') + (i === currentIndex ? ' is-current' : ''));
                btn.setAttribute('aria-label', 'Go to question ' + (i + 1) + (answeredQ ? ', answered' : ', unanswered'));
                nav.appendChild(btn);
            });
            body.appendChild(nav);
            var question = questions[currentIndex];
            var feedback = (current.feedback || {})[String(question.id)] || null;
            var card = el('article', 'smcq-practice-question');
            card.appendChild(el('h3', '', 'Question ' + (currentIndex + 1) + ' of ' + questions.length));
            card.appendChild(el('p', 'smcq-practice-title', question.title));
            if (question.passage) card.appendChild(el('blockquote', 'smcq-passage', question.passage));
            if (question.media) {
                try {
                    var mediaUrl = new URL(question.media, window.location.href);
                    if (mediaUrl.protocol === 'https:' || mediaUrl.protocol === 'http:') {
                        var media = document.createElement(question.type === 'audio' ? 'audio' : 'img');
                        media.src = mediaUrl.href; media.className = 'smcq-question-media';
                        if (question.type === 'audio') { media.controls = true; media.preload = 'none'; }
                        else { media.alt = 'Question illustration'; media.loading = 'lazy'; }
                        card.appendChild(media);
                    }
                } catch (e) { /* Invalid media URL is ignored. */ }
            }
            var form = el('div', 'smcq-practice-options');
            var multi = question.type === 'multiple';
            var selected = [];
            function includesKey(answer, key) { return typeof answer === 'string' && answer.split(',').indexOf(key) !== -1; }
            async function submitPractice(choice) {
                if (busy || current.status !== 'active' || feedback) return;
                busy = true; message('Saving your answer…');
                try {
                    var updated = await api('practice/answer', 'POST', { sessionId: current.sessionId, questionId: question.id, choice: choice, version: current.version });
                    current = updated; render();
                } catch (error) {
                    message(error.message, true);
                    if (error.status === 409) body.appendChild(button('Reload Latest Session', async function () { try { showSession(await api('practice/state?id=' + current.sessionId)); } catch (err) { message(err.message, true); } }));
                } finally { busy = false; }
            }
            question.options.forEach(function (opt) {
                var text = opt.label + '. ' + opt.text;
                var key = opt.key;
                var klass = 'smcq-practice-option';
                if (feedback && includesKey(feedback.correctAnswer, key)) klass += ' is-correct';
                if (feedback && includesKey(feedback.yourAnswer, key) && !includesKey(feedback.correctAnswer, key)) klass += ' is-wrong';
                var b = button(text, function () {
                    if (multi) {
                        if (selected.indexOf(key) !== -1) selected.splice(selected.indexOf(key), 1);
                        else selected.push(key);
                        b.classList.toggle('is-selected', selected.indexOf(key) !== -1);
                    } else submitPractice(key);
                }, klass);
                if (feedback || current.status !== 'active') b.disabled = true;
                form.appendChild(b);
            });
            if (multi && !feedback && current.status === 'active') {
                form.appendChild(button('Submit Selected Answers', function () {
                    if (!selected.length) { message('Choose at least one answer.', true); return; }
                    submitPractice(selected.sort().join(','));
                }, 'smcq-practice-button'));
            }
            card.appendChild(form);
            if (feedback) {
                card.appendChild(el('p', feedback.status === 'correct' ? 'smcq-practice-feedback is-correct' : 'smcq-practice-feedback', (feedback.status === 'correct' ? 'Correct!' : feedback.status === 'wrong' ? 'Not quite.' : 'Skipped.') + ' Correct choice: ' + feedback.correctAnswer));
                if (feedback.explanation) card.appendChild(el('p', 'smcq-practice-explanation', feedback.explanation));
            }
            var bookmarkControl = button(savedBookmarks[question.id] ? '★ Bookmarked' : '☆ Bookmark Question', async function () {
                if (busy) return;
                busy = true;
                try {
                    var add = !savedBookmarks[question.id];
                    await api('practice/bookmarks/' + question.id, add ? 'POST' : 'DELETE');
                    savedBookmarks[question.id] = add;
                    render();
                } catch (error) { message(error.message, true); }
                finally { busy = false; }
            }, 'smcq-practice-button smcq-secondary');
            card.appendChild(bookmarkControl);
            body.appendChild(card);
            var footer = el('div', 'smcq-practice-controls');
            if (currentIndex > 0) footer.appendChild(button('← Previous', function () { --currentIndex; render(); }, 'smcq-practice-button smcq-secondary'));
            if (currentIndex < questions.length - 1) footer.appendChild(button('Next →', function () { ++currentIndex; render(); }, 'smcq-practice-button smcq-secondary'));
            body.appendChild(footer);
        }
        info();
    });
}());