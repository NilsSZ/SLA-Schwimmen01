<!-- footer.php -->
<footer class="bg-dark text-light py-4 mt-5">
  <div class="container">
    <div class="row align-items-center">
      <div class="col-md-6 text-center text-md-start">
        <p>
          Support:
          <a href="mailto:info@sla-schwimmen.de" class="text-info text-decoration-none">info@sla-schwimmen.de</a>
        </p>
        <p>
          <a href="impressum.php" class="text-info text-decoration-none me-3">Impressum</a>
          <a href="datenschutz.php" class="text-info text-decoration-none">Datenschutz</a>
        </p>
        <p class="mb-0">&copy; <?php echo date('Y'); ?> SLA-Schwimmen. Alle Rechte vorbehalten.</p>
      </div>
      <div class="col-md-6 text-center text-md-end mt-3 mt-md-0">
        <button id="supportButton" class="btn btn-outline-light">
          <i class="bi bi-life-preserver"></i> Support
        </button>
      </div>
    </div>
  </div>

  <!-- Support Chat Popup -->
  <div id="supportChat" class="support-chat">
    <div class="support-chat-header">
      <h5 class="mb-0">Support-Anfrage</h5>
      <button id="closeSupportChat" class="btn-close btn-close-white"></button>
    </div>
    <div class="support-chat-body">
      <form id="supportForm">
        <div class="mb-3">
          <label for="supportSubject" class="form-label">Betreff</label>
          <input type="text" class="form-control" id="supportSubject" placeholder="Betreff" required>
        </div>
        <div class="mb-3">
          <label for="supportMessage" class="form-label">Nachricht</label>
          <textarea class="form-control" id="supportMessage" rows="3" placeholder="Ihre Nachricht" required></textarea>
        </div>
        <div class="form-check mb-3">
          <input type="checkbox" class="form-check-input" id="supportAgree" required>
          <label class="form-check-label" for="supportAgree">
            Ich stimme zu, dass mein Anliegen vom Support-Team bearbeitet wird.
          </label>
        </div>
        <button type="submit" class="btn btn-primary w-100">Absenden</button>
      </form>
    </div>
  </div>

  <style>
    /* Support Chat Popup Style */
    .support-chat {
      position: fixed;
      bottom: 20px;
      right: 20px;
      width: 320px;
      background: #fff;
      border: 1px solid #ccc;
      border-radius: 8px;
      box-shadow: 0 2px 8px rgba(0, 0, 0, 0.3);
      display: none;
      z-index: 1050;
    }
    .support-chat-header {
      background: #007bff;
      color: #fff;
      padding: 10px;
      border-top-left-radius: 8px;
      border-top-right-radius: 8px;
      display: flex;
      justify-content: space-between;
      align-items: center;
    }
    .support-chat-body {
      padding: 15px;
    }
  </style>

  <script>
    // Öffne das Support-Chat Popup beim Klick auf den Support-Button
    document.getElementById('supportButton').addEventListener('click', function() {
      document.getElementById('supportChat').style.display = 'block';
    });
    // Schließe das Popup beim Klick auf den Schließen-Button
    document.getElementById('closeSupportChat').addEventListener('click', function() {
      document.getElementById('supportChat').style.display = 'none';
    });
    // Support-Formular absenden (hier als Beispiel mit Alert)
    document.getElementById('supportForm').addEventListener('submit', function(e) {
      e.preventDefault();
      // Hier sollte per AJAX die Anfrage an den Server gesendet werden, z.B.:
      // fetch('support_handler.php', { method: 'POST', body: new FormData(this) })
      alert('Support-Anfrage gesendet. Ihre Fallnummer wird Ihnen per E-Mail mitgeteilt.');
      document.getElementById('supportChat').style.display = 'none';
      // Formular zurücksetzen
      this.reset();
    });
  </script>
</footer>
