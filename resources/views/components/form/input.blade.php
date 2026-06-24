@props(['label', 'name', 'type' => 'text', 'placeholder' => '', 'error' => null, 'required' => false, 'disabled' => false])

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
    type="{{ $type }}"
    id="{{ $name }}"
    name="{{ $name }}"
    placeholder="{{ $placeholder }}"
    @disabled($disabled)
    {{ $attributes->merge([
      'class' => 'w-full px-md py-sm border rounded text-body-md font-sans focus:outline-none focus:border-primary transition-colors ' .
        ($error 
          ? 'border-error focus:border-error focus:ring-0' 
          : 'border-outline-variant focus:border-primary focus:ring-0'
        ) .
        ($disabled ? ' bg-surface-container text-on-surface-variant cursor-not-allowed' : ' bg-surface-container-lowest')
    ]) }}
  />

  @if($error)
    <p class="text-body-sm text-error mt-xs font-sans flex items-start gap-xs">
      <span class="flex-shrink-0 mt-0.5">⚠</span>
      <span>{{ $error }}</span>
    </p>
  @endif
</div>
