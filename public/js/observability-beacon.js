(function () {
  'use strict';

  if (window.__khoddamObservabilityBeacon) {
    return;
  }
  window.__khoddamObservabilityBeacon = true;

  var endpoint = '/observability/client-errors';
  var throttleMs = 2000;
  var lastSent = 0;
  var queue = [];
  var flushing = false;

  function csrfToken() {
    var meta = document.querySelector('meta[name="csrf-token"]');
    return meta ? meta.getAttribute('content') : '';
  }

  function enqueue(payload) {
    if (queue.length > 20) {
      queue.shift();
    }
    queue.push(payload);
    flush();
  }

  function flush() {
    if (flushing || queue.length === 0) {
      return;
    }
    var now = Date.now();
    if (now - lastSent < throttleMs) {
      setTimeout(flush, throttleMs - (now - lastSent));
      return;
    }
    flushing = true;
    var payload = queue.shift();
    lastSent = now;

    try {
      fetch(endpoint, {
        method: 'POST',
        credentials: 'same-origin',
        headers: {
          'Content-Type': 'application/json',
          'Accept': 'application/json',
          'X-CSRF-TOKEN': csrfToken(),
          'X-Requested-With': 'XMLHttpRequest'
        },
        body: JSON.stringify(payload)
      }).catch(function () {}).finally(function () {
        flushing = false;
        if (queue.length) {
          setTimeout(flush, throttleMs);
        }
      });
    } catch (e) {
      flushing = false;
    }
  }

  window.addEventListener('error', function (event) {
    enqueue({
      message: (event && event.message) ? String(event.message).slice(0, 2000) : 'Unknown frontend error',
      source: event && event.filename ? String(event.filename).slice(0, 500) : null,
      lineno: event ? event.lineno : null,
      colno: event ? event.colno : null,
      stack: event && event.error && event.error.stack ? String(event.error.stack).slice(0, 4000) : null,
      type: 'window.onerror',
      url: window.location.href
    });
  });

  window.addEventListener('unhandledrejection', function (event) {
    var reason = event && event.reason;
    var message = 'Unhandled promise rejection';
    var stack = null;
    if (typeof reason === 'string') {
      message = reason;
    } else if (reason && reason.message) {
      message = String(reason.message);
      stack = reason.stack ? String(reason.stack) : null;
    }
    enqueue({
      message: message.slice(0, 2000),
      stack: stack ? stack.slice(0, 4000) : null,
      type: 'unhandledrejection',
      url: window.location.href
    });
  });
})();
