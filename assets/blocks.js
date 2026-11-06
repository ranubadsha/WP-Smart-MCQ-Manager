/* No compiled dependencies: dynamically rendered Gutenberg blocks. */
(function (wp) {
    'use strict';
    if (!wp || !wp.blocks || !wp.element || !wp.components || !wp.blockEditor) return;
    var el = wp.element.createElement;
    var types = [
        { name: 'smcq/exam', title: 'Smart MCQ Exam', desc: 'Show an examination by ID.' },
        { name: 'smcq/dashboard', title: 'Smart MCQ Dashboard', desc: 'Student performance dashboard.' },
        { name: 'smcq/practice', title: 'Smart MCQ Practice', desc: 'Personal revision exercises.' },
        { name: 'smcq/history', title: 'Smart MCQ History', desc: 'Previous examination attempts.' }
    ];
    types.forEach(function (t) {
        wp.blocks.registerBlockType(t.name, {
            apiVersion: 2, title: t.title, icon: 'welcome-learn-more', category: 'widgets',
            attributes: t.name === 'smcq/exam' ? { examId: { type: 'number', default: 0 } } : {},
            edit: function (props) {
                if (t.name !== 'smcq/exam') return el('div', { className: 'components-placeholder' }, t.title + ' — ' + t.desc + ' (preview on published page)');
                return el('div', { className: 'components-placeholder' },
                    el('strong', {}, t.title),
                    el(wp.components.TextControl, {
                        label: 'Examination ID', type: 'number', min: 1,
                        value: props.attributes.examId || '',
                        onChange: function (v) { props.setAttributes({ examId: Math.max(0, parseInt(v, 10) || 0) }); }
                    })
                );
            },
            save: function () { return null; }
        });
    });
}(window.wp));