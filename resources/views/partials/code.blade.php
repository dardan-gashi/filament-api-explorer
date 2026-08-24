<div @class(['fae-code', 'fae-code-numbered', $class ?? ''])>
	@foreach (\DardanGashi\FilamentApiExplorer\Highlighting\Highlighter::lines($html) as $index => $line)
		<span class="fae-code-gutter" aria-hidden="true">{{ $index + 1 }}</span><span class="fae-code-text">{!! $line !!}</span>
	@endforeach
</div>
