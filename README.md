# FuelFinder

PWA per trovare i distributori di carburante più convenienti vicino a te, con calcolo del costo reale considerando anche il viaggio. Supporto **Italia** e **Germania** con rilevamento automatico del paese e ricerca cross-border nelle zone di confine.

## Funzionalità

- **Ricerca per posizione** — GPS automatico o ricerca per indirizzo con autocomplete (Nominatim/OpenStreetMap, supporto indirizzi IT e DE)
- **Multi-paese** — provider Italia (MIMIT) e Germania (Tankerkoenig/MTS-K), rilevamento automatico + cross-border
- **Filtro per tipo carburante** — benzina, diesel (+ GPL, metano solo IT; opzioni nascoste fuori paese)
- **Filtro per marca** — seleziona solo i brand preferiti (selezione ricordata in localStorage)
- **Distanze stradali reali** — calcolo via OSRM `/route` in parallelo (non linea d'aria), con cache 30 giorni
- **Calcolo costo totale** — prezzo carburante + costo del viaggio andata/ritorno in base ai consumi del veicolo
- **Modalità SOS** — trova il distributore più vicino in assoluto, senza filtri
- **Garage veicoli** — salva i tuoi veicoli in locale (localStorage) con tipo carburante e consumi
- **Interfaccia multilingua** — Italiano e Tedesco, selettore nell'header con cookie persistente
- **Progress bar di caricamento** — feedback dettagliato sui passaggi della ricerca
- **Cache multi-livello** — ricerche (1h), distanze OSRM (30gg), lista marche (24h)
- **PWA installabile** — funziona come app su mobile e desktop
- **Tutorial guidato** — overlay 7 step al primo accesso, tradotto

## Fonti dati

| Paese | Dato | Fonte | Note |
|-------|------|-------|------|
| 🇮🇹 IT | Anagrafica impianti | `mimit.gov.it` — CSV ufficiale | Scaricato 1x/24h, cachato localmente |
| 🇮🇹 IT | Prezzi carburanti | API OSPZ `carburanti.mise.gov.it/ospzApi` | Live ad ogni ricerca, aggiornamento MIMIT 1-2x/giorno |
| 🇩🇪 DE | Anagrafica + prezzi | Tankerkoenig `creativecommons.tankerkoenig.de` | Feed ufficiale MTS-K (Bundeskartellamt), quasi realtime, richiede API key |
| 🌍 | Distanze stradali | OSRM `router.project-osrm.org` | Chiamate parallele via curl_multi, cache 30gg |
| 🌍 | Geocoding indirizzi | Nominatim (OpenStreetMap) | Autocomplete live, filtrato per `countrycodes=it,de` |

## Stack tecnico

- **Backend** — PHP puro, nessun framework, split modulare in `includes/`
- **Frontend** — HTML/CSS/JS vanilla, dark theme con glassmorphism
- **Font** — Inter + JetBrains Mono
- **Geocoding** — Nominatim (OpenStreetMap), gratuito e senza API key
- **Routing** — OSRM demo server (sostituibile con istanza self-hosted per alto traffico)
- **PWA** — `manifest.json` + Service Worker (`sw.js`)
- **Storage** — localStorage per veicoli/preferenze, file cache server-side per OSRM/ricerche/marche
- **i18n** — array PHP `$LANG` (IT/DE) + `window.FF_T` per stringhe JS

## Struttura

```
fuelfinder/
├── index.php                        # Entry point, view e layout
├── includes/
│   ├── config.php                   # Costanti pubbliche + caricamento secrets
│   ├── config.local.php             # Secrets (API key) — gitignorato
│   ├── config.local.example.php     # Template da copiare
│   ├── i18n.php                     # Dizionari IT/DE + helper t()
│   ├── api.php                      # Endpoint brands (con cache 24h)
│   ├── cache.php                    # Cache file-based con TTL e sharding MD5
│   ├── data.php                     # Orchestrazione ricerca: provider → OSRM → calcoli
│   └── providers/
│       ├── router.php               # Dispatcher paese + bbox + cross-border
│       ├── mimit.php                # Provider Italia
│       └── tankerkoenig.php         # Provider Germania
├── js/
│   ├── app.js                       # Logica frontend (GPS, form, progress, garage, brands)
│   └── tutorial.js                  # Tutorial overlay
├── style.css
├── manifest.json
├── sw.js
└── cache/                           # Cache server (generata a runtime, gitignorata)
```

## Setup

### 1. Chiave API Tankerkoenig (per distributori tedeschi)

Registrazione gratuita su https://creativecommons.tankerkoenig.de/

```bash
cp includes/config.local.example.php includes/config.local.php
# edita config.local.php e inserisci la tua chiave
```

Se la chiave non è presente, il provider DE viene disattivato automaticamente e l'app funziona solo per l'Italia.

### 2. Deploy locale (XAMPP)

1. Copia la cartella in `htdocs/fuelfinder`
2. Avvia Apache da XAMPP
3. Assicurati che `cache/` sia scrivibile dall'utente Apache:
   ```bash
   chmod -R 777 cache/
   ```
4. Apri `http://localhost/fuelfinder`

I dati MIMIT vengono scaricati automaticamente al primo accesso.

## Sicurezza

- `includes/config.local.php` è **gitignorato** — non committare mai la chiave API
- `cache/` è gitignorato — contiene dati runtime
- Nessun dato personale inviato a terze parti (solo GPS → provider pubblici)
