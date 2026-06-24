@props(['label', 'name', 'options' => [], 'placeholder' => 'Select an option', 'error' => null, 'required' => false, 'disabled' => false])

<div class="mb-md">
  @if($label)
    <label for="{{ $name }}" class="block text-label-sm text-on-surface font-medium mb-xs">
      {{ $label }}
      @if($required)
        <span class="text-error">*</span>
      @endif
    </label>
  @endif

  <div class="relative">
    <select
      id="{{ $name }}"
      name="{{ $name }}"
      @disabled($disabled)
      {{ $attributes->merge([
        'class' => 'w-full px-md py-sm border rounded text-body-md font-sans focus:outline-none focus:ring-0 appearance-none bg-surface-container-lowest transition-colors ' .
          ($error 
            ? 'border-error focus:border-error' 
            : 'border-outline-variant focus:border-primary'
          ) .
          ($disabled ? ' bg-surface-container text-on-surface-variant cursor-not-allowed' : '')
      ]) }}
    >
      @if($placeholder)
        <option value="">{{ $placeholder }}</option>
      @endif

      @foreach($options as $value => $label)
        <option value="{{ $value }}">{{ $label }}</option>
      @endforeach
    </select>

    <!-- Dropdown arrow icon -->
    <div class="pointer-events-none absolute inset-y-0 right-0 flex items-center pr-md text-on-surface-variant">
      <svg class="w-4 h-4 fill-current" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 20 20">
        <path d="M9.293 12.95l.707.707L15.657 8l-1.414-1.414L10 10.828 5.757 6.586 4.343 8z"/>
      </svg>
    </div>
  </div>

  @if($error)
    <p class="text-body-sm text-error mt-xs font-sans flex items-start gap-xs">
      <span class="flex-shrink-0 mt-0.5">⚠</span>
      <span>{{ $error }}</span>
    </p>
  @endif
</div>
