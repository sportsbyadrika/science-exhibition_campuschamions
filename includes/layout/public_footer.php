    </div>
  </main>

  <footer class="bg-white border-t border-slate-200 py-6 text-xs text-slate-500">
    <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
      <div class="flex items-center space-x-2">
        <?= icon('atom', 'w-4 h-4 text-cyan-600') ?>
        <span class="font-bold text-slate-800"><?= e(setting('footer_title', setting('organisation_name', 'CBSE South Zone Sahodaya Complex') . ' Science Fair')) ?></span>
        <?php if (setting('footer_tagline', 'Official Secretariat') !== '' && strtolower(setting('footer_tagline', 'Official Secretariat')) !== 'none'): ?><span>• <?= e(setting('footer_tagline', 'Official Secretariat')) ?></span><?php endif; ?>
      </div>
      <div><span>&copy; <?= date('Y') ?> <?= e(setting('footer_copyright', app_title())) ?></span></div>
    </div>
  </footer>

  <script src="<?= asset('vendor/lucide.min.js') ?>"></script>
  <script src="<?= asset('js/app.js') ?>"></script>
  <?= $extraScripts ?? '' ?>
</body>
</html>
