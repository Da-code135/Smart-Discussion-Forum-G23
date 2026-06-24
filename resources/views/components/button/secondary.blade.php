@props(['disabled' => false])

<button
  type="button"
  @disabled($disabled)
  {{ $attributes->merge([
    'class' => 'inline-flex items-center justify-center px-lg py-sm bg-transparent border border-outline-variant text-on-surface font-medium rounded text-body-md font-sans focus:outline-none transition-colors ' .
      ($disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-surface-container hover:border-outline cursor-pointer focus:border-primary focus:ring-0')
  ]) }}
>
  {{ $slot }}
</button>
