{{--
    Role management for admins, component App\Livewire\Moderation\Roles (route moderation.roles.index).

    Variables:
      $users      LengthAwarePaginator<User>  id, display_name, email, role; 25 per page
      $roleCases  list<App\Enums\Role>
--}}
<div class="flex flex-col gap-6">
    <div class="flex flex-col gap-1">
        <h1 class="o-h2 m-0 text-night">Rollen</h1>
        <p class="m-0 text-ink-2">Moderation und Admin brauchen eine eingeschaltete Zwei-Faktor-Anmeldung, bevor sie den Moderationsbereich öffnen können.</p>
    </div>

    <p role="status" @class(['m-0 rounded-2xl bg-dune px-[18px] py-3.5', 'sr-only' => ! $status])>{{ $status }}</p>

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
                    <tr wire:key="user-{{ $user->id }}" class="border-t border-paper-line">
                        <td class="px-4 py-3 font-semibold">{{ $user->display_name }}</td>
                        <td class="px-4 py-3">{{ $user->email }}</td>
                        <td class="px-4 py-3">
                            <form wire:submit="save({{ $user->id }})" class="flex flex-wrap items-center gap-2">
                                <label for="role-{{ $user->id }}" class="sr-only">Rolle von {{ $user->display_name }}</label>
                                <select id="role-{{ $user->id }}" wire:model="roles.{{ $user->id }}" class="o-input min-h-11 w-auto py-2" @disabled($user->is(auth()->user()))>
                                    @foreach ($roleCases as $role)
                                        <option value="{{ $role->value }}">{{ $role->label() }}</option>
                                    @endforeach
                                </select>
                                @unless ($user->is(auth()->user()))
                                    <button type="submit" wire:loading.attr="disabled" class="o-btn o-btn-sm o-btn-outline">Speichern</button>
                                @else
                                    <span class="text-sm text-muted">Das bist du</span>
                                @endunless
                                @error('roles.'.$user->id)<p role="alert" class="o-error m-0 w-full">{{ $message }}</p>@enderror
                            </form>
                        </td>
                    </tr>
                @endforeach
            </tbody>
        </table>
    </div>

    {{ $users->links() }}
</div>
