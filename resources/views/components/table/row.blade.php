@props(['hover' => true])

<tr {{ $attributes->merge([
  'class' => 'border-b border-outline-variant font-sans ' .
    ($hover ? 'hover:bg-surface-container-low transition-colors' : '')
]) }}>
  {{ $slot }}
</tr>
