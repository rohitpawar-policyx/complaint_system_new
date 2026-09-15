@props(['src', 'alt' => 'Preview'])
{{-- Clicking opens the full image inline in a new tab (not a forced
     download) - the browser's own viewer lets the user save it if they
     want, rather than us deciding for them. --}}
<a href="{{ $src }}" target="_blank" rel="noopener" class="image-preview-link">
    <img src="{{ $src }}" alt="{{ $alt }}" class="image-preview-thumb" loading="lazy">
</a>
