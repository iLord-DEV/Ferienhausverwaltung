document.addEventListener('DOMContentLoaded', function () {
  // Chart-Daten aus PHP
  const ctx = document.getElementById('verbrauchChart').getContext('2d');
  new Chart(ctx, {
    type: 'bar', // Typ des Diagramms (Bar, Line, Pie etc.)
    data: {
      labels: wueChartData.labels,
      datasets: [
        {
          label: 'Verbrauch pro Kopf (kWh)',
          data: wueChartData.data,
          backgroundColor: [
            'rgba(75, 192, 192, 0.2)',
            'rgba(54, 162, 235, 0.2)',
            'rgba(255, 206, 86, 0.2)',
            'rgba(255, 99, 132, 0.2)',
          ],
          borderColor: [
            'rgba(75, 192, 192, 1)',
            'rgba(54, 162, 235, 1)',
            'rgba(255, 206, 86, 1)',
            'rgba(255, 99, 132, 1)',
          ],
          borderWidth: 1,
        },
      ],
    },
    options: {
      responsive: true,
      scales: {
        y: {
          beginAtZero: true,
        },
      },
    },
  });
});
