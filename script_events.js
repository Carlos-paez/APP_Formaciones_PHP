document.addEventListener('DOMContentLoaded', function() {
    // Elementos del DOM
    const eventsContainer = document.getElementById('eventsContainer');
    const emptyState = document.getElementById('emptyState');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const messageContainer = document.getElementById('messageContainer');
    const warningModal = document.getElementById('warningModal');
    const equipmentModal = document.getElementById('equipmentModal');
    const alertSound = document.getElementById('alertSound');
    const finishedSound = document.getElementById('finishedSound');
    const warningMessage = document.getElementById('warningMessage');
    const equipmentMessage = document.getElementById('equipmentMessage');
    const timeCounter = document.getElementById('timeCounter');
    const warningCloseBtn = document.querySelector('.warning-close');
    const equipmentCloseBtn = document.querySelector('.equipment-close');
    const searchInput = document.getElementById('searchEvents');
    const filterButtons = document.querySelectorAll('.filter-btn');
    
    // Variables de estado
    let allEvents = [];
    let filteredEvents = [];
    let currentFilter = 'all';
    let countdownInterval = null;
    let warningEvent = null;
    let processedAlerts = new Set();
    let lastStatusUpdate = 0;
    let soundInterval = null;
    const STATUS_UPDATE_INTERVAL = 30000;
    const ALERT_CHECK_INTERVAL = 5000;
    
    // Cargar eventos al iniciar
    loadEvents();
    
    // Verificar alertas cada 5 segundos
    setInterval(checkAlerts, ALERT_CHECK_INTERVAL);
    checkAlerts();
    
    // Actualizar estados automáticamente cada 30 segundos
    setInterval(updateEventStatuses, STATUS_UPDATE_INTERVAL);
    
    // Actualizar al recuperar el foco de la pestaña
    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'visible') {
            loadEvents();
            checkAlerts();
        }
    });
    
    // Manejar filtros
    filterButtons.forEach(btn => {
        btn.addEventListener('click', function() {
            filterButtons.forEach(b => b.classList.remove('active'));
            this.classList.add('active');
            currentFilter = this.dataset.filter;
            applyFilters();
        });
    });
    
    // Manejar búsqueda
    searchInput.addEventListener('input', debounce(applyFilters, 300));
    
    // Cerrar modales
    if (warningCloseBtn) {
        warningCloseBtn.addEventListener('click', closeWarning);
    }
    
    if (equipmentCloseBtn) {
        equipmentCloseBtn.addEventListener('click', closeEquipmentModal);
    }
    
    window.addEventListener('click', function(e) {
        if (e.target === warningModal) closeWarning();
        if (e.target === equipmentModal) closeEquipmentModal();
    });
    
    // ==================== FUNCIONES PRINCIPALES ====================
    
    async function loadEvents() {
        try {
            loadingIndicator.style.display = 'block';
            eventsContainer.style.display = 'none';
            emptyState.style.display = 'none';
            
            const response = await fetch('get_events.php?_=' + Date.now(), {
                headers: { 'Cache-Control': 'no-cache' }
            });
            
            if (!response.ok) throw new Error(`Error ${response.status}`);
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Respuesta no es JSON válido');
            }
            
            const events = await response.json();
            if (!Array.isArray(events)) throw new Error('Formato de datos incorrecto');
            
            allEvents = events;
            filteredEvents = events;
            renderEvents(events);
            
        } catch (error) {
            console.error('Error al cargar eventos:', error);
            showMessage(`❌ Error al cargar eventos: ${error.message}`, 'error');
            eventsContainer.innerHTML = '';
        } finally {
            loadingIndicator.style.display = 'none';
        }
    }
    
    function renderEvents(events) {
        eventsContainer.innerHTML = '';
        
        if (events.length === 0) {
            eventsContainer.style.display = 'none';
            emptyState.style.display = 'block';
            return;
        }
        
        eventsContainer.style.display = 'grid';
        emptyState.style.display = 'none';
        
        events.forEach(event => {
            const eventCard = createEventCard(event);
            eventsContainer.appendChild(eventCard);
        });
        
        // Reasignar eventos después de renderizar
        setupDeleteEventListeners();
        
        lastStatusUpdate = Date.now();
    }
    
    function createEventCard(event) {
        const card = document.createElement('div');
        card.className = 'event-card';
        card.dataset.eventId = event.id;
        
        const status = getEventStatus(event.hora_inicio, event.hora_fin);
        card.classList.add(`status-${status}`);
        
        const horaInicio = formatTime(event.hora_inicio);
        const horaFin = formatTime(event.hora_fin);
        
        const fecha = event.created_at ? new Date(event.created_at) : new Date();
        const fechaFormateada = fecha.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric'
        });
        
        card.innerHTML = `
            <div class="event-card-header">
                <div>
                    <div class="event-location">📍 ${escapeHtml(event.ubicacion)}</div>
                    <div class="event-trainer">👨‍🏫 ${escapeHtml(event.formador)}</div>
                </div>
                <span class="event-badge badge-${status}" data-status="${status}">
                    ${getStatusText(status)}
                </span>
            </div>
            
            <div class="event-details">
                <div class="event-detail">
                    <strong>🕐 Inicio:</strong> ${horaInicio}
                </div>
                <div class="event-detail">
                    <strong>🕑 Fin:</strong> ${horaFin}
                </div>
                <div class="event-detail">
                    <strong>📅 Fecha:</strong> ${fechaFormateada}
                </div>
            </div>
            
            <div class="event-actions">
                ${status === 'finished' ? `
                    <button class="btn-delete" data-event-id="${event.id}">
                        🗑️ Eliminar evento
                    </button>
                ` : `
                    <button class="btn-info" disabled>
                        ${status === 'active' ? '✅ En curso' : '⏳ Pendiente'}
                    </button>
                `}
            </div>
        `;
        
        return card;
    }
    
    // Configurar listeners para botones de eliminar
    function setupDeleteEventListeners() {
        document.querySelectorAll('.btn-delete').forEach(button => {
            // Remover listener anterior si existe para evitar duplicados
            const clonedButton = button.cloneNode(true);
            button.parentNode.replaceChild(clonedButton, button);
            
            // Agregar nuevo listener
            clonedButton.addEventListener('click', function() {
                const eventId = this.dataset.eventId;
                if (eventId) {
                    confirmAndDeleteEvent(parseInt(eventId));
                }
            });
        });
    }
    
    // Actualización automática de estados SIN recargar toda la lista
    function updateEventStatuses() {
        const now = Date.now();
        if (now - lastStatusUpdate < STATUS_UPDATE_INTERVAL - 1000) return;
        
        let changesDetected = false;
        
        document.querySelectorAll('.event-card').forEach(card => {
            const eventId = card.dataset.eventId;
            const event = allEvents.find(e => e.id == eventId);
            if (!event) return;
            
            const newStatus = getEventStatus(event.hora_inicio, event.hora_fin);
            const badge = card.querySelector('.event-badge');
            const currentStatus = badge ? badge.dataset.status : null;
            
            // Actualizar solo si el estado cambió
            if (newStatus !== currentStatus) {
                changesDetected = true;
                
                // Actualizar clase del contenedor
                card.className = 'event-card status-' + newStatus;
                
                // Actualizar badge
                if (badge) {
                    badge.className = 'event-badge badge-' + newStatus;
                    badge.dataset.status = newStatus;
                    badge.textContent = getStatusText(newStatus);
                }
                
                // Actualizar botón de acciones
                const actionsDiv = card.querySelector('.event-actions');
                if (newStatus === 'finished') {
                    actionsDiv.innerHTML = `
                        <button class="btn-delete" data-event-id="${eventId}">
                            🗑️ Eliminar evento
                        </button>
                    `;
                } else {
                    actionsDiv.innerHTML = `
                        <button class="btn-info" disabled>
                            ${newStatus === 'active' ? '✅ En curso' : '⏳ Pendiente'}
                        </button>
                    `;
                }
            }
        });
        
        // Reconfigurar listeners si hubo cambios
        if (changesDetected) {
            setupDeleteEventListeners();
            console.log('✅ Estados de eventos actualizados automáticamente');
        }
        
        lastStatusUpdate = now;
    }
    
    async function checkAlerts() {
        try {
            const response = await fetch('check_alerts.php?_=' + Date.now(), {
                headers: { 'Cache-Control': 'no-cache' }
            });
            
            if (!response.ok) throw new Error(`HTTP ${response.status}`);
            
            const data = await response.json();
            if (!data.alerts || !Array.isArray(data.alerts)) return;
            
            data.alerts.forEach(alert => {
                const alertKey = `${alert.type}-${alert.event.id}-${Math.floor(Date.now() / 60000)}`;
                
                if (processedAlerts.has(alertKey)) return;
                processedAlerts.add(alertKey);
                
                if (processedAlerts.size > 50) {
                    const nowMinute = Math.floor(Date.now() / 60000);
                    for (let key of processedAlerts) {
                        const alertMinute = parseInt(key.split('-').pop());
                        if (nowMinute - alertMinute > 5) {
                            processedAlerts.delete(key);
                        }
                    }
                }
                
                if (alert.type === 'warning') {
                    showWarning(alert.event, alert.message, alert.minutes_remaining);
                } else if (alert.type === 'finished') {
                    showEquipmentAlert(alert.event, alert.message);
                }
            });
            
        } catch (error) {
            console.error('Error verificando alertas:', error);
        }
    }
    
    function showWarning(event, message, minutesRemaining) {
        if (!warningModal || warningModal.style.display === 'flex') return;
        
        warningEvent = event;
        warningMessage.textContent = message;
        startCountdown(minutesRemaining);
        warningModal.style.display = 'flex';
        
        if (navigator.vibrate) navigator.vibrate([200, 100, 200]);
        showSystemNotification('⏰ Próxima finalización', message.split('\n')[2]);
    }
    
    function showEquipmentAlert(event, customMessage) {
        if (!equipmentModal) return;
        
        const message = customMessage || 
            `🔔 ¡EVENTO FINALIZADO!\n\nEl evento en "${event.ubicacion}" con el formador ${event.formador} ha concluido.\n\n⚠️ ES NECESARIO RECONFIGURAR LOS EQUIPOS PRESTADOS.`;
        
        equipmentMessage.textContent = message;
        equipmentModal.style.display = 'flex';
        
        playFinishedSound();
        
        if (navigator.vibrate) navigator.vibrate([500, 200, 500, 200, 500]);
        
        showSystemNotification('🔧 Equipos necesitan reconfiguración', 
            `Evento finalizado en ${event.ubicacion}. Reconfigurar equipos.`);
        
        setTimeout(loadEvents, 3000);
    }
    
    function playFinishedSound() {
        if (!finishedSound) return;
        
        finishedSound.volume = 1.0;
        finishedSound.pause();
        finishedSound.currentTime = 0;
        
        let count = 0;
        soundInterval = setInterval(() => {
            if (count >= 3 || equipmentModal.style.display !== 'flex') {
                clearInterval(soundInterval);
                soundInterval = null;
                return;
            }
            
            finishedSound.play().catch(e => console.log('Error audio:', e));
            count++;
        }, 1200);
    }
    
    function startCountdown(minutes) {
        if (countdownInterval) clearInterval(countdownInterval);
        
        let seconds = minutes * 60;
        updateCounterDisplay(seconds);
        
        countdownInterval = setInterval(() => {
            seconds--;
            if (seconds < 0) {
                clearInterval(countdownInterval);
                return;
            }
            
            if (seconds <= 120 && timeCounter) {
                timeCounter.classList.add('time-critical');
            }
            
            updateCounterDisplay(seconds);
        }, 1000);
    }
    
    function updateCounterDisplay(totalSeconds) {
        if (!timeCounter) return;
        
        const minutes = Math.floor(totalSeconds / 60);
        const seconds = totalSeconds % 60;
        timeCounter.textContent = `Tiempo restante: ${minutes}:${seconds.toString().padStart(2, '0')}`;
    }
    
    function closeWarning() {
        if (!warningModal) return;
        warningModal.style.display = 'none';
        if (countdownInterval) clearInterval(countdownInterval);
        if (timeCounter) timeCounter.classList.remove('time-critical');
        warningEvent = null;
    }
    
    function closeEquipmentModal() {
        if (!equipmentModal) return;
        equipmentModal.style.display = 'none';
        
        if (soundInterval) {
            clearInterval(soundInterval);
            soundInterval = null;
        }
        
        if (finishedSound) {
            finishedSound.pause();
            finishedSound.currentTime = 0;
        }
    }
    
    function showSystemNotification(title, body) {
        if ('Notification' in window && Notification.permission === 'granted') {
            const notification = new Notification(title, {
                body: body,
                icon: 'https://cdn-icons-png.flaticon.com/512/3771/3771814.png',
                badge: 'https://cdn-icons-png.flaticon.com/512/3771/3771814.png',
                requireInteraction: true,
                silent: false
            });
            
            notification.onclick = () => {
                window.focus();
                notification.close();
            };
            
            setTimeout(() => notification.close(), 10000);
        }
    }
    
    // ==================== ELIMINACIÓN DE EVENTOS ====================
    
    async function confirmAndDeleteEvent(eventId) {
        if (!eventId || eventId <= 0) {
            showMessage('❌ ID de evento inválido', 'error');
            return;
        }
        
        const event = allEvents.find(e => e.id == eventId);
        if (!event) {
            showMessage('❌ Evento no encontrado', 'error');
            return;
        }
        
        const confirmMessage = `¿Está seguro de eliminar este evento?\n\n📍 Ubicación: ${event.ubicacion}\n👨‍🏫 Formador: ${event.formador}\n🕐 Horario: ${event.hora_inicio} - ${event.hora_fin}\n\nEsta acción no se puede deshacer.`;
        
        if (!confirm(confirmMessage)) return;
        
        try {
            showMessage('🗑️ Eliminando evento...', 'info');
            
            const response = await fetch(`delete_event.php?id=${eventId}`, {
                method: 'GET',
                headers: { 
                    'Cache-Control': 'no-cache',
                    'Accept': 'application/json'
                }
            });
            
            if (!response.ok) {
                throw new Error(`Error HTTP: ${response.status}`);
            }
            
            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Respuesta no es JSON válido');
            }
            
            const result = await response.json();
            
            if (result.success) {
                showMessage('✅ Evento eliminado correctamente', 'success');
                
                // Eliminar del array local
                allEvents = allEvents.filter(e => e.id != eventId);
                filteredEvents = filteredEvents.filter(e => e.id != eventId);
                
                // Recargar eventos después de un breve delay
                setTimeout(() => {
                    loadEvents();
                    setTimeout(() => {
                        messageContainer.innerHTML = '';
                    }, 1500);
                }, 500);
                
            } else {
                throw new Error(result.message || 'Error al eliminar el evento');
            }
            
        } catch (error) {
            console.error('Error al eliminar evento:', error);
            showMessage(`❌ ${error.message || 'Error al eliminar el evento'}`, 'error');
            
            // Intentar recargar eventos para sincronizar estado
            setTimeout(loadEvents, 2000);
        }
    }
    
    function deleteEvent(eventId) {
        confirmAndDeleteEvent(eventId);
    }
    
    // ==================== FUNCIONES AUXILIARES ====================
    
    function getEventStatus(hora_inicio, hora_fin) {
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();
        
        const [startHour, startMinute] = hora_inicio.split(':').map(Number);
        const [endHour, endMinute] = hora_fin.split(':').map(Number);
        
        const currentTotal = currentHour * 60 + currentMinute;
        const startTotal = startHour * 60 + startMinute;
        const endTotal = endHour * 60 + endMinute;
        
        if (currentTotal >= endTotal) return 'finished';
        if (currentTotal >= startTotal) return 'active';
        return 'pending';
    }
    
    function getStatusText(status) {
        const texts = {
            'active': '✅ En Curso',
            'pending': '⏳ Pendiente',
            'finished': '🏁 Finalizado'
        };
        return texts[status] || 'Desconocido';
    }
    
    function applyFilters() {
        let filtered = allEvents;
        
        const searchTerm = searchInput.value.toLowerCase().trim();
        if (searchTerm) {
            filtered = filtered.filter(event => 
                event.ubicacion.toLowerCase().includes(searchTerm) ||
                event.formador.toLowerCase().includes(searchTerm)
            );
        }
        
        if (currentFilter !== 'all') {
            filtered = filtered.filter(event => {
                const status = getEventStatus(event.hora_inicio, event.hora_fin);
                return currentFilter === status;
            });
        }
        
        filteredEvents = filtered;
        renderEvents(filtered);
    }
    
    function formatTime(timeString) {
        if (!timeString) return 'N/A';
        const [hours, minutes] = timeString.split(':');
        const hour = parseInt(hours);
        const period = hour >= 12 ? 'PM' : 'AM';
        const displayHour = hour % 12 || 12;
        return `${displayHour}:${minutes} ${period}`;
    }
    
    function escapeHtml(text) {
        if (!text) return '';
        const div = document.createElement('div');
        div.textContent = text;
        return div.innerHTML;
    }
    
    function showMessage(message, type) {
        messageContainer.innerHTML = `<div class="message ${type}">${message}</div>`;
        if (type === 'success') setTimeout(() => messageContainer.innerHTML = '', 4000);
    }
    
    function debounce(func, wait) {
        let timeout;
        return (...args) => {
            clearTimeout(timeout);
            timeout = setTimeout(() => func.apply(this, args), wait);
        };
    }
    
    // ==================== INICIALIZACIÓN ====================
    
    window.deleteEvent = deleteEvent;
    window.closeWarning = closeWarning;
    window.closeEquipmentModal = closeEquipmentModal;
    
    if ('Notification' in window && Notification.permission !== 'granted') {
        Notification.requestPermission().then(permission => {
            if (permission === 'granted') {
                console.log('✅ Notificaciones habilitadas');
            }
        });
    }
    
    console.log('%c✅ Sistema de Gestión de Eventos Iniciado', 'color: #2c5282; font-weight: bold; font-size: 16px;');
    console.log(`📊 Eventos cargados: ${allEvents.length}`);
    console.log(`⏱️ Verificación de alertas: cada ${ALERT_CHECK_INTERVAL/1000} segundos`);
    console.log(`🔄 Actualización de estados: cada ${STATUS_UPDATE_INTERVAL/1000} segundos`);
});