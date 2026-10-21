<?php

namespace App\Http\Controllers;

use App\Models\Export;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class DownloadExport
{
    public function __invoke(Request $request, Export $export): StreamedResponse
    {
        Gate::authorize('view', $export->job);

        abort_unless($export->isReady(), 404);
        abort_if($export->expires_at?->isPast(), 410, 'This export has expired.');

        $types = ['csv' => 'text/csv; charset=UTF-8', 'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'json' => 'application/json'];

        return Storage::disk($export->disk)->download($export->path, $export->filename, [
            'Content-Type' => $types[$export->format] ?? 'application/octet-stream',
            'X-Content-Type-Options' => 'nosniff',
            'Cache-Control' => 'private, no-store',
        ]);
    }
}
