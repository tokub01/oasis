<?php

namespace App\Support\Network;

use App\Enums\Network\PostKind;
use App\Enums\Network\Visibility;
use App\Models\Post;
use App\Support\Media\WebpImage;
use Illuminate\Validation\Rule;

/** Input rules shared by the reactive composer and its upload validation regressions. */
class PostInput
{
    public const IMAGE_MAX_KB = 8192;

    /** @return array<string, mixed> */
    public static function rules(?Post $carried = null): array
    {
        return [
            'kind' => ['required', Rule::enum(PostKind::class)],
            'body' => ['required', 'string', 'max:'.Post::MAX_LENGTH],
            'visibility' => ['required', Rule::enum(Visibility::class)],
            'link_read' => [Rule::when(TextLinks::in($carried->body ?? '') !== [], ['accepted'])],
            'images' => ['nullable', 'array', 'max:'.Post::MAX_IMAGES],
            'images.*' => ['file', 'image', 'mimes:jpeg,png,webp,gif', 'max:'.self::IMAGE_MAX_KB,
                Rule::dimensions()->maxWidth(WebpImage::MAX_INPUT_EDGE)->maxHeight(WebpImage::MAX_INPUT_EDGE)],
            // Alternative text is required for every image (accessibility); index matches images.*.
            'alts' => ['nullable', 'array', 'max:'.Post::MAX_IMAGES],
            'alts.*' => ['nullable', 'string', 'max:300'],
        ];
    }

    /** @return array<string, string> */
    public static function messages(): array
    {
        return [
            'body.required' => 'Schreib etwas in deinen Tropfen.',
            'body.max' => 'Ein Tropfen hat höchstens '.Post::MAX_LENGTH.' Zeichen.',
            'link_read.accepted' => 'Bestätige bitte, dass du den verlinkten Inhalt gelesen hast.',
            'images.max' => 'Höchstens '.Post::MAX_IMAGES.' Bilder pro Tropfen.',
            'images.*.image' => 'Das ist kein Bild.',
            'images.*.mimes' => 'Bilder bitte als JPEG, PNG, WebP oder GIF.',
            'images.*.dimensions' => 'Ein Bild darf höchstens 12000 Pixel pro Kante haben.',
            'images.*.max' => 'Ein Bild darf höchstens 8 MB groß sein.',
        ];
    }
}
