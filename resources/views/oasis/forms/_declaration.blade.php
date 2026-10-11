{{--
    Retainable declaration details shared by cancellation and withdrawal confirmations.
    $fields: array<string, string|null> — labels mapped to already formatted declaration values.
    Values remain escaped and multiline; no JavaScript is required to save or print the page.
--}}
<dl class="mb-6 flex flex-col gap-4 rounded-3xl border border-paper-line bg-paper p-6">
    @foreach ($fields as $label => $value)
        <div>
            <dt class="font-bold text-night">{{ $label }}</dt>
            <dd class="whitespace-pre-wrap break-words">{{ $value ?? '–' }}</dd>
        </div>
    @endforeach
</dl>
