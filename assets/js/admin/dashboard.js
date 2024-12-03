document.addEventListener('DOMContentLoaded', function () {
  const WUEDashboard = {
    init: function () {
      this.bindYearSelector();
      console.log('WUE Dashboard initialized'); // Debug-Ausgabe
    },

    bindYearSelector: function () {
      const selector = document.getElementById('wue-year-selector');
      if (selector) {
        console.log('Year selector found'); // Debug-Ausgabe
        selector.addEventListener('change', function (e) {
          console.log('Year changed to: ' + e.target.value); // Debug-Ausgabe
          const year = e.target.value;
          // URL-Parameter aktualisieren
          const currentUrl = new URL(window.location.href);
          currentUrl.searchParams.set('wue_year', year);
          // Seite neu laden
          window.location.href = currentUrl.toString();
        });
      } else {
        console.log('Year selector not found'); // Debug-Ausgabe
      }
    },
  };

  WUEDashboard.init();
});
