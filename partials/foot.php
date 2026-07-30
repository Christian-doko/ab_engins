      </main>
    </div>
  </div>

  <div class="overlay" id="overlay"></div>
  <script src="js/shell.js"></script>
  <?php foreach (($pageScripts ?? []) as $src): ?>
    <script src="<?= htmlspecialchars($src) ?>"></script>
  <?php endforeach; ?>
</body>
</html>
