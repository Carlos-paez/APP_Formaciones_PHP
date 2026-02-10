document.addEventListener('DOMContentLoaded', function() {
    const eventForm = document.getElementById('eventForm');
    const messageContainer = document.getElementById('messageContainer');

    // Cargar estadísticas al iniciar
    loadStats();

    // Manejar el formulario
    eventForm.addEventListener('submit', async function(e) {
        e.preventDefault();
        await handleSubmit();
    });

    async function handleSubmit() {
        const ubicacion = document.getElementById('ubicacion').value.trim();
        const formador = document.getElementById('formador').value.trim();
        const hora_inicio = document.getElementById('hora_inicio').value;
        const hora_fin = document.getElementById('hora_fin').value;

        // Validaciones
        if (!ubicacion || !formador || !hora_inicio || !hora_fin) {
            showMessage('Por favor complete todos los campos', 'error');
            return;
        }

        if (hora_fin <= hora_inicio) {
            showMessage('La hora de finalización debe ser después de la hora de inicio', 'error');
            return;
        }

        const formData = {
            ubicacion: ubicacion,
            formador: formador,
            hora_inicio: hora_inicio,
            hora_fin: hora_fin
        };

        try {
            showMessage('Guardando evento...', 'info');

            const response = await fetch('save_event.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/json',
                },
                body: JSON.stringify(formData)
            });

            if (!response.ok) {
                throw new Error(`Error ${response.status}`);
            }

            const result = await response.json();

            if (result.success) {
                showMessage('✅ Evento guardado correctamente', 'success');
                eventForm.reset();
                await loadStats();

                // Redirigir después de 2 segundos
                setTimeout(() => {
                    window.location.href = 'events.html';
                }, 2000);
            } else {
                showMessage('❌ ' + (result.message || 'Error al guardar'), 'error');
            }
        } catch (error) {
            console.error('Error:', error);
            showMessage('❌ Error al guardar el evento. Intente nuevamente.', 'error');
        }
    }

    function resetForm() {
        eventForm.reset();
        messageContainer.innerHTML = '';
    }

    async function loadStats() {
        try {
            const response = await fetch('get_events.php');
            const events = await response.json();

            if (!Array.isArray(events)) {
                throw new Error('Formato de datos incorrecto');
            }

            const now = new Date();
            const currentHour = now.getHours();
            const currentMinute = now.getMinutes();

            let activeCount = 0;
            let pendingCount = 0;

            events.forEach(event => {
                const [startHour, startMinute] = event.hora_inicio.split(':').map(Number);
                const [endHour, endMinute] = event.hora_fin.split(':').map(Number);

                const currentTotalMinutes = currentHour * 60 + currentMinute;
                const startTotalMinutes = startHour * 60 + startMinute;
                const endTotalMinutes = endHour * 60 + endMinute;

                if (currentTotalMinutes >= startTotalMinutes && currentTotalMinutes < endTotalMinutes) {
                    activeCount++;
                } else if (currentTotalMinutes < startTotalMinutes) {
                    pendingCount++;
                }
            });

            document.getElementById('totalEvents').textContent = events.length;
            document.getElementById('activeEvents').textContent = activeCount;
            document.getElementById('pendingEvents').textContent = pendingCount;

        } catch (error) {
            console.error('Error cargando estadísticas:', error);
        }
    }

    function showMessage(message, type) {
        messageContainer.innerHTML = `
            <div class="message ${type}">
                ${message}
            </div>
        `;

        // Auto-ocultar después de 5 segundos para mensajes de éxito
        if (type === 'success') {
            setTimeout(() => {
                messageContainer.innerHTML = '';
            }, 5000);
        }
    }
});