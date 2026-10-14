{{--
    Safety overview page (route safety.index, /app/sicherheit, SafetyController::index).
    Lists all blocked and muted accounts with options to unblock or unmute.

    Variables:
      $blockedUsers  Collection<User>  accounts blocked by the viewer
      $mutedUsers    Collection<User>  accounts muted by the viewer
--}}
@extends('layouts.app', ['title' => 'Sicherheit', 'active' => 'sicherheit', 'wide' => true])

@section('content')
    <x-network.columns label="Sicherheit und Privatsphäre">
        <div class="flex flex-col gap-8">
            <div class="flex flex-col gap-2">
                <p class="o-eyebrow m-0">Privatsphäre</p>
                <h1 class="o-h2 m-0 text-night">Sicherheit</h1>
                <p class="m-0 text-muted">Hier verwaltest du Personen, die du blockiert oder stummgeschaltet hast. Beide Einstellungen wirken leise – niemand wird benachrichtigt.</p>
            </div>

            {{-- Blockierte Personen --}}
            <section aria-labelledby="blocked-heading" class="flex flex-col gap-4 rounded-[24px] border border-paper-line bg-paper p-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2 id="blocked-heading" class="m-0 text-[20px] font-extrabold text-night">Blockierte Personen</h2>
                        <p class="m-0 text-sm text-muted">Ihr seht gegenseitig keine Tropfen, Antworten oder Profile mehr und könnt nicht interagieren.</p>
                    </div>
                    <span class="rounded-full bg-sand px-3 py-1 text-sm font-bold text-night">{{ $blockedUsers->count() }}</span>
                </div>

                @forelse ($blockedUsers as $user)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-dune-line pt-4">
                        <div class="flex items-center gap-3">
                            <x-network.avatar :user="$user" :size="44" />
                            <div class="flex flex-col leading-tight">
                                <span class="font-bold text-night">{{ $user->display_name }}</span>
                                <span class="text-sm text-muted">{{ '@'.$user->username }}</span>
                            </div>
                        </div>
                        <form method="POST" action="{{ route('safety.block.destroy', $user) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="o-btn o-btn-sm o-btn-outline">Blockade aufheben</button>
                        </form>
                    </div>
                @empty
                    <p class="m-0 border-t border-dune-line pt-4 text-[15px] text-muted">Du hast aktuell niemanden blockiert.</p>
                @endforelse
            </section>

            {{-- Stummgeschaltete Personen --}}
            <section aria-labelledby="muted-heading" class="flex flex-col gap-4 rounded-[24px] border border-paper-line bg-paper p-6">
                <div class="flex items-center justify-between gap-4">
                    <div class="flex flex-col gap-1">
                        <h2 id="muted-heading" class="m-0 text-[20px] font-extrabold text-night">Stummgeschaltete Personen</h2>
                        <p class="m-0 text-sm text-muted">Tropfen dieser Personen erscheinen nicht mehr in deiner Quelle, Entdecken oder Antworten. Ihr Profil bleibt für dich aufrufbar.</p>
                    </div>
                    <span class="rounded-full bg-sand px-3 py-1 text-sm font-bold text-night">{{ $mutedUsers->count() }}</span>
                </div>

                @forelse ($mutedUsers as $user)
                    <div class="flex flex-wrap items-center justify-between gap-3 border-t border-dune-line pt-4">
                        <a href="{{ route('oasis.profil', $user->username) }}" class="flex items-center gap-3 text-ink no-underline hover:opacity-80">
                            <x-network.avatar :user="$user" :size="44" />
                            <div class="flex flex-col leading-tight">
                                <span class="font-bold text-night">{{ $user->display_name }}</span>
                                <span class="text-sm text-muted">{{ '@'.$user->username }}</span>
                            </div>
                        </a>
                        <form method="POST" action="{{ route('safety.mute.destroy', $user) }}">
                            @csrf @method('DELETE')
                            <button type="submit" class="o-btn o-btn-sm o-btn-outline">Nicht mehr stummschalten</button>
                        </form>
                    </div>
                @empty
                    <p class="m-0 border-t border-dune-line pt-4 text-[15px] text-muted">Du hast aktuell niemanden stummgeschaltet.</p>
                @endforelse
            </section>
        </div>

        <x-slot:aside>
            <x-network.side-card title="Wie Schutz funktioniert" tone="dune">
                <div class="flex flex-col gap-3 text-[15px] text-ink-2 leading-relaxed">
                    <p class="m-0"><strong class="text-night">Blockieren:</strong> Wenn du jemanden blockierst, existiert ihr füreinander nicht mehr. Tropfen und Profile sind wie nicht existent (404). Bestehende Follows werden beidseitig gelöscht.</p>
                    <p class="m-0"><strong class="text-night">Stummschalten:</strong> Nur für dich sichtbar. Du siehst keine Beiträge der Person mehr im Feed, kannst ihr Profil aber weiter aufrufen. Die Person merkt nichts.</p>
                </div>
            </x-network.side-card>

            <x-network.side-card title="Deine Merkliste">
                <p class="m-0 text-[15px] text-ink-2">Tropfen kannst du dir privat für später merken.</p>
                <a href="{{ route('safety.bookmarks') }}" class="o-btn o-btn-md o-btn-outline self-start">Zur Merkliste</a>
            </x-network.side-card>
        </x-slot:aside>
    </x-network.columns>
@endsection
