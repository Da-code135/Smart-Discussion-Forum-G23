@props(['title' => '', 'subtitle' => '', 'footer' => false])

<div {{ $attributes->merge(['class' => 'bg-surface-container-lowest border border-outline-variant rounded font-sans overflow-hidden']) }}>
  @if($title)
    <div class="px-lg py-lg border-b border-outline-variant">
      <h3 class="text-headline-md text-on-surface font-bold">{{ $title }}</h3>
      @if($subtitle)
        <p class="text-body-sm text-on-surface-variant mt-xs">{{ $subtitle }}</p>
      @endif
    </div>
  @endif

  <div class="px-lg py-lg">
    {{ $slot }}
  </div>

  @if($footer)
    <div class="px-lg py-md bg-surface-container border-t border-outline-variant">
      {{ $footer }}
    </div>
  @endif
</div>
