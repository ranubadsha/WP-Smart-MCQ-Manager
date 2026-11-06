/* global smcqExam */
(function () {
  'use strict';
  var builder = document.getElementById('smcq-exam-question-builder');
  if (!builder || typeof smcqExam === 'undefined') return;
  var input = document.getElementById('smcq_selected_ids');
  var list = document.getElementById('smcq_selected_list');
  var count = document.getElementById('smcq_question_count');
  var search = document.getElementById('smcq_question_search');
  var results = document.getElementById('smcq_question_search_results');
  var max = smcqExam.max || 200;
  var pending = null;
  var debounce = null;

  function ids() {
    return input.value.split(/[\s,;]+/).map(function (s) { return s.trim(); })
      .filter(function (s) { return /^\d+$/.test(s) && Number(s) > 0; })
      .filter(function (s, i, all) { return all.indexOf(s) === i; }).slice(0, max);
  }
  function syncCount() { count.textContent = String(ids().length); }
  function reconcile() {
    var found = ids();
    Array.prototype.slice.call(list.querySelectorAll('li[data-id]')).forEach(function (li) {
      if (found.indexOf(li.getAttribute('data-id')) < 0) li.remove();
    });
    syncCount();
  }
  function addQuestion(id, title) {
    id = String(id);
    var current = ids();
    if (current.indexOf(id) !== -1 || current.length >= max) return;
    current.push(id);
    input.value = current.join(',');
    var li = document.createElement('li');
    li.setAttribute('data-id', id);
    var label = document.createElement('span');
    label.textContent = 'Q-' + id + ': ' + title;
    li.appendChild(label);
    li.appendChild(document.createTextNode(' '));
    var remove = document.createElement('button');
    remove.type = 'button';
    remove.className = 'button-link smcq-remove-question';
    remove.textContent = 'Remove';
    li.appendChild(remove);
    list.appendChild(li);
    syncCount();
  }
  list.addEventListener('click', function (event) {
    if (!event.target.classList.contains('smcq-remove-question')) return;
    var li = event.target.closest('li[data-id]');
    if (!li) return;
    var id = li.getAttribute('data-id');
    input.value = ids().filter(function (item) { return item !== id; }).join(',');
    li.remove();
    syncCount();
  });
  input.addEventListener('input', reconcile);

  search.addEventListener('input', function () {
    clearTimeout(debounce);
    if (pending) pending.abort();
    var query = search.value.trim();
    results.textContent = '';
    if (!query) return;
    debounce = setTimeout(function () {
      pending = new XMLHttpRequest();
      var params = new URLSearchParams({
        action: 'smcq_exam_search_questions', nonce: smcqExam.nonce,
        exam_id: String(smcqExam.examId || 0), term: query
      });
      pending.open('GET', smcqExam.ajaxUrl + '?' + params.toString(), true);
      pending.onreadystatechange = function () {
        if (!pending || pending.readyState !== 4) return;
        var response;
        try { response = JSON.parse(pending.responseText); } catch (e) { response = null; }
        results.textContent = '';
        if (!response || !response.success) {
          results.textContent = smcqExam.error;
          return;
        }
        if (!response.data.length) { results.textContent = smcqExam.empty; return; }
        response.data.forEach(function (item) {
          var button = document.createElement('button');
          button.type = 'button';
          button.className = 'button smcq-add-question';
          button.textContent = 'Q-' + item.id + ': ' + item.title;
          button.addEventListener('click', function () { addQuestion(item.id, item.title); });
          results.appendChild(button);
        });
      };
      pending.send();
    }, 300);
  });
  syncCount();
}());