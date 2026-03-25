// assets/js/reportes.js
document.addEventListener('DOMContentLoaded', function() {
    // Obtener datos del div oculto
    const datosDiv = document.getElementById('datos-reportes');
    
    if (!datosDiv) {
        console.error('No se encontraron los datos para reportes');
        return;
    }
    
    const fechas = JSON.parse(datosDiv.dataset.fechas || '[]');
    const citasData = JSON.parse(datosDiv.dataset.citas || '[]');
    const ingresosData = JSON.parse(datosDiv.dataset.ingresos || '[]');
    const meses = JSON.parse(datosDiv.dataset.meses || '[]');
    const citasMensuales = JSON.parse(datosDiv.dataset.citasMensuales || '[]');

    // Gráfico de citas por día
    if (document.getElementById('citasPorDiaChart')) {
        new Chart(document.getElementById('citasPorDiaChart'), {
            type: 'bar',
            data: {
                labels: fechas,
                datasets: [{
                    label: 'Citas agendadas',
                    data: citasData,
                    backgroundColor: '#E68A00',
                    borderColor: '#CC7A00',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Número de citas' } },
                    x: { title: { display: true, text: 'Fecha' } }
                }
            }
        });
    }

    // Gráfico de ingresos por día
    if (document.getElementById('ingresosPorDiaChart')) {
        new Chart(document.getElementById('ingresosPorDiaChart'), {
            type: 'line',
            data: {
                labels: fechas,
                datasets: [{
                    label: 'Ingresos ($)',
                    data: ingresosData,
                    backgroundColor: 'rgba(230, 138, 0, 0.2)',
                    borderColor: '#E68A00',
                    borderWidth: 2,
                    fill: true,
                    tension: 0.3
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Ingresos (MXN)' } },
                    x: { title: { display: true, text: 'Fecha' } }
                }
            }
        });
    }

    // Gráfico de citas por mes
    if (document.getElementById('citasPorMesChart')) {
        new Chart(document.getElementById('citasPorMesChart'), {
            type: 'bar',
            data: {
                labels: meses,
                datasets: [{
                    label: 'Citas por mes',
                    data: citasMensuales,
                    backgroundColor: '#F2A33B',
                    borderColor: '#E68A00',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Número de citas' } },
                    x: { title: { display: true, text: 'Mes' } }
                }
            }
        });
    }
});