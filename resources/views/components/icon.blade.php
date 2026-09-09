@props(['name', 'class' => 'icon', 'decorative' => true, 'label' => null])
<i data-lucide="{{ $name }}" class="{{ $class }}"
    @if($decorative) aria-hidden="true" @elseif($label) role="img" aria-label="{{ $label }}" @endif
></i>
