@if ($article->getTranslation('title', 'fr', false) === 'Erreur')
    @php(throw new RuntimeException('Vue cassée'))
@endif
<h1>{{ $item->title }}</h1>
<p class="slug">{{ $article->getTranslation('slug', 'fr', false) }}</p>
@foreach ($article->blocks as $block)
    <blockquote>{{ $block->translated('quote') }}</blockquote>
@endforeach
@foreach ($article->related('related_articles', onlyPublished: false) as $related)
    <a>{{ $related->title }}</a>
@endforeach
