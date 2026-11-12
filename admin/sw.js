/* Service worker de l'administration : notifications push (aucune donnée n'est mise en cache). */
self.addEventListener('install', () => self.skipWaiting());
self.addEventListener('activate', e => e.waitUntil(self.clients.claim()));

self.addEventListener('push', e => {
  let d = {};
  try { d = e.data ? e.data.json() : {}; } catch (err) { d = { body: e.data ? e.data.text() : '' }; }
  e.waitUntil(self.registration.showNotification(d.title || 'La Salle RN6', {
    body: d.body || '',
    icon: 'icons/icon-192.png',
    badge: 'icons/icon-192.png',
    tag: d.tag || undefined,
    data: { url: d.url || 'index.php' },
  }));
});

self.addEventListener('notificationclick', e => {
  e.notification.close();
  const url = new URL((e.notification.data && e.notification.data.url) || 'index.php', self.registration.scope).href;
  if (!url.startsWith(self.registration.scope)) return; // n'ouvre que des pages de l'admin
  e.waitUntil(self.clients.matchAll({ type: 'window', includeUncontrolled: true }).then(ws => {
    for (const w of ws) {
      if (w.url.startsWith(self.registration.scope) && 'focus' in w) { w.navigate(url); return w.focus(); }
    }
    return self.clients.openWindow(url);
  }));
});
