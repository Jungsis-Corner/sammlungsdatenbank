const CACHE_NAME = "sammlung-pwa-v2";

// Nur wirklich statische Sachen cachen:
const STATIC_ASSETS = [
  "/sammlung/manifest.json",
  "/sammlung/icons/icon-192.png",
  "/sammlung/icons/icon-512.png",
  "/sammlung/icons/apple-touch-icon.png",
  "/sammlung/offline.php"
  // Hier deine echten Assets ergänzen, z.B.:
  // "/sammlung/assets/style.css",
  // "/sammlung/assets/app.js"
];

self.addEventListener("install", (event) => {
  event.waitUntil(
    caches.open(CACHE_NAME).then((cache) => cache.addAll(STATIC_ASSETS))
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) => (k !== CACHE_NAME ? caches.delete(k) : null)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (url.origin !== location.origin) return;
  if (!url.pathname.startsWith("/sammlung/")) return;

  const accept = req.headers.get("accept") || "";
  const isHTML = req.mode === "navigate" || accept.includes("text/html");

  // HTML/PHP: immer Netz, kein Cache (damit Login/Permissions sauber bleiben)
  if (isHTML) {
    event.respondWith(
      fetch(req).catch(() => caches.match("/sammlung/offline.php"))
    );
    return;
  }

  // Assets: cache-first
  event.respondWith(
    caches.match(req).then((cached) => {
      if (cached) return cached;
      return fetch(req).then((res) => {
        // Nur erfolgreiche GETs cachen
        if (req.method === "GET" && res.ok) {
          const copy = res.clone();
          caches.open(CACHE_NAME).then((cache) => cache.put(req, copy));
        }
        return res;
      });
    })
  );
});