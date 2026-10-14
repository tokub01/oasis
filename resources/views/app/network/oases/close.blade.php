{{--
    Confirmation before closing an oasis (route network.oases.close → DELETE network.oases.destroy). A page instead
    of a JavaScript dialog, because the CSP allows no inline scripts. Variables: $oasis (with members_count, posts_count)
--}}
@extends('layouts.app', ['title' => $oasis->name.' schließen', 'active' => 'oasen'])

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-2">
            <a href="{{ route('network.oases.edit', $oasis) }}" class="o-link self-start">Zurück</a>
            <h1 class="o-h2 m-0 text-night">„{{ $oasis->name }}“ schließen?</h1>
        </div>

        <div class="flex flex-col gap-3 rounded-[24px] bg-paper p-6">
            <ul class="m-0 flex list-disc flex-col gap-1.5 pl-5 text-ink-2">
                <li>Die Oase verschwindet für alle {{ $oasis->members_count }} {{ $oasis->members_count === 1 ? 'Mitglied' : 'Mitglieder' }}.</li>
                <li>Ihre {{ $oasis->posts_count }} Tropfen bleiben bei den Menschen, die sie geschrieben haben – nur ohne Oase.</li>
                <li>Das lässt sich nicht rückgängig machen.</li>
            </ul>
            <form method="POST" action="{{ route('network.oases.destroy', $oasis) }}" class="flex flex-col gap-4">
                @csrf
                @method('DELETE')
                <label class="flex items-start gap-3">
                    <input type="checkbox" name="confirm" value="1" required class="mt-1 size-5 flex-none accent-night">
                    <span>Ja, ich möchte die Oase endgültig schließen.</span>
                </label>
                @error('confirm') <p role="alert" class="m-0 font-bold text-sun-text">{{ $message }}</p> @enderror
                <button type="submit" class="o-btn o-btn-md self-start bg-sun-text text-white">Oase schließen</button>
            </form>
        </div>
    </div>
@endsection
