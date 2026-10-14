<?php

namespace App\Http\Requests\Network;

use App\Actions\Network\StorePostImage;
use App\Enums\Network\Visibility;
use App\Models\Oasis;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Validation\Rule;

/**
 * Input of a new Tropfen, reply or carry ("weitertragen"). Context ids are resolved to models here and checked
 * against the policies, so the action only receives readable, writable targets.
 */
class PostRequest extends FormRequest
{
    /** Images: JPEG, PNG, WebP or GIF (first frame) up to 8 MB each; re-encoded on upload. */
    public const IMAGE_MAX_KB = 8192;

    public function authorize(): bool
    {
        $user = $this->user();
        if (! $user->can('create', Post::class)) {
            return false;
        }
        foreach ([$this->replyTo(), $this->carried()] as $context) {
            if ($context !== null && ! $user->can('interact', $context)) {
                return false;
            }
        }
        $oasis = $this->oasis();

        return $oasis === null || $user->can('post', $oasis);
    }

    /** @return array<string, mixed> */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:'.Post::MAX_LENGTH],
            'visibility' => ['required', Rule::enum(Visibility::class)],
            'oasis' => ['nullable', 'string', Rule::exists('oases', 'slug')],
            'reply_to' => ['nullable', 'integer', Rule::exists('posts', 'id')],
            'carried' => ['nullable', 'integer', Rule::exists('posts', 'id'), 'prohibits:reply_to'],
            'images' => ['nullable', 'array', 'max:'.Post::MAX_IMAGES],
            'images.*' => ['file', 'image', 'mimes:jpeg,png,webp,gif', 'max:'.self::IMAGE_MAX_KB,
                Rule::dimensions()->maxWidth(StorePostImage::MAX_INPUT_EDGE)->maxHeight(StorePostImage::MAX_INPUT_EDGE)],
            // Alternative text is required for every image (accessibility); index matches images.*.
            'alts' => ['nullable', 'array', 'max:'.Post::MAX_IMAGES],
            'alts.*' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** Every uploaded image needs a description. */
    public function after(): array
    {
        return [function ($validator): void {
            foreach (array_keys($this->file('images') ?? []) as $index) {
                if (trim((string) $this->input("alts.$index")) === '') {
                    $validator->errors()->add("alts.$index", 'Beschreib das Bild kurz für Menschen, die es nicht sehen.');
                }
            }
        }];
    }

    /** @return array<string, string> */
    public function messages(): array
    {
        return [
            'body.required' => 'Schreib etwas in deinen Tropfen.',
            'body.max' => 'Ein Tropfen hat höchstens '.Post::MAX_LENGTH.' Zeichen.',
            'images.max' => 'Höchstens '.Post::MAX_IMAGES.' Bilder pro Tropfen.',
            'images.*.image' => 'Das ist kein Bild.',
            'images.*.mimes' => 'Bilder bitte als JPEG, PNG, WebP oder GIF.',
            'images.*.dimensions' => 'Ein Bild darf höchstens 12000 Pixel pro Kante haben.',
            'images.*.max' => 'Ein Bild darf höchstens 8 MB groß sein.',
        ];
    }

    public function oasis(): ?Oasis
    {
        return $this->filled('oasis') ? Oasis::where('slug', $this->input('oasis'))->first() : null;
    }

    public function replyTo(): ?Post
    {
        return $this->filled('reply_to') ? Post::find($this->integer('reply_to')) : null;
    }

    public function carried(): ?Post
    {
        return $this->filled('carried') ? Post::find($this->integer('carried')) : null;
    }

    /** @return list<array{file: UploadedFile, alt: string}> */
    public function images(): array
    {
        return collect($this->file('images') ?? [])
            ->map(fn (UploadedFile $file, $index) => ['file' => $file, 'alt' => trim((string) $this->input("alts.$index"))])
            ->values()->all();
    }
}
