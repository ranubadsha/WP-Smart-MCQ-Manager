/* Step 7: owner-scoped analytics. Uses DOM text only, never trusted HTML. */
(function () {
    'use strict';
    if (!window.smcqAnalytics) return;
    var config = window.smcqAnalytics;
    function el(tag, cls, text) {
        var node = document.createElement(tag);
        if (cls) node.className = cls;
        if (text !== undefined) node.textContent = String(text);
        return node;
    }
    function pct(n) { return Math.max(0, Math.min(100, Number(n) || 0)); }
    function addStat(parent, name, value) {
        var card = el('div', 'smcq-stat-card');
        card.appendChild(el('span', '', name));
        card.appendChild(el('strong', '', value));
        parent.appendChild(card);
    }
    function section(parent, title, note) {
        var panel = el('section', 'smcq-analytics-panel');
        panel.appendChild(el('h3', '', title));
        if (note) panel.appendChild(el('p', 'smcq-muted', note));
        parent.appendChild(panel);
        return panel;
    }
    function groups(parent, title, data) {
        var panel = section(parent, title, 'Accuracy = correct / attempted. Low-sample topics are not reliable indicators of weakness.');
        if (!data || !data.length) {
            panel.appendChild(el('p', 'smcq-muted', 'No categorized questions in your saved attempts yet. New Step 7 attempts retain their original subject and topic labels.'));
            return;
        }
        data.forEach(function (row) {
            var item = el('div', 'smcq-topic-row');
            item.appendChild(el('div', 'smcq-topic-label', row.name));
            var track = el('div', 'smcq-progress-track');
            var bar = el('div', 'smcq-progress-fill');
            bar.style.width = pct(row.accuracy) + '%';
            track.appendChild(bar);
            item.appendChild(track);
            var label = row.accuracy === null ? 'No answers' : Number(row.accuracy).toFixed(1) + '%';
            var value = el('span', 'smcq-topic-value', label);
            value.title = row.attempted + ' answered · ' + row.correct + ' correct · ' + row.skipped + ' skipped' + (row.lowSample ? ' · low sample' : '');
            item.appendChild(value);
            panel.appendChild(item);
        });
    }
    function weaknesses(parent, rows) {
        var eligible = (rows || []).filter(function (row) { return !row.lowSample && row.attempted >= 3 && row.accuracy !== null; });
        eligible.sort(function (a, b) { return Number(a.accuracy) - Number(b.accuracy) || Number(b.attempted) - Number(a.attempted); });
        var panel = section(parent, 'Topics to review', 'Lowest accuracy among topics with at least 3 answered questions. This is a practice suggestion, not a definitive assessment.');
        if (!eligible.length) {
            panel.appendChild(el('p', 'smcq-muted', 'Answer at least 3 questions in a topic to receive topic suggestions.'));
            return;
        }
        eligible.slice(0, 5).forEach(function (row) {
            panel.appendChild(el('p', '', row.name + ' — ' + Number(row.accuracy).toFixed(1) + '% (' + row.attempted + ' answered)'));
        });
    }
    function trend(parent, rows) {
        var panel = section(parent, 'Recent score trend', 'Last ' + rows.length + ' completed attempts (oldest to newest).');
        if (!rows.length) { panel.appendChild(el('p', 'smcq-muted', 'Complete an examination to see your progress.')); return; }
        var chart = el('div', 'smcq-bars');
        chart.setAttribute('role', 'list');
        rows.forEach(function (row, index) {
            var bar = el('div', 'smcq-bar');
            var y = pct(row.percentage);
            var label = 'Attempt ' + (index + 1) + ': ' + Number(row.percentage).toFixed(1) + '%';
            bar.setAttribute('role', 'listitem');
            bar.setAttribute('tabindex', '0');
            bar.setAttribute('aria-label', label);
            bar.title = label;
            var fill = el('span', 'smcq-bar-fill');
            fill.style.height = y + '%';
            bar.appendChild(fill);
            chart.appendChild(bar);
        });
        panel.appendChild(chart);
    }
    document.querySelectorAll('.smcq-dashboard .smcq-dashboard-content').forEach(function (container) {
        container.setAttribute('aria-busy', 'true');
        fetch(config.url, { credentials: 'same-origin', cache: 'no-store', headers: { 'X-WP-Nonce': config.nonce } })
            .then(function (r) { if (!r.ok) throw new Error('Request failed'); return r.json(); })
            .then(function (data) {
                container.replaceChildren();
                var cards = el('div', 'smcq-stat-cards');
                addStat(cards, 'Completed attempts', data.totalAttempts);
                addStat(cards, 'Average score', Number(data.averagePercentage).toFixed(1) + '%');
                addStat(cards, 'Passed attempts', data.passedAttempts);
                addStat(cards, 'Correct answers', data.correct);
                addStat(cards, 'Wrong answers', data.wrong);
                addStat(cards, 'Skipped questions', data.unanswered);
                container.appendChild(cards);
                if (data.totalAttempts > data.sampledAttempts) {
                    container.appendChild(el('p', 'smcq-muted', 'Statistics use your latest ' + data.sampledAttempts + ' attempts out of ' + data.totalAttempts + '.'));
                }
                trend(container, data.trend || []);
                groups(container, 'Subject performance', data.subjects || []);
                groups(container, 'Topic performance', data.topics || []);
                weaknesses(container, data.topics || []);
            })
            .catch(function () { container.textContent = config.error; })
            .finally(function () { container.setAttribute('aria-busy', 'false'); });
    });
}());