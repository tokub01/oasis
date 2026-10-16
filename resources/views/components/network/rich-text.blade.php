{{-- Escaped network text with @accounts, #topics and HTTP links (visible destination host). Props: text (string). No JS or URL fetches. --}}
@props(['text'])
@php
    $names = \App\Support\Network\Mentions::usernames($text);
    $existing = \App\Support\Network\Mentions::existing($names);
    preg_match_all(\App\Support\Network\Mentions::PATTERN, $text, $mentions, PREG_OFFSET_CAPTURE);
    $tagPattern = '/(?<![\p{L}\p{N}_#])\#'.\App\Http\Controllers\Search\SearchController::TAG_PATTERN.'(?![\p{L}\p{N}_])/u';
    preg_match_all($tagPattern, $text, $tags, PREG_OFFSET_CAPTURE);
    $links = \App\Support\Network\TextLinks::in($text);
    $external = [];
    foreach ($links as $link) {
        $external[$link['position']] = $link;
    }
    $tokens = array_merge(array_map(fn ($link) => [$link['url'], $link['position']], $links), $mentions[0], $tags[0]);
    usort($tokens, fn ($left, $right) => $left[1] <=> $right[1]);
    $offset = 0;
    $html = '';
    foreach ($tokens as [$token, $position]) {
        // URL tokens consume their entire range, including @names and #fragments inside the URL.
        if ($position < $offset) {
            continue;
        }
        $html .= e(substr($text, $offset, $position - $offset));
        if (isset($external[$position])) {
            $html .= '<a class="text-night underline underline-offset-4" href="'.e($token).'" target="_blank" rel="noopener noreferrer nofollow" title="'.e($token).'">'.e($external[$position]['host']).'</a>';
            $offset = $position + strlen($token);
            continue;
        }
        $name = substr($token, 1);
        $url = null;
        if (str_starts_with($token, '@') && in_array(strtolower($name), $existing, true)) {
            $url = route('oasis.profil', strtolower($name));
        } elseif (str_starts_with($token, '#')) {
            $url = route('search.tags.show', $name);
        }
        $html .= $url === null ? e($token) : '<a class="text-night underline underline-offset-4" href="'.e($url).'">'.e($token).'</a>';
        $offset = $position + strlen($token);
    }
    $html .= e(substr($text, $offset));
@endphp
<span {{ $attributes->class('whitespace-pre-line break-words') }}>{!! $html !!}</span>
