document.addEventListener('DOMContentLoaded', function() {
    const eventsContainer = document.getElementById('eventsContainer');
    const emptyState = document.getElementById('emptyState');
    const loadingIndicator = document.getElementById('loadingIndicator');
    const messageContainer = document.getElementById('messageContainer');
    const alertModal = document.getElementById('alertModal');
    const alertSound = document.getElementById('alertSound');
    const alertMessage = document.getElementById('alertMessage');
    const closeBtn = document.querySelector('.close');
    const searchInput = document.getElementById('searchEvents');
    const filterButtons = document.querySelectorAll('.filter-btn');

    let allEvents = [];
    let filteredEvents = [];
    let currentFilter = 'all';

    // Cargar eventos al iniciar
    loadEvents();

    // Verificar alertas cada minuto
    setInterval(checkAlerts, 60000);
    checkAlerts(); // Verificar inmediatamente

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

    // Cerrar modal
    if (closeBtn) {
        closeBtn.addEventListener('click', closeAlert);
    }

    window.addEventListener('click', function(e) {
        if (e.target === alertModal) {
            closeAlert();
        }
    });

    async function loadEvents() {
        try {
            loadingIndicator.style.display = 'block';
            eventsContainer.style.display = 'none';
            emptyState.style.display = 'none';

            const response = await fetch('get_events.php');

            if (!response.ok) {
                throw new Error(`Error ${response.status}`);
            }

            const contentType = response.headers.get('content-type');
            if (!contentType || !contentType.includes('application/json')) {
                throw new Error('Respuesta no es JSON válido');
            }

            const events = await response.json();

            if (!Array.isArray(events)) {
                throw new Error('Formato de datos incorrecto');
            }

            allEvents = events;
            filteredEvents = events;
            renderEvents(events);

        } catch (error) {
            console.error('Error al cargar eventos:', error);
            showMessage('❌ Error al cargar eventos. Por favor intente nuevamente.', 'error');
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
    }

    function createEventCard(event) {
        const card = document.createElement('div');
        card.className = 'event-card';

        // Determinar estado del evento
        const status = getEventStatus(event.hora_inicio, event.hora_fin);
        card.classList.add(`status-${status}`);

        // Formatear horas
        const horaInicio = formatTime(event.hora_inicio);
        const horaFin = formatTime(event.hora_fin);

        // Formatear fecha
        const fecha = event.created_at ? new Date(event.created_at) : new Date();
        const fechaFormateada = fecha.toLocaleDateString('es-ES', {
            year: 'numeric',
            month: 'long',
            day: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });

        card.innerHTML = `
            <div class="event-card-header">
                <div>
                    <div class="event-location">📍 ${escapeHtml(event.ubicacion)}</div>
                    <div class="event-trainer">👨‍🏫 ${escapeHtml(event.formador)}</div>
                </div>
                <span class="event-badge badge-${status}">${getStatusText(status)}</span>
            </div>
            
            <div class="event-details">
                <div class="event-detail">
                    <strong>🕐 Inicio:</strong> ${horaInicio}
                </div>
                <div class="event-detail">
                    <strong>🕑 Fin:</strong> ${horaFin}
                </div>
                <div class="event-detail">
                    <strong>📅 Creado:</strong> ${fechaFormateada}
                </div>
            </div>
            
            <div class="event-actions">
                ${status === 'finished' ? `
                    <button class="btn-delete" onclick="deleteEvent(${event.id})">
                        🗑️ Eliminar
                    </button>
                ` : ''}
            </div>
        `;

        return card;
    }

    function getEventStatus(hora_inicio, hora_fin) {
        const now = new Date();
        const currentHour = now.getHours();
        const currentMinute = now.getMinutes();

        const [startHour, startMinute] = hora_inicio.split(':').map(Number);
        const [endHour, endMinute] = hora_fin.split(':').map(Number);

        const currentTotalMinutes = currentHour * 60 + currentMinute;
        const startTotalMinutes = startHour * 60 + startMinute;
        const endTotalMinutes = endHour * 60 + endMinute;

        if (currentTotalMinutes >= endTotalMinutes) {
            return 'finished';
        } else if (currentTotalMinutes >= startTotalMinutes) {
            return 'active';
        } else {
            return 'pending';
        }
    }

    function getStatusText(status) {
        const texts = {
            'active': 'En Curso',
            'pending': 'Pendiente',
            'finished': 'Finalizado'
        };
        return texts[status] || 'Desconocido';
    }

    function applyFilters() {
        let filtered = allEvents;

        // Aplicar búsqueda
        const searchTerm = searchInput.value.toLowerCase().trim();
        if (searchTerm) {
            filtered = filtered.filter(event =>
                event.ubicacion.toLowerCase().includes(searchTerm) ||
                event.formador.toLowerCase().includes(searchTerm)
            );
        }

        // Aplicar filtro por estado
        if (currentFilter !== 'all') {
            filtered = filtered.filter(event => {
                const status = getEventStatus(event.hora_inicio, event.hora_fin);
                return currentFilter === status;
            });
        }

        filteredEvents = filtered;
        renderEvents(filtered);
    }

    async function checkAlerts() {
        try {
            const response = await fetch('check_alerts.php');
            const data = await response.json();

            if (data.alert && data.event) {
                showAlert(data.event, data.message);
            }
        } catch (error) {
            console.error('Error verificando alertas:', error);
        }
    }

    function showAlert(event, customMessage) {
        if (!alertModal || !alertMessage) return;

        const message = customMessage ||
            `🔔 ¡ATENCIÓN!\n\nEl evento en "${event.ubicacion}" con el formador ${event.formador} finaliza en 10 minutos.\n\nHora de finalización: ${formatTime(event.hora_fin)}`;

        alertMessage.textContent = message;

        // Reproducir sonido
        if (alertSound) {
            alertSound.volume = 1.0;
            alertSound.play().catch(e => {
                console.log('Error al reproducir sonido:', e);
                setTimeout(() => {
                    alertSound.play().catch(e => console.log('Segundo intento fallido:', e));
                }, 100);
            });
        }

        // Mostrar modal
        alertModal.style.display = 'flex';

        // Vibración
        if (navigator.vibrate) {
            navigator.vibrate([1000, 500, 1000]);
        }

        // Notificación del sistema
        if ('Notification' in window && Notification.permission === 'granted') {
            new Notification('🔔 Alerta de Evento', {
                body: `El evento en ${event.ubicacion} finaliza en 10 minutos`,
                icon: 'https://cdn-icons-png.flaticon.com/512/1576/1576383.png'
            });
        }
    }

    function closeAlert() {
        if (!alertModal) return;

        alertModal.style.display = 'none';

        if (alertSound) {
            alertSound.pause();
            alertSound.currentTime = 0;
        }
    }

    // Reemplaza la función deleteEvent existente con esta versión mejorada:

    async function deleteEvent(eventId) {
        // Validar ID
        if (!eventId || eventId <= 0) {
            showMessage('❌ ID de evento inválido', 'error');
            return;
        }

        // Confirmar eliminación
        if (!confirm('¿Está seguro de que desea eliminar este evento?\n\nEsta acción no se puede deshacer.')) {
            return;
        }

        try {
            showMessage('Eliminando evento...', 'info');

            // Intentar múltiples métodos para mayor compatibilidad
            const methods = [
                {
                    method: 'POST',
                    url: `delete_event.php`,
                    body: JSON.stringify({ id: eventId })
                },
                {
                    method: 'GET',
                    url: `delete_event.php?id=${eventId}`
                }
            ];

            let response = null;
            let result = null;
            let lastError = null;

            // Intentar con POST primero
            try {
                response = await fetch(methods[0].url, {
                    method: methods[0].method,
                    headers: {
                        'Content-Type': 'application/json',
                    },
                    body: methods[0].body
                });

                if (response.ok) {
                    result = await response.json();
                } else {
                    lastError = await response.text();
                    throw new Error(`HTTP ${response.status}`);
                }
            } catch (error) {
                console.warn('Método POST falló, intentando GET:', error);

                // Intentar con GET
                try {
                    response = await fetch(methods[1].url);

                    if (response.ok) {
                        result = await response.json();
                    } else {
                        lastError = await response.text();
                        throw new Error(`HTTP ${response.status}`);
                    }
                } catch (error2) {
                    console.error('Ambos métodos fallaron:', error2);
                    throw new Error('No se pudo eliminar el evento. Error: ' + (lastError || error2.message));
                }
            }

            // Verificar resultado
            if (result && result.success) {
                showMessage('✅ Evento eliminado correctamente', 'success');

                // Recargar eventos después de un breve delay
                setTimeout(async () => {
                    await loadEvents();
                    // Limpiar mensaje después de recargar
                    setTimeout(() => {
                        messageContainer.innerHTML = '';
                    }, 1000);
                }, 500);

            } else {
                const errorMsg = result?.message || 'Error desconocido al eliminar el evento';
                showMessage('❌ ' + errorMsg, 'error');
                console.error('Respuesta del servidor:', result);
            }

        } catch (error) {
            console.error('Error al eliminar evento:', error);
            showMessage('❌ Error al eliminar el evento: ' + error.message, 'error');
        }
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
        messageContainer.innerHTML = `
            <div class="message ${type}">
                ${message}
            </div>
        `;

        if (type === 'success') {
            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
        }
    }

    function debounce(func, wait) {
        let timeout;
        return function executedFunction(...args) {
            const later = () => {
                clearTimeout(timeout);
                func(...args);
            };
            clearTimeout(timeout);
            timeout = setTimeout(later, wait);
        };
    }

    // Exponer función deleteEvent al ámbito global
    window.deleteEvent = deleteEvent;

    // Solicitar permisos de notificación
    if ('Notification' in window && Notification.permission !== 'granted' && Notification.permission !== 'denied') {
        Notification.requestPermission();
    }
});