  </main>

  <footer class="bg-white border-t border-slate-200 py-5 text-xs text-slate-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-2">
      <div class="flex items-center space-x-2">
        <?= icon('atom', 'w-4 h-4 text-cyan-600') ?>
        <span class="font-bold text-slate-800"><?= e(app_title()) ?></span>
        <span>• <?= e(setting('organisation_name', 'CBSE South Zone Sahodaya Complex')) ?></span>
      </div>
      <span>Support: <?= e(setting('support_phone')) ?> • <?= e(setting('support_email')) ?></span>
    </div>
  </footer>

  <script src="<?= asset('vendor/lucide.min.js') ?>"></script>
  <script src="<?= asset('js/app.js') ?>"></script>
  <?= $extraScripts ?? '' ?>
</body>
</html>
