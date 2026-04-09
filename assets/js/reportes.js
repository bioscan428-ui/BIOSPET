// assets/js/reportes.js
document.addEventListener('DOMContentLoaded', function() {
    const datosDiv = document.getElementById('datos-reportes');
    
    if (!datosDiv) {
        console.error('No se encontraron los datos para reportes');
        return;
    }
    
    // Obtener datos con valores por defecto seguros
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
    const citasPorDiaChart = document.getElementById('citasPorDiaChart');
    if (citasPorDiaChart && fechas.length > 0 && citasData.length > 0) {
        new Chart(citasPorDiaChart, {
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
                maintainAspectRatio: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Número de citas' } 
                    },
                    x: { 
                        title: { display: true, text: 'Fecha' } 
                    }
                }
            }
        });
    } else if (citasPorDiaChart) {
        console.log('No hay datos de citas por día para mostrar');
    }

    // Gráfico de ingresos por servicios
    const ingresosPorDiaChart = document.getElementById('ingresosPorDiaChart');
    if (ingresosPorDiaChart && fechas.length > 0 && ingresosData.length > 0) {
        new Chart(ingresosPorDiaChart, {
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
                maintainAspectRatio: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Ingresos (MXN)' } 
                    },
                    x: { 
                        title: { display: true, text: 'Fecha' } 
                    }
                }
            }
        });
    }

    // Gráfico de citas por mes
    const citasPorMesChart = document.getElementById('citasPorMesChart');
    if (citasPorMesChart && meses.length > 0 && citasMensuales.length > 0) {
        new Chart(citasPorMesChart, {
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
                maintainAspectRatio: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Número de citas' } 
                    },
                    x: { 
                        title: { display: true, text: 'Mes' } 
                    }
                }
            }
        });
    }

    // Gráfico de ventas por día
    const ventasPorDiaChart = document.getElementById('ventasPorDiaChart');
    if (ventasPorDiaChart && ventasPorDiaFechas.length > 0 && ventasPorDiaCantidad.length > 0) {
        new Chart(ventasPorDiaChart, {
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
                maintainAspectRatio: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Número de ventas' } 
                    },
                    x: { 
                        title: { display: true, text: 'Fecha' } 
                    }
                }
            }
        });
    }

    // Gráfico de ingresos por ventas
    const ingresosVentasPorDiaChart = document.getElementById('ingresosVentasPorDiaChart');
    if (ingresosVentasPorDiaChart && ventasPorDiaFechas.length > 0 && ingresosVentasPorDia.length > 0) {
        new Chart(ingresosVentasPorDiaChart, {
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
                maintainAspectRatio: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Ingresos (MXN)' } 
                    },
                    x: { 
                        title: { display: true, text: 'Fecha' } 
                    }
                }
            }
        });
    }

    // Gráfico de ingresos combinados (Servicios vs Productos)
    const ingresosCombinadosChart = document.getElementById('ingresosCombinadosChart');
    if (ingresosCombinadosChart && ingresosCombinados.length > 0) {
        const mesesLabels = ingresosCombinados.map(item => item.mes);
        const serviciosData = ingresosCombinados.map(item => parseFloat(item.ingresos_servicios) || 0);
        const ventasData = ingresosCombinados.map(item => parseFloat(item.ingresos_ventas) || 0);
        
        new Chart(ingresosCombinadosChart, {
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
                maintainAspectRatio: true,
                scales: {
                    y: { 
                        beginAtZero: true, 
                        title: { display: true, text: 'Ingresos (MXN)' } 
                    },
                    x: { 
                        title: { display: true, text: 'Mes' } 
                    }
                }
            }
        });
    }
});