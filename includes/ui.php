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

function render_flashes(): string
{
    $html = '';
    foreach (take_flashes() as $f) {
        [$cls, $ic] = match ($f['type']) {
            'success' => ['bg-emerald-50 border-emerald-200 text-emerald-800', 'check-circle-2'],
            'error' => ['bg-rose-50 border-rose-200 text-rose-800', 'alert-octagon'],
            'warning' => ['bg-amber-50 border-amber-200 text-amber-800', 'alert-triangle'],
            default => ['bg-cyan-50 border-cyan-200 text-cyan-800', 'info'],
        };
        $html .= '<div class="flash mb-4 flex items-start gap-2 border rounded-xl px-4 py-3 text-xs font-medium ' . $cls . '">'
            . icon($ic, 'w-4 h-4 mt-0.5 shrink-0') . '<span class="flex-1">' . e($f['message']) . '</span>'
            . '<button type="button" onclick="this.parentElement.remove()" class="opacity-60 hover:opacity-100">' . icon('x', 'w-3.5 h-3.5') . '</button></div>';
    }
    return $html;
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
