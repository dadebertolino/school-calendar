/**
 * School Calendar Frontend JS
 */
(function() {
    'use strict';
    
    window.SchoolCalendar = {
        
        instances: {},
        
        /**
         * Ottieni configurazione globale
         */
        getConfig: function() {
            return window.schoolCalendarConfig || {};
        },
        
        /**
         * Inizializza istanza calendario
         */
        init: function(options) {
            var self = this;
            var instanceId = options.instanceId;
            var calendarEl = document.getElementById(instanceId);
            var config = this.getConfig();
            
            if (!calendarEl) {
                console.error('School Calendar: element not found:', instanceId);
                return;
            }
            
            if (typeof FullCalendar === 'undefined') {
                console.error('School Calendar: FullCalendar not loaded');
                return;
            }
            
            // Salva istanza PRIMA di creare il calendario
            this.instances[instanceId] = {
                calendar: null,
                options: options,
                filters: {
                    plesso: options.plesso || null,
                    classe: options.classe || null
                }
            };
            
            // Configura FullCalendar
            var calendarConfig = {
                locale: config.locale || 'it',
                initialView: options.initialView || 'dayGridMonth',
                height: options.height || 'auto',
                headerToolbar: {
                    left: 'prev,next today',
                    center: 'title',
                    right: ''
                },
                buttonText: {
                    today: 'Oggi',
                    month: 'Mese',
                    week: 'Settimana',
                    day: 'Giorno',
                    list: 'Lista'
                },
                firstDay: 1,
                navLinks: true,
                dayMaxEvents: 3,
                eventDisplay: 'block',
                
                // Orari visualizzazione settimanale/giornaliera
                slotMinTime: options.slotMinTime || '07:00:00',
                slotMaxTime: options.slotMaxTime || '20:00:00',
                slotDuration: '00:30:00',
                
                // Eventi sovrapposti in colonne separate
                slotEventOverlap: false,
                eventMaxStack: 4,
                
                // Nascondi testo "all day"
                allDayText: '',
                
                // Nascondi weekend se configurato
                weekends: !options.hideWeekends,
                
                // Abilita selezione date per creare eventi
                selectable: config.canCreate,
                
                // Fetch eventi da API
                events: function(info, successCallback, failureCallback) {
                    self.fetchEvents(instanceId, info, successCallback, failureCallback);
                },
                
                // Applica gradiente per eventi multi-colore
                eventDidMount: function(info) {
                    var props = info.event.extendedProps || {};
                    if (props.gradientStyle) {
                        info.el.style.background = props.gradientStyle;
                    }
                },
                
                // Click su evento - modifica o dettagli in base ai permessi
                eventClick: function(info) {
                    info.jsEvent.preventDefault();
                    var eventId = info.event.id;
                    var props = info.event.extendedProps || {};
                    
                    // Verifica se può modificare
                    var canEdit = config.canEdit && props.source === 'local' && 
                                  (config.isAdmin || props.autore_id == config.currentUserId);
                    
                    if (canEdit) {
                        self.openEditModal(instanceId, eventId);
                    } else {
                        self.showEventModal(instanceId, info.event);
                    }
                },
                
                // Click su data vuota - crea nuovo evento
                dateClick: function(info) {
                    if (config.canCreate) {
                        self.openCreateModal(instanceId, info.dateStr);
                    }
                },
                
                // Selezione range date - crea evento con range
                select: function(info) {
                    if (config.canCreate) {
                        self.openCreateModal(instanceId, info.startStr, info.endStr);
                    }
                },
                
                // Hover su evento (solo desktop)
                eventMouseEnter: function(info) {
                    info.el.style.cursor = 'pointer';
                    if (window.innerWidth > 768) {
                        self.showEventTooltip(info.event, info.el);
                    }
                },
                
                // Mouse leave per nascondere tooltip
                eventMouseLeave: function(info) {
                    self.hideEventTooltip();
                },
                
                // Drag & Drop (se abilitato)
                editable: options.editable && config.canEdit,
                eventDrop: function(info) {
                    self.updateEventDates(info);
                },
                eventResize: function(info) {
                    self.updateEventDates(info);
                },
                
                // Loading indicator
                loading: function(isLoading) {
                    var wrapper = document.getElementById(instanceId + '-wrapper');
                    if (wrapper) {
                        wrapper.classList.toggle('sc-loading', isLoading);
                    }
                }
            };
            
            // Crea istanza calendario
            var calendar = new FullCalendar.Calendar(calendarEl, calendarConfig);
            this.instances[instanceId].calendar = calendar;
            
            // Render
            calendar.render();
            
            // Setup filtri
            if (options.showFilters) {
                this.setupFilters(instanceId);
            }
            
            // Setup modal
            this.setupModal(instanceId);
            
            // Evidenzia view attiva
            this.updateActiveViewButton(instanceId, options.initialView);
            
            console.log('School Calendar initialized:', instanceId);
        },
        
        /**
         * Fetch eventi da API
         */
        fetchEvents: function(instanceId, info, successCallback, failureCallback) {
            var self = this;
            var instance = this.instances[instanceId];
            var config = this.getConfig();
            
            if (!instance) {
                console.error('School Calendar: instance not found:', instanceId);
                failureCallback(new Error('Instance not found'));
                return;
            }
            
            if (!config.apiUrl) {
                console.error('School Calendar: apiUrl not configured');
                failureCallback(new Error('API URL not configured'));
                return;
            }
            
            var params = new URLSearchParams({
                start: info.startStr.split('T')[0],
                end: info.endStr.split('T')[0],
                format: 'fullcalendar'
            });
            
            if (instance.filters && instance.filters.plesso) {
                params.append('plesso_id', instance.filters.plesso);
            }
            
            if (instance.filters && instance.filters.classe) {
                params.append('classe_id', instance.filters.classe);
            }
            
            if (instance.filters && instance.filters.subCalendario) {
                params.append('sub_calendario_id', instance.filters.subCalendario);
            }
            
            var fetchUrl = config.apiUrl + '/eventi?' + params.toString();
            
            fetch(fetchUrl, {
                headers: {
                    'X-WP-Nonce': config.nonce || ''
                }
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('HTTP ' + response.status);
                }
                return response.json();
            })
            .then(function(events) {
                // Applica colori custom
                events = events.map(function(event) {
                    return self.styleEvent(event);
                });
                successCallback(events);
            })
            .catch(function(error) {
                console.error('School Calendar: error fetching events:', error);
                successCallback([]); // Ritorna array vuoto invece di fallire
            });
        },
        
        /**
         * Applica stili all'evento
         */
        styleEvent: function(event) {
            var props = event.extendedProps || {};
            var bgColor = null;
            var colors = [];
            
            // Raccogli colori dai sub-calendari
            if (props.sub_calendari && props.sub_calendari.length > 0) {
                props.sub_calendari.forEach(function(sc) {
                    if (sc.colore) {
                        colors.push(sc.colore);
                    }
                });
            }
            
            // Se ha più colori, crea gradiente a strisce
            if (colors.length > 1) {
                var stripeWidth = 100 / colors.length;
                var gradientParts = [];
                colors.forEach(function(color, i) {
                    var start = i * stripeWidth;
                    var end = (i + 1) * stripeWidth;
                    gradientParts.push(color + ' ' + start + '%');
                    gradientParts.push(color + ' ' + end + '%');
                });
                event.backgroundColor = 'transparent';
                event.borderColor = colors[0];
                event.textColor = this.isLightColor(colors[0]) ? '#2d3436' : '#ffffff';
                
                // Aggiungi classe per applicare gradiente via CSS
                event.classNames = event.classNames || [];
                event.classNames.push('sc-event-multicolor');
                event.extendedProps.gradientStyle = 'linear-gradient(90deg, ' + gradientParts.join(', ') + ')';
            }
            // Singolo colore sub-calendario
            else if (colors.length === 1) {
                bgColor = colors[0];
                event.backgroundColor = bgColor;
                event.borderColor = this.darkenColor(bgColor, 15);
                event.textColor = this.isLightColor(bgColor) ? '#2d3436' : '#ffffff';
            }
            // Colore da props.colore (calendario esterno)
            else if (props.colore) {
                bgColor = props.colore;
                event.backgroundColor = bgColor;
                event.borderColor = this.darkenColor(bgColor, 15);
                event.textColor = this.isLightColor(bgColor) ? '#2d3436' : '#ffffff';
            }
            // Colori per source
            else if (props.source === 'google') {
                bgColor = '#00b894';
                event.backgroundColor = bgColor;
                event.borderColor = this.darkenColor(bgColor, 15);
                event.textColor = '#ffffff';
            } else if (props.source === 'ical') {
                bgColor = '#fdcb6e';
                event.backgroundColor = bgColor;
                event.borderColor = this.darkenColor(bgColor, 15);
                event.textColor = '#2d3436';
            } else if (props.source === 'booking') {
                bgColor = '#00cec9';
                event.backgroundColor = bgColor;
                event.borderColor = this.darkenColor(bgColor, 15);
                event.textColor = '#ffffff';
            }
            // Colori per visibilità
            else if (props.visibilita === 'privato') {
                bgColor = '#6c5ce7';
                event.backgroundColor = bgColor;
                event.borderColor = this.darkenColor(bgColor, 15);
                event.textColor = '#ffffff';
            } else {
                bgColor = '#0984e3';
                event.backgroundColor = bgColor;
                event.borderColor = this.darkenColor(bgColor, 15);
                event.textColor = '#ffffff';
            }
            
            return event;
        },
        
        /**
         * Scurisce un colore hex
         */
        darkenColor: function(hex, percent) {
            var num = parseInt(hex.replace('#', ''), 16);
            var amt = Math.round(2.55 * percent);
            var R = (num >> 16) - amt;
            var G = (num >> 8 & 0x00FF) - amt;
            var B = (num & 0x0000FF) - amt;
            R = Math.max(0, R);
            G = Math.max(0, G);
            B = Math.max(0, B);
            return '#' + (0x1000000 + R * 0x10000 + G * 0x100 + B).toString(16).slice(1);
        },
        
        /**
         * Determina se un colore è chiaro
         */
        isLightColor: function(hex) {
            var num = parseInt(hex.replace('#', ''), 16);
            var R = num >> 16;
            var G = num >> 8 & 0x00FF;
            var B = num & 0x0000FF;
            var luminance = (0.299 * R + 0.587 * G + 0.114 * B) / 255;
            return luminance > 0.5;
        },
        
        /**
         * Setup filtri
         */
        setupFilters: function(instanceId) {
            var self = this;
            var wrapper = document.getElementById(instanceId + '-wrapper');
            
            if (!wrapper) return;
            
            // Filtro plesso
            var plessoSelect = wrapper.querySelector('.sc-filter-plesso');
            var subcalSelect = wrapper.querySelector('.sc-filter-subcal');
            var classeSelect = wrapper.querySelector('.sc-filter-classe');
            
            if (plessoSelect) {
                plessoSelect.addEventListener('change', function() {
                    var plessoId = this.value;
                    self.instances[instanceId].filters.plesso = plessoId || null;
                    self.instances[instanceId].filters.classe = null;
                    self.instances[instanceId].filters.subCalendario = null;
                    
                    // Aggiorna classi
                    self.loadClassi(instanceId, plessoId);
                    
                    // Aggiorna sub-calendari visibili nel dropdown
                    if (subcalSelect) {
                        self.filterSubCalOptions(subcalSelect, plessoId);
                    }
                    
                    // Refresh calendario
                    self.instances[instanceId].calendar.refetchEvents();
                });
            }
            
            // Filtro sub-calendario
            if (subcalSelect) {
                subcalSelect.addEventListener('change', function() {
                    self.instances[instanceId].filters.subCalendario = this.value || null;
                    self.instances[instanceId].calendar.refetchEvents();
                });
            }
            
            if (classeSelect) {
                classeSelect.addEventListener('change', function() {
                    self.instances[instanceId].filters.classe = this.value || null;
                    self.instances[instanceId].calendar.refetchEvents();
                });
            }
            
            // Bottoni vista
            var viewButtons = wrapper.querySelectorAll('.sc-view-btn');
            viewButtons.forEach(function(btn) {
                btn.addEventListener('click', function() {
                    var view = this.dataset.view;
                    self.instances[instanceId].calendar.changeView(view);
                    self.updateActiveViewButton(instanceId, view);
                });
            });
        },
        
        /**
         * Filtra opzioni sub-calendario per plesso
         */
        filterSubCalOptions: function(select, plessoId) {
            var options = select.querySelectorAll('option[data-plesso]');
            select.value = '';
            
            // Mostra/nascondi opzioni in base al plesso
            options.forEach(function(opt) {
                if (!plessoId || opt.dataset.plesso === plessoId) {
                    opt.disabled = false;
                    opt.hidden = false;
                } else {
                    opt.disabled = true;
                    opt.hidden = true;
                }
            });
        },
        
        /**
         * Carica classi per plesso
         */
        loadClassi: function(instanceId, plessoId) {
            var wrapper = document.getElementById(instanceId + '-wrapper');
            var classeSelect = wrapper.querySelector('.sc-filter-classe');
            
            if (!classeSelect) return;
            
            classeSelect.innerHTML = '<option value="">Tutte le classi</option>';
            classeSelect.disabled = true;
            
            if (!plessoId) return;
            
            fetch(this.getConfig().apiUrl + '/classi?plesso_id=' + plessoId, {
                headers: {
                    'X-WP-Nonce': this.getConfig().nonce
                }
            })
            .then(function(response) { return response.json(); })
            .then(function(classi) {
                classi.forEach(function(classe) {
                    var option = document.createElement('option');
                    option.value = classe.id;
                    option.textContent = classe.nome;
                    classeSelect.appendChild(option);
                });
                classeSelect.disabled = false;
            })
            .catch(function(error) {
                console.error('Error loading classi:', error);
            });
        },
        
        /**
         * Aggiorna bottone vista attivo
         */
        updateActiveViewButton: function(instanceId, activeView) {
            var wrapper = document.getElementById(instanceId + '-wrapper');
            if (!wrapper) return;
            
            var buttons = wrapper.querySelectorAll('.sc-view-btn');
            buttons.forEach(function(btn) {
                btn.classList.toggle('active', btn.dataset.view === activeView);
            });
        },
        
        /**
         * Apre modal per creare nuovo evento
         */
        openCreateModal: function(instanceId, startDate, endDate) {
            var self = this;
            var config = this.getConfig();
            var modal = document.getElementById(instanceId + '-modal');
            var body = modal.querySelector('.sc-modal-body');
            
            // Prepara date
            var start = startDate.split('T')[0];
            var end = endDate ? endDate.split('T')[0] : start;
            
            // Mostra loading e carica sub-calendari
            body.innerHTML = '<p class="sc-loading">Caricamento...</p>';
            modal.style.display = 'flex';
            
            fetch(config.apiUrl + '/sub-calendari', {
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(subCals) {
                var html = '<div class="sc-quick-form">';
                html += '<h2>✨ Nuovo Evento</h2>';
                html += '<form id="' + instanceId + '-quick-create">';
                html += '<div class="sc-form-row"><label>Titolo *</label><input type="text" id="' + instanceId + '-qc-titolo" required></div>';
                html += '<div class="sc-form-row"><label>Descrizione</label><textarea id="' + instanceId + '-qc-descrizione" rows="2"></textarea></div>';
                html += '<div class="sc-form-row sc-form-row-inline">';
                html += '<div><label>Data inizio</label><input type="date" id="' + instanceId + '-qc-data-inizio" value="' + start + '" required></div>';
                html += '<div><label>Ora</label><input type="time" id="' + instanceId + '-qc-ora-inizio" value="08:00"></div>';
                html += '</div>';
                html += '<div class="sc-form-row sc-form-row-inline">';
                html += '<div><label>Data fine</label><input type="date" id="' + instanceId + '-qc-data-fine" value="' + end + '" required></div>';
                html += '<div><label>Ora</label><input type="time" id="' + instanceId + '-qc-ora-fine" value="09:00"></div>';
                html += '</div>';
                html += '<div class="sc-form-row"><label><input type="checkbox" id="' + instanceId + '-qc-tutto-giorno"> Tutto il giorno</label></div>';
                
                // Sub-calendari
                if (subCals && subCals.length > 0) {
                    html += '<div class="sc-form-row"><label>Categoria</label>';
                    html += '<div class="sc-subcal-checkboxes">';
                    subCals.forEach(function(sc) {
                        html += '<label class="sc-subcal-option" style="--sc-color: ' + sc.colore + ';">';
                        html += '<input type="checkbox" name="subcal" value="' + sc.id + '">';
                        html += '<span class="sc-subcal-color"></span>';
                        html += '<span>' + self.escapeHtml(sc.nome) + '</span>';
                        html += '</label>';
                    });
                    html += '</div></div>';
                }
                
                html += '<div class="sc-form-row"><label>Visibilità</label><select id="' + instanceId + '-qc-visibilita"><option value="pubblico">Pubblico</option><option value="privato">Riservato</option></select></div>';
                html += '<div class="sc-form-row sc-form-buttons">';
                html += '<button type="submit" class="sc-form-submit">Crea Evento</button>';
                html += '<button type="button" class="sc-form-cancel" onclick="SchoolCalendar.closeModal(\'' + instanceId + '\')">Annulla</button>';
                html += '</div>';
                html += '<div id="' + instanceId + '-qc-message" class="sc-form-message" style="display:none;"></div>';
                html += '</form></div>';
                
                body.innerHTML = html;
                document.body.style.overflow = 'hidden';
                
                // Focus su titolo
                document.getElementById(instanceId + '-qc-titolo').focus();
                
                // Toggle tutto il giorno
                document.getElementById(instanceId + '-qc-tutto-giorno').addEventListener('change', function() {
                    document.getElementById(instanceId + '-qc-ora-inizio').disabled = this.checked;
                    document.getElementById(instanceId + '-qc-ora-fine').disabled = this.checked;
                });
                
                // Submit
                document.getElementById(instanceId + '-quick-create').addEventListener('submit', function(e) {
                    e.preventDefault();
                    
                    var tuttoGiorno = document.getElementById(instanceId + '-qc-tutto-giorno').checked;
                    var dataInizio = document.getElementById(instanceId + '-qc-data-inizio').value;
                    var dataFine = document.getElementById(instanceId + '-qc-data-fine').value;
                    
                    if (!tuttoGiorno) {
                        dataInizio += ' ' + document.getElementById(instanceId + '-qc-ora-inizio').value + ':00';
                        dataFine += ' ' + document.getElementById(instanceId + '-qc-ora-fine').value + ':00';
                    } else {
                        dataInizio += ' 00:00:00';
                        dataFine += ' 23:59:59';
                    }
                    
                    // Raccogli sub-calendari selezionati
                    var subcalIds = [];
                    document.querySelectorAll('#' + instanceId + '-quick-create input[name="subcal"]:checked').forEach(function(cb) {
                        subcalIds.push(parseInt(cb.value));
                    });
                    
                    var data = {
                        titolo: document.getElementById(instanceId + '-qc-titolo').value,
                        descrizione: document.getElementById(instanceId + '-qc-descrizione').value,
                        data_inizio: dataInizio,
                        data_fine: dataFine,
                        tutto_giorno: tuttoGiorno ? 1 : 0,
                        sub_calendario_ids: subcalIds,
                        visibilita: document.getElementById(instanceId + '-qc-visibilita').value
                    };
                    
                    fetch(config.apiUrl + '/eventi', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                        body: JSON.stringify(data)
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(result) {
                        if (result.success) {
                            self.closeModal(instanceId);
                            self.instances[instanceId].calendar.refetchEvents();
                        } else {
                            var msg = document.getElementById(instanceId + '-qc-message');
                            msg.className = 'sc-form-message sc-form-error';
                            msg.textContent = result.message || 'Errore';
                            msg.style.display = 'block';
                        }
                    });
                });
            });
        },
        
        /**
         * Apre modal per modificare evento
         */
        openEditModal: function(instanceId, eventId) {
            var self = this;
            var config = this.getConfig();
            var modal = document.getElementById(instanceId + '-modal');
            var body = modal.querySelector('.sc-modal-body');
            
            body.innerHTML = '<p class="sc-loading">Caricamento...</p>';
            modal.style.display = 'flex';
            
            var headers = { 'X-WP-Nonce': config.nonce };
            
            // Carica evento e sub-calendari in parallelo
            Promise.all([
                fetch(config.apiUrl + '/eventi/' + eventId, { headers: headers }).then(function(r) { return r.json(); }),
                fetch(config.apiUrl + '/sub-calendari', { headers: headers }).then(function(r) { return r.json(); })
            ])
            .then(function(results) {
                var e = results[0];
                var subCals = results[1] || [];
                var selectedSubcals = e.sub_calendario_ids || [];
                
                var dataInizio = e.data_inizio.split(' ');
                var dataFine = e.data_fine.split(' ');
                
                var html = '<div class="sc-quick-form">';
                html += '<h2>✏️ Modifica Evento</h2>';
                html += '<form id="' + instanceId + '-quick-edit">';
                html += '<input type="hidden" id="' + instanceId + '-qe-id" value="' + e.id + '">';
                html += '<div class="sc-form-row"><label>Titolo *</label><input type="text" id="' + instanceId + '-qe-titolo" value="' + self.escapeHtml(e.titolo || '') + '" required></div>';
                html += '<div class="sc-form-row"><label>Descrizione</label><textarea id="' + instanceId + '-qe-descrizione" rows="2">' + self.escapeHtml(e.descrizione || '') + '</textarea></div>';
                html += '<div class="sc-form-row sc-form-row-inline">';
                html += '<div><label>Data inizio</label><input type="date" id="' + instanceId + '-qe-data-inizio" value="' + dataInizio[0] + '" required></div>';
                html += '<div><label>Ora</label><input type="time" id="' + instanceId + '-qe-ora-inizio" value="' + (dataInizio[1] ? dataInizio[1].substring(0,5) : '08:00') + '"' + (e.tutto_giorno ? ' disabled' : '') + '></div>';
                html += '</div>';
                html += '<div class="sc-form-row sc-form-row-inline">';
                html += '<div><label>Data fine</label><input type="date" id="' + instanceId + '-qe-data-fine" value="' + dataFine[0] + '" required></div>';
                html += '<div><label>Ora</label><input type="time" id="' + instanceId + '-qe-ora-fine" value="' + (dataFine[1] ? dataFine[1].substring(0,5) : '09:00') + '"' + (e.tutto_giorno ? ' disabled' : '') + '></div>';
                html += '</div>';
                html += '<div class="sc-form-row"><label><input type="checkbox" id="' + instanceId + '-qe-tutto-giorno"' + (e.tutto_giorno ? ' checked' : '') + '> Tutto il giorno</label></div>';
                
                // Sub-calendari
                if (subCals.length > 0) {
                    html += '<div class="sc-form-row"><label>Categoria</label>';
                    html += '<div class="sc-subcal-checkboxes">';
                    subCals.forEach(function(sc) {
                        var checked = selectedSubcals.indexOf(sc.id) !== -1 ? ' checked' : '';
                        html += '<label class="sc-subcal-option" style="--sc-color: ' + sc.colore + ';">';
                        html += '<input type="checkbox" name="subcal" value="' + sc.id + '"' + checked + '>';
                        html += '<span class="sc-subcal-color"></span>';
                        html += '<span>' + self.escapeHtml(sc.nome) + '</span>';
                        html += '</label>';
                    });
                    html += '</div></div>';
                }
                
                html += '<div class="sc-form-row"><label>Visibilità</label><select id="' + instanceId + '-qe-visibilita"><option value="pubblico"' + (e.visibilita === 'pubblico' ? ' selected' : '') + '>Pubblico</option><option value="privato"' + (e.visibilita === 'privato' ? ' selected' : '') + '>Riservato</option></select></div>';
                html += '<div class="sc-form-row sc-form-buttons">';
                html += '<button type="submit" class="sc-form-submit">Salva</button>';
                html += '<button type="button" class="sc-btn-duplicate" onclick="SchoolCalendar.duplicateEvent(\'' + instanceId + '\', ' + e.id + ')">📋 Duplica</button>';
                html += '<button type="button" class="sc-btn-delete" onclick="SchoolCalendar.deleteEvent(\'' + instanceId + '\', ' + e.id + ')">🗑️ Elimina</button>';
                html += '</div>';
                html += '<div class="sc-form-row" style="margin-top:10px;"><button type="button" class="sc-form-cancel" style="width:100%;" onclick="SchoolCalendar.closeModal(\'' + instanceId + '\')">Annulla</button></div>';
                html += '<div id="' + instanceId + '-qe-message" class="sc-form-message" style="display:none;"></div>';
                html += '</form></div>';
                
                body.innerHTML = html;
                document.body.style.overflow = 'hidden';
                
                // Toggle tutto il giorno
                document.getElementById(instanceId + '-qe-tutto-giorno').addEventListener('change', function() {
                    document.getElementById(instanceId + '-qe-ora-inizio').disabled = this.checked;
                    document.getElementById(instanceId + '-qe-ora-fine').disabled = this.checked;
                });
                
                // Submit
                document.getElementById(instanceId + '-quick-edit').addEventListener('submit', function(ev) {
                    ev.preventDefault();
                    
                    var tuttoGiorno = document.getElementById(instanceId + '-qe-tutto-giorno').checked;
                    var dataInizioVal = document.getElementById(instanceId + '-qe-data-inizio').value;
                    var dataFineVal = document.getElementById(instanceId + '-qe-data-fine').value;
                    
                    if (!tuttoGiorno) {
                        dataInizioVal += ' ' + document.getElementById(instanceId + '-qe-ora-inizio').value + ':00';
                        dataFineVal += ' ' + document.getElementById(instanceId + '-qe-ora-fine').value + ':00';
                    } else {
                        dataInizioVal += ' 00:00:00';
                        dataFineVal += ' 23:59:59';
                    }
                    
                    // Raccogli sub-calendari selezionati
                    var subcalIds = [];
                    document.querySelectorAll('#' + instanceId + '-quick-edit input[name="subcal"]:checked').forEach(function(cb) {
                        subcalIds.push(parseInt(cb.value));
                    });
                    
                    var data = {
                        titolo: document.getElementById(instanceId + '-qe-titolo').value,
                        descrizione: document.getElementById(instanceId + '-qe-descrizione').value,
                        data_inizio: dataInizioVal,
                        data_fine: dataFineVal,
                        tutto_giorno: tuttoGiorno ? 1 : 0,
                        sub_calendario_ids: subcalIds,
                        visibilita: document.getElementById(instanceId + '-qe-visibilita').value
                    };
                    
                    fetch(config.apiUrl + '/eventi/' + eventId, {
                        method: 'PUT',
                        headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                        body: JSON.stringify(data)
                    })
                    .then(function(r) { return r.json(); })
                    .then(function(result) {
                        if (result.success) {
                            self.closeModal(instanceId);
                            self.instances[instanceId].calendar.refetchEvents();
                        } else {
                            var msg = document.getElementById(instanceId + '-qe-message');
                            msg.className = 'sc-form-message sc-form-error';
                            msg.textContent = result.message || 'Errore';
                            msg.style.display = 'block';
                        }
                    });
                });
            });
        },
        
        /**
         * Duplica evento
         */
        duplicateEvent: function(instanceId, eventId) {
            var self = this;
            var config = this.getConfig();
            
            fetch(config.apiUrl + '/eventi/' + eventId, {
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(e) {
                // Crea copia con data +1 giorno
                var startDate = new Date(e.data_inizio);
                startDate.setDate(startDate.getDate() + 1);
                var endDate = new Date(e.data_fine);
                endDate.setDate(endDate.getDate() + 1);
                
                var formatDate = function(d) {
                    return d.toISOString().slice(0, 10) + ' ' + d.toTimeString().slice(0, 8);
                };
                
                var data = {
                    titolo: e.titolo + ' (copia)',
                    descrizione: e.descrizione,
                    data_inizio: formatDate(startDate),
                    data_fine: formatDate(endDate),
                    tutto_giorno: e.tutto_giorno,
                    plesso_id: e.plesso_id,
                    visibilita: e.visibilita,
                    responsabile: e.responsabile,
                    luogo_scuola: e.luogo_scuola,
                    luogo_fisico: e.luogo_fisico,
                    sub_calendario_ids: e.sub_calendario_ids || []
                };
                
                fetch(config.apiUrl + '/eventi', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json', 'X-WP-Nonce': config.nonce },
                    body: JSON.stringify(data)
                })
                .then(function(r) { return r.json(); })
                .then(function(result) {
                    if (result.success) {
                        self.closeModal(instanceId);
                        self.instances[instanceId].calendar.refetchEvents();
                        // Apri edit del nuovo evento
                        setTimeout(function() {
                            self.openEditModal(instanceId, result.evento.id);
                        }, 300);
                    } else {
                        alert('Errore duplicazione: ' + (result.message || 'errore sconosciuto'));
                    }
                });
            });
        },
        
        /**
         * Elimina evento
         */
        deleteEvent: function(instanceId, eventId) {
            if (!confirm('Sei sicuro di voler eliminare questo evento?')) {
                return;
            }
            
            var self = this;
            var config = this.getConfig();
            
            fetch(config.apiUrl + '/eventi/' + eventId, {
                method: 'DELETE',
                headers: { 'X-WP-Nonce': config.nonce }
            })
            .then(function(r) { return r.json(); })
            .then(function(result) {
                if (result.success) {
                    self.closeModal(instanceId);
                    self.instances[instanceId].calendar.refetchEvents();
                } else {
                    alert('Errore eliminazione: ' + (result.message || 'errore sconosciuto'));
                }
            })
            .catch(function(err) {
                alert('Errore: ' + err.message);
            });
        },
        
        /**
         * Setup modal
         */
        setupModal: function(instanceId) {
            var self = this;
            var modal = document.getElementById(instanceId + '-modal');
            
            if (!modal) return;
            
            // Chiudi con overlay
            modal.querySelector('.sc-modal-overlay').addEventListener('click', function() {
                self.closeModal(instanceId);
            });
            
            // Chiudi con X
            modal.querySelector('.sc-modal-close').addEventListener('click', function() {
                self.closeModal(instanceId);
            });
            
            // Chiudi con ESC
            document.addEventListener('keydown', function(e) {
                if (e.key === 'Escape' && modal.style.display !== 'none') {
                    self.closeModal(instanceId);
                }
            });
        },
        
        /**
         * Mostra modal con lista eventi (per selezione)
         */
        showEventListModal: function(instanceId, date, events) {
            var self = this;
            var modal = document.getElementById(instanceId + '-modal');
            var body = modal.querySelector('.sc-modal-body');
            
            var dateStr = this.formatDate(date);
            
            var html = '<div class="sc-event-list-select">';
            html += '<h2>' + dateStr + '</h2>';
            html += '<p class="sc-event-list-subtitle">' + events.length + ' eventi in questa data. Seleziona per vedere i dettagli:</p>';
            html += '<ul class="sc-event-list-items">';
            
            events.forEach(function(event, index) {
                var props = event.extendedProps || {};
                var timeStr = '';
                
                if (!event.allDay) {
                    timeStr = '<span class="sc-event-time">' + self.formatTime(event.start) + '</span>';
                } else {
                    timeStr = '<span class="sc-event-time sc-all-day">Tutto il giorno</span>';
                }
                
                var sourceClass = 'sc-source-' + (props.source || 'local');
                var visClass = 'sc-vis-' + (props.visibilita || 'pubblico');
                
                html += '<li class="sc-event-list-item ' + sourceClass + ' ' + visClass + '" data-index="' + index + '">';
                html += timeStr;
                html += '<span class="sc-event-title-list">' + self.escapeHtml(event.title) + '</span>';
                if (props.visibilita === 'privato') {
                    html += '<span class="sc-badge-small sc-badge-privato">Privato</span>';
                }
                html += '<span class="sc-event-arrow">›</span>';
                html += '</li>';
            });
            
            html += '</ul>';
            html += '</div>';
            
            body.innerHTML = html;
            
            // Aggiungi click handlers
            var items = body.querySelectorAll('.sc-event-list-item');
            items.forEach(function(item) {
                item.addEventListener('click', function() {
                    var index = parseInt(this.dataset.index);
                    self.showEventModal(instanceId, events[index]);
                });
            });
            
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        },
        
        /**
         * Mostra modal dettaglio evento
         */
        showEventModal: function(instanceId, event) {
            var self = this;
            var modal = document.getElementById(instanceId + '-modal');
            var body = modal.querySelector('.sc-modal-body');
            var props = event.extendedProps || {};
            
            // Formatta date
            var startDate = event.start;
            var endDate = event.end || event.start;
            var dateStr = this.formatDate(startDate);
            
            if (!event.allDay) {
                dateStr += ' · ' + this.formatTime(startDate);
                if (endDate && endDate.getTime() !== startDate.getTime()) {
                    // Se stesso giorno, mostra solo ora fine
                    if (startDate.toDateString() === endDate.toDateString()) {
                        dateStr += ' - ' + this.formatTime(endDate);
                    } else {
                        dateStr += ' - ' + this.formatDate(endDate) + ' ' + this.formatTime(endDate);
                    }
                }
            } else {
                dateStr += ' · Tutto il giorno';
            }
            
            // Costruisci HTML
            var html = '<div class="sc-event-detail">';
            
            // Pulsante indietro se veniamo da lista
            html += '<button class="sc-back-to-list" style="display:none;">← Torna alla lista</button>';
            
            html += '<h2 class="sc-event-title">' + this.escapeHtml(event.title) + '</h2>';
            html += '<div class="sc-event-meta">';
            html += '<div class="sc-meta-row"><svg class="sc-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M19 4h-1V2h-2v2H8V2H6v2H5c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h14c1.1 0 2-.9 2-2V6c0-1.1-.9-2-2-2zm0 16H5V9h14v11z"/></svg> <span>' + dateStr + '</span></div>';
            
            // Sub-calendari
            if (props.sub_calendari && props.sub_calendari.length > 0) {
                html += '<div class="sc-meta-row"><svg class="sc-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M17 3H7c-1.1 0-2 .9-2 2v14c0 1.1.9 2 2 2h10c1.1 0 2-.9 2-2V5c0-1.1-.9-2-2-2zm0 16H7V5h10v14z"/></svg> ';
                html += '<span class="sc-sub-calendari">';
                props.sub_calendari.forEach(function(sc) {
                    html += '<span class="sc-sub-cal-badge" style="background-color:' + sc.colore + '">' + self.escapeHtml(sc.nome) + '</span> ';
                });
                html += '</span></div>';
            }
            
            // Responsabile
            if (props.responsabile) {
                html += '<div class="sc-meta-row"><svg class="sc-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 12c2.21 0 4-1.79 4-4s-1.79-4-4-4-4 1.79-4 4 1.79 4 4 4zm0 2c-2.67 0-8 1.34-8 4v2h16v-2c0-2.66-5.33-4-8-4z"/></svg> <span>' + this.escapeHtml(props.responsabile) + '</span></div>';
            }
            
            // Luogo scuola
            if (props.luogo_scuola) {
                html += '<div class="sc-meta-row"><svg class="sc-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 3L1 9l4 2.18v6L12 21l7-3.82v-6l2-1.09V17h2V9L12 3zm6.82 6L12 12.72 5.18 9 12 5.28 18.82 9zM17 15.99l-5 2.73-5-2.73v-3.72L12 15l5-2.73v3.72z"/></svg> <span>' + this.escapeHtml(props.luogo_scuola) + '</span></div>';
            }
            
            // Luogo fisico con link mappa
            if (props.luogo_fisico) {
                var mapLink = '';
                if (props.luogo_lat && props.luogo_lng) {
                    mapLink = ' <a href="https://www.google.com/maps?q=' + props.luogo_lat + ',' + props.luogo_lng + '" target="_blank" class="sc-map-link">🗺️</a>';
                } else {
                    mapLink = ' <a href="https://www.google.com/maps/search/' + encodeURIComponent(props.luogo_fisico) + '" target="_blank" class="sc-map-link">🗺️</a>';
                }
                html += '<div class="sc-meta-row"><svg class="sc-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> <span>' + this.escapeHtml(props.luogo_fisico) + mapLink + '</span></div>';
            }
            
            // Plesso (se non c'è luogo scuola)
            if (!props.luogo_scuola && props.plesso_id && props.plesso) {
                html += '<div class="sc-meta-row"><svg class="sc-icon" viewBox="0 0 24 24" width="16" height="16"><path fill="currentColor" d="M12 2C8.13 2 5 5.13 5 9c0 5.25 7 13 7 13s7-7.75 7-13c0-3.87-3.13-7-7-7zm0 9.5c-1.38 0-2.5-1.12-2.5-2.5s1.12-2.5 2.5-2.5 2.5 1.12 2.5 2.5-1.12 2.5-2.5 2.5z"/></svg> <span>' + this.escapeHtml(props.plesso.nome) + '</span></div>';
            }
            
            html += '</div>';
            
            // Badges
            html += '<div class="sc-event-badges">';
            if (props.visibilita === 'privato') {
                html += '<span class="sc-badge sc-badge-privato">Riservato</span>';
            }
            if (props.source === 'google') {
                html += '<span class="sc-badge sc-badge-google">Google Calendar</span>';
            } else if (props.source === 'ical') {
                html += '<span class="sc-badge sc-badge-ical">iCal</span>';
            } else if (props.source === 'booking') {
                html += '<span class="sc-badge sc-badge-booking">Prenotazione</span>';
            }
            html += '</div>';
            
            // Classi associate
            if (props.classi && props.classi.length > 0) {
                html += '<div class="sc-event-classi">';
                html += '<strong>Classi:</strong> ';
                html += props.classi.map(function(c) { return c.nome; }).join(', ');
                html += '</div>';
            }
            
            // Descrizione
            if (props.descrizione) {
                html += '<div class="sc-event-description">' + this.nl2br(this.escapeHtml(props.descrizione)) + '</div>';
            }
            
            // Pulsanti esportazione
            html += '<div class="sc-export-buttons">';
            html += '<span class="sc-export-label">Aggiungi al calendario:</span>';
            html += '<a href="' + this.generateGoogleCalendarUrl(event) + '" target="_blank" class="sc-export-btn sc-export-google" title="Google Calendar">📅 Google</a>';
            html += '<a href="' + this.generateIcsUrl(event) + '" download="evento.ics" class="sc-export-btn sc-export-ics" title="Apple Calendar / Outlook">📱 iCal</a>';
            html += '</div>';
            
            html += '</div>';
            
            body.innerHTML = html;
            modal.style.display = 'flex';
            document.body.style.overflow = 'hidden';
        },
        
        /**
         * Genera URL per Google Calendar
         */
        generateGoogleCalendarUrl: function(event) {
            var props = event.extendedProps || {};
            var startDate = event.start;
            var endDate = event.end || new Date(startDate.getTime() + 3600000); // +1 ora se non c'è fine
            
            // Formatta date per Google Calendar (YYYYMMDDTHHmmssZ o YYYYMMDD per tutto il giorno)
            var formatDate = function(date, allDay) {
                if (allDay) {
                    return date.toISOString().replace(/[-:]/g, '').split('T')[0];
                }
                return date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
            };
            
            var dates = formatDate(startDate, event.allDay) + '/' + formatDate(endDate, event.allDay);
            
            var params = {
                action: 'TEMPLATE',
                text: event.title,
                dates: dates,
                details: props.descrizione || '',
                location: props.luogo_fisico || props.luogo_scuola || ''
            };
            
            var queryString = Object.keys(params).map(function(key) {
                return encodeURIComponent(key) + '=' + encodeURIComponent(params[key]);
            }).join('&');
            
            return 'https://calendar.google.com/calendar/render?' + queryString;
        },
        
        /**
         * Genera file ICS per Apple Calendar / Outlook
         */
        generateIcsUrl: function(event) {
            var props = event.extendedProps || {};
            var startDate = event.start;
            var endDate = event.end || new Date(startDate.getTime() + 3600000);
            
            // Formatta date per ICS
            var formatIcsDate = function(date, allDay) {
                if (allDay) {
                    return date.toISOString().replace(/[-:]/g, '').split('T')[0];
                }
                return date.toISOString().replace(/[-:]/g, '').replace(/\.\d{3}/, '');
            };
            
            var location = props.luogo_fisico || props.luogo_scuola || '';
            var description = props.descrizione || '';
            if (props.responsabile) {
                description += (description ? '\\n' : '') + 'Responsabile: ' + props.responsabile;
            }
            
            var icsContent = [
                'BEGIN:VCALENDAR',
                'VERSION:2.0',
                'PRODID:-//School Calendar//IT',
                'CALSCALE:GREGORIAN',
                'METHOD:PUBLISH',
                'BEGIN:VEVENT',
                'DTSTART' + (event.allDay ? ';VALUE=DATE:' : ':') + formatIcsDate(startDate, event.allDay),
                'DTEND' + (event.allDay ? ';VALUE=DATE:' : ':') + formatIcsDate(endDate, event.allDay),
                'SUMMARY:' + event.title.replace(/[,;]/g, '\\$&'),
                'DESCRIPTION:' + description.replace(/[,;]/g, '\\$&').replace(/\n/g, '\\n'),
                'LOCATION:' + location.replace(/[,;]/g, '\\$&'),
                'UID:' + event.id + '@school-calendar',
                'END:VEVENT',
                'END:VCALENDAR'
            ].join('\r\n');
            
            var blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
            return URL.createObjectURL(blob);
        },
        
        /**
         * Mostra tooltip hover
         */
        showEventTooltip: function(event, el) {
            var props = event.extendedProps || {};
            var self = this;
            
            // Rimuovi tooltip esistente
            this.hideEventTooltip();
            
            // Crea tooltip
            var tooltip = document.createElement('div');
            tooltip.className = 'sc-event-tooltip';
            tooltip.id = 'sc-tooltip';
            
            var html = '<div class="sc-tooltip-title">' + this.escapeHtml(event.title) + '</div>';
            
            // Orario
            var startDate = event.start;
            var endDate = event.end || event.start;
            if (!event.allDay) {
                html += '<div class="sc-tooltip-time">' + this.formatTime(startDate);
                if (endDate && endDate.getTime() !== startDate.getTime()) {
                    html += ' - ' + this.formatTime(endDate);
                }
                html += '</div>';
            }
            
            // Sub-calendari
            if (props.sub_calendari && props.sub_calendari.length > 0) {
                html += '<div class="sc-tooltip-subcal">';
                props.sub_calendari.forEach(function(sc) {
                    html += '<span class="sc-sub-cal-badge" style="background-color:' + sc.colore + '">' + self.escapeHtml(sc.nome) + '</span> ';
                });
                html += '</div>';
            }
            
            // Responsabile
            if (props.responsabile) {
                html += '<div class="sc-tooltip-row">👤 ' + this.escapeHtml(props.responsabile) + '</div>';
            }
            
            // Luogo
            if (props.luogo_scuola) {
                html += '<div class="sc-tooltip-row">📍 ' + this.escapeHtml(props.luogo_scuola) + '</div>';
            } else if (props.luogo_fisico) {
                html += '<div class="sc-tooltip-row">📍 ' + this.escapeHtml(props.luogo_fisico) + '</div>';
            }
            
            tooltip.innerHTML = html;
            document.body.appendChild(tooltip);
            
            // Posiziona tooltip
            var rect = el.getBoundingClientRect();
            tooltip.style.top = (rect.top + window.scrollY - tooltip.offsetHeight - 5) + 'px';
            tooltip.style.left = (rect.left + window.scrollX + (rect.width / 2) - (tooltip.offsetWidth / 2)) + 'px';
            
            // Verifica che non esca dallo schermo
            var tooltipRect = tooltip.getBoundingClientRect();
            if (tooltipRect.top < 0) {
                tooltip.style.top = (rect.bottom + window.scrollY + 5) + 'px';
            }
            if (tooltipRect.left < 0) {
                tooltip.style.left = '5px';
            }
            if (tooltipRect.right > window.innerWidth) {
                tooltip.style.left = (window.innerWidth - tooltip.offsetWidth - 5) + 'px';
            }
        },
        
        /**
         * Nascondi tooltip
         */
        hideEventTooltip: function() {
            var tooltip = document.getElementById('sc-tooltip');
            if (tooltip) {
                tooltip.remove();
            }
        },
        
        /**
         * Chiudi modal
         */
        closeModal: function(instanceId) {
            var modal = document.getElementById(instanceId + '-modal');
            modal.style.display = 'none';
            document.body.style.overflow = '';
        },
        
        /**
         * Aggiorna date evento (drag & drop)
         */
        updateEventDates: function(info) {
            var self = this;
            var event = info.event;
            var props = event.extendedProps || {};
            
            // Solo eventi locali
            if (props.source !== 'local') {
                info.revert();
                alert('Gli eventi esterni non possono essere modificati');
                return;
            }
            
            var data = {
                data_inizio: this.formatDateTime(event.start),
                data_fine: this.formatDateTime(event.end || event.start)
            };
            
            fetch(this.getConfig().apiUrl + '/eventi/' + event.id, {
                method: 'PUT',
                headers: {
                    'Content-Type': 'application/json',
                    'X-WP-Nonce': this.getConfig().nonce
                },
                body: JSON.stringify(data)
            })
            .then(function(response) {
                if (!response.ok) {
                    throw new Error('Update failed');
                }
                return response.json();
            })
            .catch(function(error) {
                console.error('Error updating event:', error);
                info.revert();
                alert('Errore durante l\'aggiornamento');
            });
        },
        
        /**
         * Helpers
         */
        formatDate: function(date) {
            var options = { weekday: 'long', year: 'numeric', month: 'long', day: 'numeric' };
            return date.toLocaleDateString(this.getConfig().locale || 'it-IT', options);
        },
        
        formatTime: function(date) {
            return date.toLocaleTimeString(this.getConfig().locale || 'it-IT', { hour: '2-digit', minute: '2-digit' });
        },
        
        formatDateTime: function(date) {
            var pad = function(n) { return n < 10 ? '0' + n : n; };
            return date.getFullYear() + '-' + 
                   pad(date.getMonth() + 1) + '-' + 
                   pad(date.getDate()) + ' ' +
                   pad(date.getHours()) + ':' + 
                   pad(date.getMinutes()) + ':' + 
                   pad(date.getSeconds());
        },
        
        escapeHtml: function(text) {
            var div = document.createElement('div');
            div.textContent = text;
            return div.innerHTML;
        },
        
        nl2br: function(text) {
            return text.replace(/\n/g, '<br>');
        }
    };
    
})();
