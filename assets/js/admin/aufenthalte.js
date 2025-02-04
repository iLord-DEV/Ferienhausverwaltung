(function ($) {
  'use strict';

  var WUEAufenthaltForm = {
    init: function () {
      this.bindEvents();
      this.initializeDefaults();
    },

    initializeDefaults: function () {
      // Standardwert für Gäste ist 0 bei neuen Einträgen
      if (!$('#anzahl_gaeste').val()) {
        $('#anzahl_gaeste').val('0');
      }

      // Setze initiales Minimum für Abreisedatum
      if ($('#ankunft').val()) {
        $('#abreise').attr('min', $('#ankunft').val());
      }
    },

    bindEvents: function () {
      $('#ankunft').on('change', this.handleAnkunftChange.bind(this));
      $('#abreise').on('change', this.handleAbreiseChange.bind(this));
      $('#brennerstunden_start').on(
        'change',
        this.handleBrennerstundenStartChange.bind(this)
      );
      $('#brennerstunden_ende').on(
        'change',
        this.handleBrennerstundenEndeChange.bind(this)
      );
      $('.wue-aufenthalt-form').on('submit', this.handleSubmit.bind(this));
    },

    handleAnkunftChange: function (e) {
      const ankunftDate = $(e.target).val();

      // Setze das Minimum für Abreise
      $('#abreise').attr('min', ankunftDate);

      // Wenn Abreise jetzt vor Ankunft liegt, lösche Abreise
      const abreiseDate = $('#abreise').val();
      if (abreiseDate && abreiseDate < ankunftDate) {
        $('#abreise').val('');
      }
    },

    handleAbreiseChange: function (e) {
      const abreiseDate = $(e.target).val();
      const ankunftDate = $('#ankunft').val();

      if (abreiseDate < ankunftDate) {
        alert('Das Abreisedatum muss nach dem Ankunftsdatum liegen.');
        $(e.target).val('');
        return;
      }

      // Prüfe Mindestanzahl Mitglieder
      const naechte = Math.ceil(
        (new Date(abreiseDate) - new Date(ankunftDate)) / (1000 * 60 * 60 * 24)
      );
      $('#anzahl_mitglieder').attr('min', naechte);
    },

    handleBrennerstundenStartChange: function () {
      this.validateBrennerstunden();
    },

    handleBrennerstundenEndeChange: function () {
      this.validateBrennerstunden();
    },

    validateBrennerstunden: function () {
      const start = parseFloat($('#brennerstunden_start').val());
      const ende = parseFloat($('#brennerstunden_ende').val());

      if (!isNaN(start) && !isNaN(ende) && ende <= start) {
        alert(
          'Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.'
        );
        $('#brennerstunden_ende').val('');
        return false;
      }
      return true;
    },

    validateForm: function () {
      let isValid = true;
      const errors = [];

      // 1. Datums-Validierung
      const ankunft = new Date($('#ankunft').val());
      const abreise = new Date($('#abreise').val());

      if (abreise <= ankunft) {
        errors.push('Das Abreisedatum muss nach dem Ankunftsdatum liegen.');
        isValid = false;
      }

      // 2. Brennerstunden-Validierung (nur Basisprüfung)
      const start = parseFloat($('#brennerstunden_start').val());
      const ende = parseFloat($('#brennerstunden_ende').val());

      if (isNaN(start) || isNaN(ende)) {
        errors.push('Bitte geben Sie gültige Brennerstunden ein.');
        isValid = false;
      } else if (ende <= start) {
        errors.push(
          'Die Brennerstunden bei Abreise müssen höher sein als bei Ankunft.'
        );
        isValid = false;
      }

      // 3. Mitglieder-Validierung
      const naechte = Math.ceil((abreise - ankunft) / (1000 * 60 * 60 * 24));
      const mitglieder = parseInt($('#anzahl_mitglieder').val()) || 0;

      if (mitglieder < naechte) {
        errors.push(
          `Es muss mindestens ein Mitglied pro Nacht anwesend sein (mindestens ${naechte} Übernachtungen).`
        );
        isValid = false;
      }

      // 4. Gäste-Validierung
      const gaeste = parseInt($('#anzahl_gaeste').val());
      if (isNaN(gaeste) || gaeste < 0) {
        errors.push(
          'Die Anzahl der Gäste-Übernachtungen darf nicht negativ sein.'
        );
        isValid = false;
      }

      // Zeige Fehler an wenn vorhanden
      if (!isValid) {
        errors.forEach((error) => alert(error));
        return false;
      }

      // Form ist valide, weitere Prüfung erfolgt im Backend
      return true;
    },

    handleSubmit: function (e) {
      // Sofort das Event stoppen
      e.preventDefault();
      e.stopPropagation();
      e.stopImmediatePropagation();

      // Validierung durchführen
      if (this.validateForm()) {
        // Wenn Validierung OK, Formular per DOM abschicken
        document.querySelector('.wue-aufenthalt-form').submit();
      }

      // Immer false zurückgeben um sicherzustellen, dass kein weiteres Submit passiert
      return false;
    },
  };

  // Initialisierung wenn Dokument geladen ist
  $(document).ready(function () {
    WUEAufenthaltForm.init();
  });
})(jQuery);
