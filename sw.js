// Service worker minimale: serve solo a rendere l'app installabile come PWA.
// NON intercetta le risposte.
//
// La versione precedente faceva `e.respondWith(fetch(e.request))`: sembra un
// passthrough innocuo, ma prende il controllo della risposta e rifà la
// richiesta. Se quel fetch veniva rifiutato, il browser riceveva un network
// error invece della risposta del server — rompendo in particolare i POST di
// navigazione (ricerca e SOS) con "FetchEvent ... resulted in a network error".
// Senza respondWith il browser gestisce la richiesta nativamente: nessun hop
// in più, nessun punto di rottura.
//
// skipWaiting + clients.claim: sostituiscono subito un service worker già
// installato nei browser degli utenti, senza attendere la chiusura di tutte le
// schede (altrimenti la vecchia versione resterebbe attiva).

self.addEventListener('install', (e) => {
  self.skipWaiting();
});

self.addEventListener('activate', (e) => {
  e.waitUntil(self.clients.claim());
});

// Handler presente ma deliberatamente vuoto: senza respondWith la richiesta
// segue il percorso normale del browser. Serve per l'installabilità PWA.
self.addEventListener('fetch', (e) => {
  // no-op
});
