@props([
    'name',
    'label',
    'type' => 'text',
    'hint' => null,
    'mono' => false,
    'bag' => 'default', // Fortify uses named error bags, e.g. updatePassword
])
@php
    $id = $attributes->get('id', $name);
    $error = $errors->getBag($bag)->first($name);
    $describedBy = trim(($hint ? $id.'-hint ' : '').($error ? $id.'-error' : ''));
@endphp
<div class="space-y-1.5">
    <label for="{{ $id }}" class="block text-sm font-medium text-ink">{{ $label }}</label>
    <input id="{{ $id }}" name="{{ $name }}" type="{{ $type }}"
        @if ($type !== 'password') value="{{ old($name, $attributes->get('value')) }}" @endif
        @if ($describedBy) aria-describedby="{{ $describedBy }}" @endif
        @if ($error) aria-invalid="true" @endif
        {{ $attributes->except(['value', 'id'])->merge(['class' => 'block w-full h-10 rounded-md bg-surface px-3 text-base text-ink border placeholder:text-muted focus:outline-none focus:border-focus focus:shadow-[var(--ring-focus)] '.($error ? 'border-danger' : 'border-line-strong').($mono ? ' font-mono text-sm' : '')]) }}>
    @if ($hint)
        <p id="{{ $id }}-hint" class="text-xs text-muted">{{ $hint }}</p>
    @endif
    @if ($error)
        <p id="{{ $id }}-error" class="text-xs text-danger flex items-center gap-1.5"><span class="record !bg-danger"></span>{{ $error }}</p>
    @endif
</div>
