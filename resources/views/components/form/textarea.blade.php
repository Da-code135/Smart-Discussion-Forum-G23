@props(['label', 'name', 'placeholder' => '', 'rows' => 4, 'error' => null, 'required' => false, 'disabled' => false])

<div class="mb-md">
  @if($label)
    <label for="{{ $name }}" class="block text-label-sm text-on-surface font-medium mb-xs">
      {{ $label }}
      @if($required)
        <span class="text-error">*</span>
      @endif
    </label>
  @endif

  <textarea
    id="{{ $name }}"
    name="{{ $name }}"
    rows="{{ $rows }}"
    placeholder="{{ $placeholder }}"
    @disabled($disabled)
    {{ $attributes->merge([
      'class' => 'w-full px-md py-sm border rounded text-body-md font-sans focus:outline-none transition-colors resize-vertical ' .
        ($error 
          ? 'border-error focus:border-error focus:ring-0' 
          : 'border-outline-variant focus:border-primary focus:ring-0'
        ) .
        ($disabled ? ' bg-surface-container text-on-surface-variant cursor-not-allowed' : ' bg-surface-container-lowest')
    ]) }}
  ></textarea>

  @if($error)
    <p class="text-body-sm text-error mt-xs font-sans flex items-start gap-xs">
      <span class="flex-shrink-0 mt-0.5">⚠</span>
      <span>{{ $error }}</span>
    </p>
  @endif
</div>
