@props(['status', 'kind' => 'subject'])
@php
    $maps = [
        'subject' => [
            'draft' => ['Brouillon', 'bg-slate-100 text-slate-700 border-slate-200', 'fa-pen-to-square'],
            'pending' => ['À examiner', 'bg-amber-50 text-amber-800 border-amber-200', 'fa-clock'],
            'approved' => ['Retenu', 'bg-emerald-50 text-emerald-800 border-emerald-200', 'fa-check'],
            'rejected' => ['Rejeté', 'bg-rose-50 text-rose-800 border-rose-200', 'fa-ban'],
            'archived' => ['Archivé', 'bg-slate-100 text-slate-600 border-slate-200', 'fa-box-archive'],
        ],
        'source' => [
            'active' => ['Actif', 'bg-emerald-50 text-emerald-800 border-emerald-200', 'fa-circle-check'],
            'error' => ['Erreur à réessayer', 'bg-rose-50 text-rose-800 border-rose-200', 'fa-triangle-exclamation'],
            'disabled' => ['Désactivé', 'bg-slate-100 text-slate-600 border-slate-200', 'fa-circle-pause'],
        ],
    ];
    [$label, $classes, $icon] = $maps[$kind][$status] ?? ['Inconnu', 'bg-slate-100 text-slate-600 border-slate-200', 'fa-circle-question'];
@endphp
<span {{ $attributes->class(['inline-flex items-center gap-1.5 px-2.5 py-1 rounded-full border text-xs font-semibold whitespace-nowrap', $classes]) }}>
    <i class="fa-solid {{ $icon }} text-xs" aria-hidden="true"></i>
    {{ $label }}
</span>
