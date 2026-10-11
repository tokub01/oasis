{{--
    Role management for admins (route moderation.roles.index, gate manageRoles).

    Variables:
      $users  LengthAwarePaginator<User>  id, display_name, email, role; 25 per page
      $roles  list<App\Enums\Role>
    Each row is its own small form (PUT moderation.roles.update), so it works without JavaScript.
    New staff members also need confirmed two-factor authentication before they can open the moderation area.
--}}
@extends('layouts.staff', ['title' => 'Rollen', 'active' => 'roles'])

@section('content')
    <div class="flex flex-col gap-6">
        <div class="flex flex-col gap-1">
            <h1 class="o-h2 m-0 text-night">Rollen</h1>
            <p class="m-0 text-ink-2">Moderation und Admin brauchen eine eingeschaltete Zwei-Faktor-Anmeldung, bevor sie den Moderationsbereich öffnen können.</p>
        </div>

        <div class="overflow-x-auto rounded-[20px] border border-paper-line bg-paper">
            <table class="w-full border-collapse text-left text-[15px]">
                <caption class="sr-only">Konten und ihre Rollen</caption>
                <thead class="bg-dune text-sm">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-extrabold">Name</th>
                        <th scope="col" class="px-4 py-3 font-extrabold">E-Mail</th>
                        <th scope="col" class="px-4 py-3 font-extrabold">Rolle</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach ($users as $user)
                        <tr class="border-t border-paper-line">
                            <td class="px-4 py-3 font-semibold">{{ $user->display_name }}</td>
                            <td class="px-4 py-3">{{ $user->email }}</td>
                            <td class="px-4 py-3">
                                <form method="POST" action="{{ route('moderation.roles.update', $user) }}" class="flex flex-wrap items-center gap-2">
                                    @csrf
                                    @method('PUT')
                                    <label for="role-{{ $user->id }}" class="sr-only">Rolle von {{ $user->display_name }}</label>
                                    <select id="role-{{ $user->id }}" name="role" class="o-input min-h-11 w-auto py-2" @disabled($user->is(auth()->user()))>
                                        @foreach ($roles as $role)
                                            <option value="{{ $role->value }}" @selected($user->role === $role)>{{ $role->label() }}</option>
                                        @endforeach
                                    </select>
                                    @unless ($user->is(auth()->user()))
                                        <button type="submit" class="o-btn o-btn-sm o-btn-outline">Speichern</button>
                                    @else
                                        <span class="text-sm text-muted">Das bist du</span>
                                    @endunless
                                </form>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{ $users->links() }}
    </div>
@endsection
