@props(['id', 'name', 'label', 'autocomplete' => 'current-password'])
<label for="{{ $id }}">{{ $label }}</label>
<div class="password-field">
    <input id="{{ $id }}" name="{{ $name }}" type="password" required autocomplete="{{ $autocomplete }}" {{ $attributes }}>
    <button type="button" class="password-toggle" data-password-toggle aria-label="Show password">
        <x-icon name="eye" class="icon password-toggle-show" />
        <x-icon name="eye-off" class="icon password-toggle-hide" />
    </button>
</div>
