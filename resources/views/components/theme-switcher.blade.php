<div x-data="themeSwitcher" {{ $attributes->merge(['class' => 'inline-flex rounded-md border border-line bg-surface p-0.5']) }} role="radiogroup" aria-label="Theme">
    @foreach (['system' => 'System', 'light' => 'Light', 'dark' => 'Dark'] as $value => $label)
        <button type="button" role="radio" x-on:click="choose('{{ $value }}')" x-bind:aria-checked="pref === '{{ $value }}'"
            x-bind:class="pref === '{{ $value }}' ? 'bg-primary-subtle text-primary' : 'text-secondary hover:text-ink'"
            class="h-7 rounded-sm px-2.5 text-xs font-semibold">{{ $label }}</button>
    @endforeach
</div>
