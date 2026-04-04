// assets/js/reportes.js
document.addEventListener('DOMContentLoaded', function() {
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
    const ventasPorDiaFechas = JSON.parse(datosDiv.dataset.ventasPorDiaFechas || '[]');
    const ventasPorDiaCantidad = JSON.parse(datosDiv.dataset.ventasPorDiaCantidad || '[]');
    const ingresosVentasPorDia = JSON.parse(datosDiv.dataset.ingresosVentasPorDia || '[]');
    const ingresosCombinados = JSON.parse(datosDiv.dataset.ingresosCombinados || '[]');

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

    // Gráfico de ingresos por servicios
    if (document.getElementById('ingresosPorDiaChart')) {
        new Chart(document.getElementById('ingresosPorDiaChart'), {
            type: 'line',
            data: {
                labels: fechas,
                datasets: [{
                    label: 'Ingresos por Servicios ($)',
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

    // Gráfico de ventas por día
    if (document.getElementById('ventasPorDiaChart')) {
        new Chart(document.getElementById('ventasPorDiaChart'), {
            type: 'bar',
            data: {
                labels: ventasPorDiaFechas,
                datasets: [{
                    label: 'Ventas realizadas',
                    data: ventasPorDiaCantidad,
                    backgroundColor: '#2196f3',
                    borderColor: '#0b5e9e',
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Número de ventas' } },
                    x: { title: { display: true, text: 'Fecha' } }
                }
            }
        });
    }

    // Gráfico de ingresos por ventas
    if (document.getElementById('ingresosVentasPorDiaChart')) {
        new Chart(document.getElementById('ingresosVentasPorDiaChart'), {
            type: 'line',
            data: {
                labels: ventasPorDiaFechas,
                datasets: [{
                    label: 'Ingresos por Ventas ($)',
                    data: ingresosVentasPorDia,
                    backgroundColor: 'rgba(33, 150, 243, 0.2)',
                    borderColor: '#2196f3',
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

    // Gráfico de ingresos combinados (Servicios vs Productos)
    if (document.getElementById('ingresosCombinadosChart') && ingresosCombinados.length > 0) {
        const mesesLabels = ingresosCombinados.map(item => item.mes);
        const serviciosData = ingresosCombinados.map(item => item.ingresos_servicios);
        const ventasData = ingresosCombinados.map(item => item.ingresos_ventas);
        
        new Chart(document.getElementById('ingresosCombinadosChart'), {
            type: 'bar',
            data: {
                labels: mesesLabels,
                datasets: [
                    {
                        label: 'Ingresos por Servicios',
                        data: serviciosData,
                        backgroundColor: '#E68A00',
                        borderColor: '#CC7A00',
                        borderWidth: 1
                    },
                    {
                        label: 'Ingresos por Ventas',
                        data: ventasData,
                        backgroundColor: '#2196f3',
                        borderColor: '#0b5e9e',
                        borderWidth: 1
                    }
                ]
            },
            options: {
                responsive: true,
                scales: {
                    y: { beginAtZero: true, title: { display: true, text: 'Ingresos (MXN)' } },
                    x: { title: { display: true, text: 'Mes' } }
                }
            }
        });
    }
});