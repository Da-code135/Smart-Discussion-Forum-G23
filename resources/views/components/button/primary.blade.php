@props(['disabled' => false, 'loading' => false])

<button
  type="button"
  @disabled($disabled || $loading)
  {{ $attributes->merge([
    'class' => 'inline-flex items-center justify-center px-lg py-sm bg-primary text-on-primary font-medium rounded text-body-md font-sans focus:outline-none transition-colors ' .
      ($disabled || $loading ? 'opacity-50 cursor-not-allowed' : 'hover:bg-primary-container cursor-pointer focus:border-primary focus:ring-0')
  ]) }}
>
  @if($loading)
    <svg class="animate-spin h-md w-md mr-sm" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
      <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
      <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
    </svg>
    <span>{{ $slot }}</span>
  @else
    {{ $slot }}
  @endif
</button>
