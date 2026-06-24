@props(['label', 'name', 'error' => null, 'disabled' => false])

<div class="mb-md">
  <div class="flex items-start gap-sm">
    <input
      type="checkbox"
      id="{{ $name }}"
      name="{{ $name }}"
      @disabled($disabled)
      {{ $attributes->merge([
        'class' => 'h-md w-md mt-xs border rounded text-primary focus:ring-0 cursor-pointer accent-primary ' .
          ($disabled ? 'opacity-50 cursor-not-allowed' : '')
      ]) }}
    />
    <label for="{{ $name }}" class="text-body-md text-on-surface cursor-pointer font-sans">
      {{ $label }}
    </label>
  </div>

  @if($error)
    <p class="text-body-sm text-error mt-xs font-sans flex items-start gap-xs ml-lg">
      <span class="flex-shrink-0">⚠</span>
      <span>{{ $error }}</span>
    </p>
  @endif
</div>
