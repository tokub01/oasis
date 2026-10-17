<?php

namespace App\Http\Controllers\Moderation;

use App\Enums\Moderation\EventType;
use App\Http\Controllers\Controller;
use App\Models\ModerationCase;
use App\Models\ModerationEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use ZipArchive;

/**
 * Admins download the secured evidence of an escalated case as one ZIP (route moderation.cases.evidence), solely to
 * hand it to the police. The ZIP holds every file plus beweise.json with texts, metadata and SHA-256 checksums. Each
 * download is written to the case history; the temporary file is deleted after sending.
 */
class EvidenceDownloadController extends Controller
{
    public function __invoke(Request $request, ModerationCase $case): BinaryFileResponse
    {
        Gate::authorize('administrate');
        abort_if($case->escalation === null || ! $case->evidence()->exists(), 404);

        $disk = Storage::disk(config('oasis.media_disk'));
        $file = tempnam(sys_get_temp_dir(), 'oasis-beweise-');
        $zip = new ZipArchive;
        $zip->open($file, ZipArchive::OVERWRITE);
        $manifest = ['fall' => $case->id, 'art' => $case->escalation->label(), 'eskaliert_am' => $case->escalated_at?->toIso8601String(),
            'exportiert_am' => now()->toIso8601String(), 'beweise' => []];
        foreach ($case->evidence()->orderBy('id')->get() as $evidence) {
            /** @var ModerationEvidence $evidence */
            $entry = ['id' => $evidence->id, 'art' => $evidence->kind, 'gesichert_am' => $evidence->created_at->toIso8601String(),
                'sha256' => $evidence->sha256, 'bytes' => $evidence->bytes, 'text' => $evidence->content, 'angaben' => $evidence->meta];
            if ($evidence->path !== null && ($bytes = $disk->get($evidence->path)) !== null) {
                $entry['datei'] = basename($evidence->path);
                $zip->addFromString($entry['datei'], $bytes);
            }
            $manifest['beweise'][] = $entry;
        }
        $zip->addFromString('beweise.json', json_encode($manifest, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));
        $zip->close();
        $case->events()->create(['actor_id' => $request->user()->id, 'type' => EventType::EvidenceDownloaded, 'data' => []]);

        return response()->download($file, 'oasis-fall-'.$case->id.'-beweise.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend();
    }
}
