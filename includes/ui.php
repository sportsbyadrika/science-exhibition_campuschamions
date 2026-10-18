<?php
declare(strict_types=1);

/* ---------------------------------------------------------------------
 * Small view helpers (Tailwind markup snippets)
 * ------------------------------------------------------------------- */

function icon(string $name, string $class = 'w-4 h-4'): string
{
    return '<i data-lucide="' . e($name) . '" class="' . e($class) . '"></i>';
}

function status_badge(string $status): string
{
    $tone = match ($status) {
        'approved', 'active', 'submitted' => 'bg-emerald-50 text-emerald-700 border-emerald-200',
        'pending', 'reopened' => 'bg-amber-50 text-amber-700 border-amber-200',
        'rejected', 'inactive' => 'bg-rose-50 text-rose-700 border-rose-200',
        default => 'bg-slate-100 text-slate-600 border-slate-200',
    };
    return '<span class="inline-flex items-center px-2 py-0.5 rounded border text-[10px] font-bold uppercase tracking-wider ' . $tone . '">' . e($status) . '</span>';
}

function tone_text(string $tone): string
{
    return match ($tone) {
        'emerald' => 'text-emerald-600',
        'cyan' => 'text-cyan-700',
        'amber' => 'text-amber-600',
        'rose' => 'text-rose-600',
        default => 'text-slate-500',
    };
}

/**
 * Flash messages as toasts in the top-right corner. They slide in, pause while hovered
 * and close themselves (errors stay a little longer); assets/js/app.js handles timing.
 */
function render_flashes(): string
{
    $flashes = take_flashes();
    if (!$flashes) {
        return '';
    }
    $html = '<div id="toastStack" class="fixed top-4 right-4 left-4 sm:left-auto z-[100] flex flex-col items-end gap-2 pointer-events-none" aria-live="polite">';
    foreach ($flashes as $f) {
        [$cls, $bar, $ic, $ms] = match ($f['type']) {
            'success' => ['border-emerald-200 text-emerald-800', 'bg-emerald-500', 'check-circle-2', 4000],
            'error' => ['border-rose-200 text-rose-800', 'bg-rose-500', 'alert-octagon', 8000],
            'warning' => ['border-amber-200 text-amber-800', 'bg-amber-500', 'alert-triangle', 6000],
            default => ['border-cyan-200 text-cyan-800', 'bg-cyan-500', 'info', 5000],
        };
        $html .= '<div class="toast pointer-events-auto relative w-full sm:w-80 overflow-hidden flex items-start gap-2 bg-white border rounded-xl shadow-lg shadow-slate-900/10 px-4 py-3 text-xs font-medium translate-x-[120%] opacity-0 transition-all duration-300 ' . $cls . '"'
            . ' role="' . ($f['type'] === 'error' ? 'alert' : 'status') . '" data-timeout="' . $ms . '">'
            . icon($ic, 'w-4 h-4 mt-0.5 shrink-0') . '<span class="flex-1 leading-snug">' . e($f['message']) . '</span>'
            . '<button type="button" class="toast-close opacity-60 hover:opacity-100" aria-label="Close">' . icon('x', 'w-3.5 h-3.5') . '</button>'
            . '<span class="toast-bar absolute bottom-0 left-0 h-0.5 w-full origin-left ' . $bar . '"></span></div>';
    }
    return $html . '</div>';
}

function stat_card(string $label, string $value, string $iconName, string $tone, string $hint = '', string $valueClass = 'text-slate-900'): string
{
    $iconCls = match ($tone) {
        'amber' => 'bg-amber-50 text-amber-600',
        'emerald' => 'bg-emerald-50 text-emerald-600',
        'purple' => 'bg-purple-50 text-purple-600',
        'rose' => 'bg-rose-50 text-rose-600',
        'teal' => 'bg-teal-50 text-teal-600',
        'indigo' => 'bg-indigo-50 text-indigo-600',
        default => 'bg-cyan-50 text-cyan-600',
    };
    return '<div class="bg-white border border-slate-200 p-4 rounded-xl shadow-xs">'
        . '<div class="flex items-center justify-between"><span class="text-xs font-bold text-slate-500 uppercase tracking-wider">' . e($label) . '</span>'
        . '<span class="p-2 rounded-lg ' . $iconCls . '">' . icon($iconName) . '</span></div>'
        . '<div class="mt-2"><span class="text-2xl font-black ' . $valueClass . '">' . e($value) . '</span></div>'
        . ($hint !== '' ? '<p class="text-[11px] text-slate-500 font-medium mt-1">' . e($hint) . '</p>' : '')
        . '</div>';
}

function select_options(array $options, mixed $selected, bool $placeholder = false, string $placeholderText = '— Select —'): string
{
    $html = $placeholder ? '<option value="">' . e($placeholderText) . '</option>' : '';
    foreach ($options as $value => $label) {
        $html .= '<option value="' . e($value) . '"' . ((string) $value === (string) $selected ? ' selected' : '') . '>' . e($label) . '</option>';
    }
    return $html;
}

/** Simple paginator: returns [offset, html] */
function paginate(int $total, int $perPage = 25): array
{
    $page = max(1, input_int('page', 1));
    $pages = max(1, (int) ceil($total / $perPage));
    $page = min($page, $pages);
    $html = '';
    if ($pages > 1) {
        $q = $_GET;
        $html = '<div class="flex items-center justify-between text-xs text-slate-500 px-4 py-3 border-t border-slate-200 bg-slate-50">'
            . '<span>Page ' . $page . ' of ' . $pages . ' • ' . $total . ' records</span><div class="flex gap-1">';
        foreach ([['Prev', $page - 1, $page <= 1], ['Next', $page + 1, $page >= $pages]] as [$label, $p, $disabled]) {
            $q['page'] = $p;
            $html .= $disabled
                ? '<span class="px-3 py-1.5 rounded-lg border border-slate-200 opacity-40">' . $label . '</span>'
                : '<a class="px-3 py-1.5 rounded-lg border border-slate-200 bg-white hover:border-cyan-300 hover:text-cyan-700" href="?' . e(http_build_query($q)) . '">' . $label . '</a>';
        }
        $html .= '</div></div>';
    }
    return [($page - 1) * $perPage, $html];
}
