const CACHE_NAME = "sammlung-pwa-v8";
// Miniaturen (thumb.php) und lokale Bilder (medien/): eigener Cache mit Obergrenze, bleibt bei Updates bestehen
const IMG_CACHE = "sammlung-img-v1";
const IMG_CACHE_MAX = 200;

// Nur wirklich statische Sachen cachen:
const STATIC_ASSETS = [
  "/sammlung/manifest.php",
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
    // Einzeln und fehlertolerant: ein fehlendes Asset darf die Installation nicht verhindern
    caches.open(CACHE_NAME).then((cache) =>
      Promise.allSettled(STATIC_ASSETS.map((u) => cache.add(u)))
    )
  );
  self.skipWaiting();
});

self.addEventListener("activate", (event) => {
  event.waitUntil(
    caches.keys().then((keys) =>
      Promise.all(keys.map((k) => (k !== CACHE_NAME && k !== IMG_CACHE ? caches.delete(k) : null)))
    )
  );
  self.clients.claim();
});

self.addEventListener("fetch", (event) => {
  const req = event.request;
  const url = new URL(req.url);

  if (url.origin !== location.origin) return;
  if (!url.pathname.startsWith("/sammlung/")) return;

  // Miniaturen und lokale Bilder: cache-first mit Obergrenze (die URL enthält die Änderungszeit, ein Austausch ist also eine neue URL)
  if (req.method === "GET" && (url.pathname === "/sammlung/thumb.php" || url.pathname.startsWith("/sammlung/medien/"))) {
    event.respondWith(
      caches.open(IMG_CACHE).then((cache) =>
        cache.match(req).then((cached) => {
          if (cached) return cached;
          return fetch(req).then((res) => {
            if (res.ok) {
              cache.put(req, res.clone()).then(() => trimImageCache(cache));
            }
            return res;
          });
        })
      )
    );
    return;
  }

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

// Älteste Einträge entfernen, wenn mehr als IMG_CACHE_MAX Bilder gespeichert sind
function trimImageCache(cache) {
  return cache.keys().then((keys) => {
    const extra = keys.length - IMG_CACHE_MAX;
    if (extra <= 0) return null;
    return Promise.all(keys.slice(0, extra).map((k) => cache.delete(k)));
  });
}
