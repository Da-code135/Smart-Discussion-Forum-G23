@props(['title' => 'No data found', 'description' => 'There\'s nothing to display here.', 'action' => null, 'actionUrl' => null, 'actionText' => 'Create'])

<div class="text-center py-xxl font-sans">
  <!-- Placeholder icon per DESIGN.md spec - light gray box with centered X -->
  <div class="w-xxl h-xxl bg-surface-container rounded mb-lg mx-auto flex items-center justify-center">
    <svg class="w-lg h-lg" viewBox="0 0 100 100" xmlns="http://www.w3.org/2000/svg">
      <!-- Horizontal line of X -->
      <line x1="20" y1="20" x2="80" y2="80" stroke="#d1d1d1" stroke-width="2"/>
      <!-- Vertical line of X -->
      <line x1="80" y1="20" x2="20" y2="80" stroke="#d1d1d1" stroke-width="2"/>
    </svg>
  </div>

  <!-- Title -->
  <h3 class="text-headline-md text-on-surface font-bold mb-xs">{{ $title }}</h3>

  <!-- Description -->
  <p class="text-body-md text-on-surface-variant mb-lg">{{ $description }}</p>

  <!-- Action Button -->
  @if($action && $actionUrl)
    <a href="{{ $actionUrl }}" class="inline-flex items-center px-lg py-sm bg-primary text-on-primary font-medium rounded text-body-md hover:bg-primary-container transition-colors">
      {{ $actionText }}
    </a>
  @elseif($slot->isNotEmpty())
    {{ $slot }}
  @endif
</div>
