# FuelFinder

PWA per trovare i distributori di carburante più convenienti vicino a te in Italia, con calcolo del costo reale considerando anche il viaggio.

## Funzionalità

- **Ricerca per posizione** — GPS automatico o ricerca per indirizzo con autocomplete (Nominatim/OpenStreetMap)
- **Filtro per tipo carburante** — benzina, diesel, GPL, metano, HVO
- **Filtro per marca** — seleziona solo i brand preferiti
- **Calcolo costo totale** — prezzo carburante + costo del viaggio andata/ritorno in base ai consumi del veicolo
- **Modalità SOS** — trova il distributore più vicino in assoluto, senza filtri
- **Garage veicoli** — salva i tuoi veicoli in locale (localStorage) con autonomia e consumi
- **PWA installabile** — funziona come app su mobile e desktop
- **Tutorial guidato** — overlay 7 step al primo accesso

## Fonti dati

I prezzi e l'anagrafica dei distributori provengono da fonti ufficiali del Ministero delle Imprese e del Made in Italy (MIMIT):

| Dato | Fonte | Modalità |
|------|-------|----------|
| Anagrafica impianti (nomi, indirizzi) | `mimit.gov.it` — CSV ufficiale | Scaricato una volta ogni 24h, cachato localmente |
| Prezzi carburanti | API OSPZ `carburanti.mise.gov.it/ospzApi` | Chiamata live ad ogni ricerca |
| Distanze stradali | OSRM `router.project-osrm.org` | Chiamata live ad ogni ricerca (max 15 candidati) |
| Geocoding indirizzi | Nominatim (OpenStreetMap) | Chiamata live durante l'autocomplete |

## Stack tecnico

- **Backend** — PHP puro, nessun framework
- **Frontend** — HTML/CSS/JS vanilla, dark theme GitHub-style
- **Font** — Space Mono + DM Sans
- **Geocoding** — Nominatim (OpenStreetMap), gratuito e senza API key
- **PWA** — `manifest.json` + Service Worker (`sw.js`)
- **Storage** — localStorage per veicoli e preferenze, CSV locali per i dati MIMIT

## Struttura

```
fuelfinder/
├── index.php              # App completa (backend + frontend)
├── includes/
│   ├── api.php            # Download CSV anagrafica + chiamate OSPZ
│   ├── config.php         # Costanti e helper cURL
│   └── data.php           # Join anagrafica+prezzi, filtri, calcoli
├── js/
│   ├── app.js             # Logica frontend
│   └── tutorial.js        # Tutorial overlay
├── style.css
├── manifest.json
├── sw.js
└── anagrafica.csv         # Cache locale (generata a runtime, non in repo)
```

## Deploy locale (XAMPP)

1. Copia la cartella in `htdocs/fuelfinder`
2. Avvia Apache da XAMPP
3. Apri `http://localhost/fuelfinder`

I CSV vengono scaricati automaticamente al primo accesso.
