/**
 * Fills in the per-branch availability block of the search result list with
 * a single AJAX request for all records on the page. If the request fails, the
 * placeholders are removed and the hits are shown without availability.
 */
(function lotsBranchAvailability() {
  'use strict';

  var TIMEOUT_MS = 20000;

  function done() {
    var name = 'lots:branch-availability-done';
    var evt;
    if (typeof Event === 'function') {
      evt = new Event(name);
    } else {
      evt = document.createEvent('Event');
      evt.initEvent(name, true, true);
    }
    document.dispatchEvent(evt);
  }

  function removeAll(placeholders) {
    placeholders.forEach(function remove(el) {
      if (el.parentNode) {
        el.parentNode.removeChild(el);
      }
    });
  }

  function fill(placeholders, availability) {
    placeholders.forEach(function replace(el) {
      var html = availability[el.getAttribute('data-id')];
      if (typeof html === 'string' && html && el.parentNode) {
        el.insertAdjacentHTML('beforebegin', html);
      }
    });
    removeAll(placeholders);
  }

  function load() {
    var placeholders = Array.prototype.slice.call(
      document.querySelectorAll('.lots-branch-availability:not([data-state])')
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
      var availability = {};
      try {
        availability = JSON.parse(xhr.responseText).data.availability || {};
      } catch (e) {
        availability = {};
      }
      fill(placeholders, availability);
      done();
    };
    xhr.onerror = xhr.ontimeout = function onfail() {
      removeAll(placeholders);
      done();
    };
    xhr.send(body);
  }

  if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', load);
  } else {
    load();
  }
}());
