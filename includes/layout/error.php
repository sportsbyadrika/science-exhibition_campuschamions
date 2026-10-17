<?php
/** @var int $code @var string $title @var string $message */
$pageTitle = $title;
require __DIR__ . '/head.php';
?>
<body class="bg-slate-50 bg-grid-pattern min-h-screen flex items-center justify-center p-6 text-slate-800">
  <div class="max-w-md w-full bg-white border border-slate-200 rounded-2xl shadow-xl p-8 text-center space-y-4">
    <div class="w-14 h-14 mx-auto rounded-full bg-rose-50 text-rose-600 flex items-center justify-center text-xl font-black font-mono"><?= (int) $code ?></div>
    <h1 class="text-xl font-extrabold text-slate-900"><?= e($title) ?></h1>
    <p class="text-sm text-slate-500"><?= e($message) ?></p>
    <a href="<?= url('') ?>" class="btn-dark">Back to Portal</a>
  </div>
</body>
</html>
