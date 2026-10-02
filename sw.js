/* Minimal service worker: makes the site installable.
   It deliberately caches NOTHING, so verification results and scan counts are always live. */
self.addEventListener('install', function () { self.skipWaiting(); });
self.addEventListener('activate', function (e) { e.waitUntil(self.clients.claim()); });
self.addEventListener('fetch', function () { /* network only */ });
