/**
 * Fills in the title hold links of the search result list with a single AJAX
 * request for all records on the page. If the request fails, the placeholders
 * are removed and the hits are shown without a hold link.
 */
(function lotsTitleHolds() {
  'use strict';

  var TIMEOUT_MS = 20000;

  function removeAll(placeholders) {
    placeholders.forEach(function remove(el) {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    });
  }

  function fill(placeholders, holds) {
    placeholders.forEach(function replace(el) {
      var html = holds[el.getAttribute('data-id')];
      if (typeof html === 'string' && html && el.parentNode) {
        el.insertAdjacentHTML('beforebegin', html);
      }
    });
    removeAll(placeholders);
  }

  function load() {
    var placeholders = Array.prototype.slice.call(
      document.querySelectorAll('.lots-title-hold:not([data-state])')
    );
    if (!placeholders.length) {
      return;
    }
    var ids = [];
    placeholders.forEach(function mark(el) {
      el.setAttribute('data-state', 'loading');
      var id = el.getAttribute('data-id');
      if (ids.indexOf(id) === -1) {
        ids.push(id);
      }
    });

    var body = ids.map(function encode(id) {
      return 'id[]=' + encodeURIComponent(id);
    }).join('&');

    var xhr = new XMLHttpRequest();
    xhr.open('POST', placeholders[0].getAttribute('data-url'), true);
    xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
    xhr.timeout = TIMEOUT_MS;
    xhr.onload = function onload() {
      var holds = {};
      try {
        holds = JSON.parse(xhr.responseText).data.holds || {};
      } catch (e) {
        holds = {};
      }
      fill(placeholders, holds);
    };
    xhr.onerror = xhr.ontimeout = function onfail() {
      removeAll(placeholders);
    };
    xhr.send(body);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
}());
