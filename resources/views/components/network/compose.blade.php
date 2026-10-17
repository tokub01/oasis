{{-- Reactive composer adapter. Props are server-rendered context models; the child locks IDs and rechecks policies. --}}
@props(['oasis' => null, 'replyTo' => null, 'carried' => null, 'images' => true])
@if (! $replyTo?->oasis || auth()->user()?->can('post', $replyTo->oasis))
<livewire:network.compose :oasis-id="$oasis?->id" :reply-to-id="$replyTo?->id" :carried-id="$carried?->id" :show-images="$images"
    :key="'compose-'.($replyTo?->id ?? $carried?->id ?? $oasis?->slug ?? 'quelle')" @post-published="$refresh" />
@endif
