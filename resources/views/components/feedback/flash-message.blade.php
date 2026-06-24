@props([])

@if(session('message'))
  <div
    x-data="{ show: true }"
    x-show="show"
    x-transition:enter="ease-out duration-300"
    x-transition:leave="ease-in duration-200"
    @click.outside="show = false"
    class="mb-md p-lg rounded border border-outline-variant bg-surface-container-low text-on-surface flex items-center justify-between font-sans"
  >
    <div class="flex items-start gap-md">
      <span class="flex-shrink-0 mt-xs text-headline-sm">✓</span>
      <span class="text-body-md">{{ session('message') }}</span>
    </div>
    <button
      @click="show = false"
      class="text-on-surface-variant hover:text-on-surface focus:outline-none p-xs ml-lg"
    >
      <svg class="w-md h-md" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
      </svg>
    </button>
  </div>
@endif

@if(session('error'))
  <div
    x-data="{ show: true }"
    x-show="show"
    x-transition:enter="ease-out duration-300"
    x-transition:leave="ease-in duration-200"
    @click.outside="show = false"
    class="mb-md p-lg rounded border border-error-container bg-error-container text-on-error-container flex items-center justify-between font-sans"
  >
    <div class="flex items-start gap-md">
      <span class="flex-shrink-0 mt-xs text-headline-sm">⚠</span>
      <span class="text-body-md">{{ session('error') }}</span>
    </div>
    <button
      @click="show = false"
      class="text-on-error-container hover:opacity-75 focus:outline-none p-xs ml-lg"
    >
      <svg class="w-md h-md" fill="currentColor" viewBox="0 0 20 20">
        <path fill-rule="evenodd" d="M4.293 4.293a1 1 0 011.414 0L10 8.586l4.293-4.293a1 1 0 111.414 1.414L11.414 10l4.293 4.293a1 1 0 01-1.414 1.414L10 11.414l-4.293 4.293a1 1 0 01-1.414-1.414L8.586 10 4.293 5.707a1 1 0 010-1.414z" clip-rule="evenodd" />
      </svg>
    </button>
  </div>
@endif
