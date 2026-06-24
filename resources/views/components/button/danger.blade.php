@props(['disabled' => false, 'confirm' => false])

<button
  type="button"
  @disabled($disabled)
  @if($confirm)
    onclick="return confirm('Are you sure? This action cannot be undone.');"
  @endif
  {{ $attributes->merge([
    'class' => 'inline-flex items-center justify-center px-lg py-sm bg-error text-on-error font-medium rounded text-body-md font-sans focus:outline-none transition-colors ' .
      ($disabled ? 'opacity-50 cursor-not-allowed' : 'hover:bg-error-container hover:text-on-error-container cursor-pointer focus:border-error focus:ring-0')
  ]) }}
>
  {{ $slot }}
</button>
