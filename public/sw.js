// Service Worker Dompet Owner — cache-first utk aset statis, network untuk API
const CACHE = 'dompet-v1';
const ASSETS = ['/', '/vendor/chart.umd.min.js', '/fonts-local.css'];

self.addEventListener('install', e => {
  e.waitUntil(caches.open(CACHE).then(c => c.addAll(ASSETS)).then(() => self.skipWaiting()));
});
self.addEventListener('activate', e => {
  e.waitUntil(caches.keys().then(keys =>
    Promise.all(keys.filter(k => k !== CACHE).map(k => caches.delete(k)))).then(() => self.clients.claim()));
});
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  // API & laporan: selalu jaringan (data keuangan harus segar)
  if (url.pathname.includes('api.php') || url.pathname.includes('report.php')) return;
  if (e.request.method !== 'GET') return;
  e.respondWith(
    caches.match(e.request).then(hit => hit || fetch(e.request).then(res => {
      if (res.ok && (url.pathname.startsWith('/vendor/') || url.pathname.startsWith('/fonts/'))) {
        const copy = res.clone();
        caches.open(CACHE).then(c => c.put(e.request, copy));
      }
      return res;
    }).catch(() => caches.match('/')))
  );
});
