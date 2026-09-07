@props(['w' => 'w-full', 'h' => 'h-3', 'round' => 'rounded'])

{{-- One grey bar standing in for a line of text or a small control. --}}
<div {{ $attributes->merge(['class' => "bg-slate-200/80 {$w} {$h} {$round}"]) }}></div>
