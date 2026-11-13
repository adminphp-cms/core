{{--
    A responsive image of the media library: the browser picks the smallest copy that
    fills the space given by `sizes` (100vw by default). Width and height keep the
    layout from jumping while it loads.

    <x-adminphp::image :media="$block->media('image')" alt="…" sizes="(min-width: 768px) 700px, 100vw" class="…" />
--}}
@props(['media', 'alt' => '', 'sizes' => '100vw', 'loading' => 'lazy'])

@if ($media)
    <img
        src="{{ $media->url() }}"
        @if ($srcset = $media->srcset()) srcset="{{ $srcset }}" sizes="{{ $sizes }}" @endif
        alt="{{ $alt }}"
        @if ($media->width && $media->height) width="{{ $media->width }}" height="{{ $media->height }}" @endif
        loading="{{ $loading }}"
        decoding="async"
        {{ $attributes }}
    >
@endif
