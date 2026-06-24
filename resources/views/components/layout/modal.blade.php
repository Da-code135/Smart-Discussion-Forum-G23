@props(['id', 'title' => '', 'maxWidth' => 'md'])

@php
  $maxWidthClass = match($maxWidth) {
    'sm' => 'max-w-sm',
    'md' => 'max-w-md',
    'lg' => 'max-w-lg',
    'xl' => 'max-w-xl',
    '2xl' => 'max-w-2xl',
    default => 'max-w-md',
  };
@endphp

<div
  x-show="open{{ $id }}"
  @keydown.escape.window="open{{ $id }} = false"
  class="fixed inset-0 z-50 flex items-center justify-center p-md"
  style="display: none;"
>
  <!-- Backdrop - tonal layer per DESIGN.md -->
  <div
    @click="open{{ $id }} = false"
    class="fixed inset-0 bg-on-surface opacity-5 transition-opacity"
    x-show="open{{ $id }}"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0"
    x-transition:enter-end="opacity-5"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-5"
    x-transition:leave-end="opacity-0"
  ></div>

  <!-- Modal - standard border per DESIGN.md -->
  <div
    class="bg-surface-container-lowest border border-outline-variant rounded-lg {{ $maxWidthClass }} w-full z-10 shadow-ambient-md"
    x-show="open{{ $id }}"
    x-transition:enter="ease-out duration-300"
    x-transition:enter-start="opacity-0 scale-95"
    x-transition:enter-end="opacity-100 scale-100"
    x-transition:leave="ease-in duration-200"
    x-transition:leave-start="opacity-100 scale-100"
    x-transition:leave-end="opacity-0 scale-95"
  >
    <!-- Header -->
    @if($title)
      <div class="px-lg py-lg border-b border-outline-variant flex items-center justify-between">
        <h3 class="text-headline-md text-on-surface font-bold">{{ $title }}</h3>
        <button
          @click="open{{ $id }} = false"
          class="text-on-surface-variant hover:text-on-surface focus:outline-none p-xs"
        >
          <svg class="w-md h-md" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"></path>
          </svg>
        </button>
      </div>
    @endif

    <!-- Body -->
    <div class="px-lg py-lg font-sans">
      {{ $slot }}
    </div>
  </div>
</div>

<script>
  document.addEventListener('alpine:init', () => {
    Alpine.data('modal', () => ({
      open{{ $id }}: false,
    }));
  });
</script>
