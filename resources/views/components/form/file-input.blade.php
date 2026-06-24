@props(['label', 'name', 'accept' => '', 'error' => null, 'required' => false, 'disabled' => false, 'helper' => ''])

<div class="mb-md">
  @if($label)
    <label for="{{ $name }}" class="block text-label-sm text-on-surface font-medium mb-xs">
      {{ $label }}
      @if($required)
        <span class="text-error">*</span>
      @endif
    </label>
  @endif

  <input
    type="file"
    id="{{ $name }}"
    name="{{ $name }}"
    accept="{{ $accept }}"
    @disabled($disabled)
    {{ $attributes->merge([
      'class' => 'block w-full text-body-sm text-on-surface-variant font-sans
        file:mr-md file:py-sm file:px-md
        file:rounded file:border
        file:border-outline-variant
        file:text-body-sm file:font-medium
        file:bg-surface-container file:text-on-surface
        hover:file:bg-surface-container-high
        cursor-pointer ' .
      ($disabled ? 'opacity-50 cursor-not-allowed' : '')
    ]) }}
  />

  @if($helper)
    <p class="text-body-sm text-on-surface-variant mt-xs font-sans">{{ $helper }}</p>
  @endif

  @if($error)
    <p class="text-body-sm text-error mt-xs font-sans flex items-start gap-xs">
      <span class="flex-shrink-0 mt-0.5">⚠</span>
      <span>{{ $error }}</span>
    </p>
  @endif
</div>
