self.addEventListener('install', (e) => {
  // Installazione silenziosa
});

self.addEventListener('fetch', (e) => {
  // Gestione richieste (lasciamo passare tutto al server per ora)
  e.respondWith(fetch(e.request));
});

self.addEventListener('fetch', function(event) {
    // Questo serve solo per far credere a Chrome che l'app sia una PWA
});