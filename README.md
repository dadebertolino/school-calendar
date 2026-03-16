# School Calendar - Plugin WordPress

Plugin WordPress per la gestione del calendario scolastico con supporto multi-plesso, sub-calendari, permessi utente e importazione ICS.

## Funzionalità

### Calendario
- 📅 Visualizzazione mese, settimana, giorno e lista
- 🎨 Sub-calendari con colori personalizzati (eventi multi-categoria mostrati a strisce)
- 🔒 Eventi pubblici e riservati (visibili solo agli utenti loggati)
- 📍 Filtri per plesso, categoria e classe
- 🖱️ Click su evento per modifica rapida (se autorizzato)
- 🖱️ Click su data vuota per creare nuovo evento
- 📋 Duplicazione eventi con un click
- 📱 Responsive e mobile-friendly
- 🚫 Opzione per nascondere weekend

### Gestione Utenti
- 👥 Pagina admin per assegnare permessi ai docenti
- ✏️ Docenti possono creare e modificare solo i propri eventi
- 👑 Admin può gestire tutti gli eventi

### Importazione
- 📥 Importazione file ICS con scelta plesso e sub-calendario
- 🔄 Opzione "Pulisci e reimporta" per evitare duplicati
- 🌐 Sync con calendari esterni (Google Calendar, iCal)

### API REST
- 🔌 API completa per integrazione con sistemi esterni
- 🔑 Autenticazione tramite API Key
- 📊 Endpoint per eventi, plessi, classi, sub-calendari

## Installazione

1. Scarica lo ZIP del plugin
2. Vai in WordPress > Plugin > Aggiungi nuovo > Carica plugin
3. Carica il file ZIP e attiva il plugin
4. Vai in "Calendario" nel menu admin per configurare

## Shortcode

### Calendario principale
```
[school_calendar]
```

**Attributi disponibili:**
| Attributo | Default | Descrizione |
|-----------|---------|-------------|
| `view` | `dayGridMonth` | Vista iniziale: `dayGridMonth`, `timeGridWeek`, `timeGridDay`, `listMonth` |
| `plesso` | `all` | ID plesso o `all` per tutti |
| `show_filters` | `true` | Mostra filtri plesso/categoria/classe |
| `show_legend` | `true` | Mostra legenda colori |
| `hide_weekends` | `false` | Nasconde sabato e domenica |
| `height` | `auto` | Altezza calendario |
| `slot_min_time` | `07:00` | Ora inizio vista settimanale |
| `slot_max_time` | `20:00` | Ora fine vista settimanale |
| `require_login` | `false` | Richiede login per visualizzare |

**Esempio:**
```
[school_calendar view="timeGridWeek" hide_weekends="true" plesso="1"]
```

### Form creazione eventi
```
[school_calendar_form]
```
Mostra un form per creare nuovi eventi (solo utenti autorizzati).

### I miei eventi
```
[school_calendar_my_events]
```
Lista degli eventi dell'utente con possibilità di modifica/elimina.

**Attributi:**
| Attributo | Default | Descrizione |
|-----------|---------|-------------|
| `show_past` | `false` | Mostra anche eventi passati |

### Lista eventi
```
[school_calendar_list limit="10" days="30"]
```
Lista compatta dei prossimi eventi.

### Widget
```
[school_calendar_widget title="Prossimi eventi" limit="5"]
```
Widget per sidebar con prossimi eventi.

## Permessi

| Ruolo | Visualizza | Crea | Modifica propri | Modifica tutti |
|-------|------------|------|-----------------|----------------|
| Visitatore | ✅ pubblici | ❌ | ❌ | ❌ |
| Utente loggato | ✅ tutti | ❌ | ❌ | ❌ |
| Docente abilitato | ✅ tutti | ✅ | ✅ | ❌ |
| Amministratore | ✅ tutti | ✅ | ✅ | ✅ |

Per abilitare un docente: **Calendario → Permessi**

## Importazione ICS

1. Vai in **Calendario → Importa ICS**
2. Seleziona il file .ics
3. Scegli plesso e sub-calendario di destinazione
4. (Opzionale) Attiva "Pulisci e reimporta" per eliminare eventi esistenti
5. Clicca "Importa Eventi"

**Campi importati:**
- `SUMMARY` → Titolo
- `DESCRIPTION` → Descrizione
- `LOCATION` → Luogo
- `ATTENDEE` → Responsabile
- `DTSTART/DTEND` → Date e orari

## API REST

Base URL: `/wp-json/school-calendar/v1/`

### Endpoint principali

| Metodo | Endpoint | Descrizione |
|--------|----------|-------------|
| GET | `/eventi` | Lista eventi |
| POST | `/eventi` | Crea evento |
| GET | `/eventi/{id}` | Dettaglio evento |
| PUT | `/eventi/{id}` | Modifica evento |
| DELETE | `/eventi/{id}` | Elimina evento |
| GET | `/plessi` | Lista plessi |
| GET | `/classi` | Lista classi |
| GET | `/sub-calendari` | Lista sub-calendari |

### Autenticazione

**Cookie (frontend WordPress):**
```javascript
fetch('/wp-json/school-calendar/v1/eventi', {
    headers: { 'X-WP-Nonce': wpApiSettings.nonce }
})
```

**API Key (sistemi esterni):**
```bash
curl -H "X-SC-API-Key: your-api-key" \
     https://example.com/wp-json/school-calendar/v1/eventi
```

## Requisiti

- WordPress 5.8+
- PHP 7.4+
- MySQL 5.7+

## Changelog

### 1.8.8
- Fix errore sintassi JavaScript

### 1.8.6
- Aggiunta opzione "Pulisci e reimporta" per importazione ICS
- Scelta sub-calendario in creazione/modifica eventi da calendario

### 1.8.4
- Aggiunto attributo `hide_weekends` per nascondere weekend

### 1.8.3
- Eventi multi-calendario visualizzati a strisce colorate

### 1.8.2
- Pulsante "Elimina" nel form modifica evento

### 1.8.1
- Click singolo su evento apre modifica (se autorizzato) o dettagli

### 1.8.0
- Click su data vuota crea nuovo evento
- Pulsante "Duplica" evento

### 1.7.3
- Importazione file ICS con scelta plesso e sub-calendario

### 1.7.2
- Utenti loggati vedono tutti gli eventi (pubblici + riservati)

### 1.7.1
- Shortcode `[school_calendar_my_events]` per gestire propri eventi

### 1.7.0
- Shortcode `[school_calendar_form]` per creare eventi
- Pagina admin "Permessi" per gestire autorizzazioni docenti

### 1.6.0
- Filtro "Categoria" (sub-calendario) nella barra filtri
- Legenda semplificata con pallini colorati

## Licenza

GPL v2 or later

## Autore

Sviluppato per la gestione dei calendari scolastici.
