@props(['size' => 'md', 'message' => 'Loading...'])

@php
  $sizeClass = match($size) {
    'sm' => 'h-md w-md',
    'md' => 'h-lg w-lg',
    'lg' => 'h-xl w-xl',
    default => 'h-lg w-lg',
  };
@endphp

<div class="flex flex-col items-center justify-center py-xxl font-sans">
  <svg class="animate-spin {{ $sizeClass }} text-on-surface mb-md" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
    <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="2"></circle>
    <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
  </svg>
  @if($message)
    <p class="text-body-md text-on-surface-variant font-medium">{{ $message }}</p>
  @endif
</div>
