document.addEventListener('DOMContentLoaded', function () {
  const WUEStatistics = {
    init: function () {
      console.log('Initializing WUE Statistics...'); // DEBUG
      console.log('Data available:', wueStatistics); // DEBUG
      this.initYearlyComparisonChart();
      this.initUsageDistributionChart();
      this.initGroupSizeCorrelationChart();
    },

    initYearlyComparisonChart: function () {
      const ctx = document
        .getElementById('yearlyComparisonChart')
        .getContext('2d');
      const data = wueStatistics.yearlyData;
      console.log('Yearly comparison data:', data); // DEBUG

      new Chart(ctx, {
        type: 'line',
        data: {
          labels: [
            'Jan',
            'Feb',
            'Mar',
            'Apr',
            'Mai',
            'Jun',
            'Jul',
            'Aug',
            'Sep',
            'Okt',
            'Nov',
            'Dez',
          ],
          datasets: [
            {
              label: data.year.toString(),
              data: data.currentYear,
              borderColor: 'rgb(75, 192, 192)',
              tension: 0.1,
            },
            {
              label: data.previousYear.toString(),
              data: data.previousYear,
              borderColor: 'rgb(201, 203, 207)',
              tension: 0.1,
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            title: {
              display: true,
              text: 'Monatlicher Verbrauch (Liter)',
            },
          },
          scales: {
            y: {
              beginAtZero: true,
            },
          },
        },
      });
    },

    initUsageDistributionChart: function () {
      const ctx = document
        .getElementById('usageDistributionChart')
        .getContext('2d');
      const data = wueStatistics.usageDistribution;
      console.log('Usage distribution data:', data); // DEBUG

      new Chart(ctx, {
        type: 'pie',
        data: {
          labels: [
            'Eigener Verbrauch',
            'Verbrauch Andere',
            'Leerlaufverbrauch',
          ],
          datasets: [
            {
              data: [data.ownUsage, data.othersUsage, data.idleUsage],
              backgroundColor: [
                'rgb(54, 162, 235)',
                'rgb(75, 192, 192)',
                'rgb(255, 205, 86)',
              ],
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            legend: {
              position: 'right',
            },
          },
        },
      });
    },

    initGroupSizeCorrelationChart: function () {
      const ctx = document
        .getElementById('groupSizeCorrelationChart')
        .getContext('2d');
      const rawData = wueStatistics.groupSizeCorrelation;
      console.log('Raw group size correlation data:', rawData); // DEBUG

      // Daten in das richtige Format für Scatter Plot bringen
      const data = rawData.map((item) => ({
        x: parseFloat(item.group_size),
        y: parseFloat(item.consumption_per_person),
      }));

      console.log('Formatted group size data:', data); // DEBUG

      new Chart(ctx, {
        type: 'scatter',
        data: {
          datasets: [
            {
              label: 'Verbrauch pro Person',
              data: data,
              backgroundColor: 'rgb(75, 192, 192)',
              pointRadius: 8, // Größere Punkte
              pointHoverRadius: 10, // Noch größer beim Hover
              borderColor: 'rgb(75, 192, 192)', // Gleiche Farbe wie Füllung
              borderWidth: 1, // Dünner Rand
            },
          ],
        },
        options: {
          responsive: true,
          plugins: {
            title: {
              display: true,
              text: 'Verbrauch nach Gruppengröße',
            },
            legend: {
              display: true,
              position: 'top',
            },
          },
          scales: {
            x: {
              type: 'linear',
              position: 'bottom',
              title: {
                display: true,
                text: 'Gruppengröße (Personen)',
              },
              min: 0,
            },
            y: {
              type: 'linear',
              title: {
                display: true,
                text: 'Verbrauch pro Person (L)',
              },
              min: 0,
            },
          },
        },
      });
    },
  };

  // Initialisierung
  WUEStatistics.init();
});
