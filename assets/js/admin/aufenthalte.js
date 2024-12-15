(function ($) {
  'use strict';

  /**
   * Aufenthalte Form Handler
   */
  var WUEAufenthaltForm = {
    /**
     * Initialisiert die Formular-Funktionalität
     */
    init: function () {
      this.bindEvents();
    },

    /**
     * Bindet Event-Handler
     */
    bindEvents: function () {
      $('#abreise').on('change', this.calculateNights);
      $('#brennerstunden_ende').on('change', this.validateBrennerstunden);
    },

    /**
     * Berechnet die Anzahl der Übernachtungen
     */
    calculateNights: function () {
      var ankunft = new Date($('#ankunft').val());
      var abreise = new Date($(this).val());
      var naechte = Math.ceil((abreise - ankunft) / (1000 * 60 * 60 * 24));

      // Vorschlag für Mitgliederübernachtungen wenn noch leer
      if (!$('#anzahl_mitglieder').val()) {
        $('#anzahl_mitglieder').val(naechte);
      }
    },

    /**
     * Validiert die Brennerstunden
     */
    validateBrennerstunden: function () {
      var start = parseFloat($('#brennerstunden_start').val());
      var ende = parseFloat($(this).val());

      if (ende <= start) {
        alert(wueAufenthalte.i18n.brennerstundenError);
        $(this).val('');
      }
    },
  };

  // Initialisierung wenn Dokument geladen ist
  $(document).ready(function () {
    WUEAufenthaltForm.init();
  });
})(jQuery);
