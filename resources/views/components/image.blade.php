{{--
    A responsive image of the media library: the browser picks the smallest copy that
    fills the space given by `sizes` (100vw by default). Width and height keep the
    layout from jumping while it loads.

    <x-adminphp::image :asset="$block->asset('image')" alt="…" sizes="(min-width: 768px) 700px, 100vw" class="…" />
--}}
@props(['asset', 'alt' => '', 'sizes' => '100vw', 'loading' => 'lazy'])

@if ($asset)
    <img
        src="{{ $asset->url() }}"
        @if ($srcset = $asset->srcset()) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
        alt="{{ $alt }}"
        @if ($asset->width && $asset->height) width="{{ $asset->width }}" height="{{ $asset->height }}" @endif
        loading="{{ $loading }}"
        decoding="async"
        {{ $attributes }}
    >
@endif
