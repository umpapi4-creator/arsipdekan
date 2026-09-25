<?php

namespace App\Http\Controllers;

use App\Models\LetterNumber;
use App\Models\Setting;
use App\Models\User;
use App\Services\GoogleDriveService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class ArchiveController extends Controller
{
    public function index(Request $request, GoogleDriveService $drive): View
    {
        /** @var User $user */
        $user = $request->user();
        $prodiItems = $drive->prodiItemsWithDriveFolders();
        $selected = $user->isAdmin() ? (string) $request->query('prodi', 'all') : $user->prodi_key;
        if ($user->isAdmin() && $selected !== 'all' && !isset($prodiItems[$selected])) $selected = 'all';

        $status = !$drive->configured() ? 'needs_configuration' : ($drive->hasConnection() ? 'connected' : 'disconnected');
        $files = [];
        $driveError = null;

        if ($status === 'connected') {
            try {
                $visible = $this->visibleProdi($user, $selected, $prodiItems);
                foreach ($visible as $key => $prodi) {
                    foreach ($drive->listFiles($prodi['folder_id']) as $file) {
                        $file['prodi_key'] = $key;
                        $file['prodi_name'] = $prodi['name'];
                        $files[] = $file;
                    }
                }
                usort($files, fn (array $a, array $b) => strcmp($b['modifiedTime'] ?? '', $a['modifiedTime'] ?? ''));
            } catch (Throwable $error) {
                report($error);
                $status = 'error';
                $driveError = 'Google Drive tidak dapat diakses. Admin dapat menghubungkan ulang akun Google.';
            }
        }

        $uploadProdi = $user->isAdmin() && $selected !== 'all' ? $selected : ($user->prodi_key ?: array_key_first($prodiItems));
        $lastLetterNumber = max(973, (int) (Setting::valueOf('letter_number_last') ?? 973));
        $nextLetterNumber = $lastLetterNumber + 1;
        $letterNumbers = LetterNumber::query()
            ->when(!$user->isAdmin(), fn ($query) => $query->where('prodi_key', $user->prodi_key))
            ->when($user->isAdmin() && $selected !== 'all', fn ($query) => $query->where('prodi_key', $selected))
            ->orderByDesc('number')
            ->limit(8)
            ->get();

        return view('dashboard', compact(
            'user', 'prodiItems', 'selected', 'uploadProdi', 'status', 'files', 'driveError',
            'lastLetterNumber', 'nextLetterNumber', 'letterNumbers'
        ));
    }

    public function upload(Request $request, GoogleDriveService $drive): RedirectResponse|JsonResponse
    {
        $validated = $request->validate([
            'file' => ['required', 'file', 'max:102400'],
            'prodi_key' => ['nullable', 'string'],
        ]);
        $target = $this->uploadTarget($request->user(), $validated['prodi_key'] ?? null, $drive);
        abort_unless($target, 422, 'Pilih program studi tujuan unggahan.');

        try {
            $file = $drive->upload($validated['file'], $target['folder_id']);
            if ($request->expectsJson()) return response()->json(['message' => 'Berkas berhasil disimpan.', 'file' => $file], 201);
            return back()->with('success', $validated['file']->getClientOriginalName().' berhasil disimpan ke '.$target['name'].'.');
        } catch (Throwable $error) {
            report($error);
            $message = 'Berkas gagal diunggah ke Google Drive.';
            if ($request->expectsJson()) return response()->json(['message' => $message], 502);
            return back()->with('error', $message);
        }
    }

    public function destroy(Request $request, string $fileId, GoogleDriveService $drive): RedirectResponse
    {
        /** @var User $user */
        $user = $request->user();

        try {
            $metadata = $drive->metadata($fileId);
        } catch (Throwable $error) {
            report($error);
            return back()->with('error', 'Dokumen tidak dapat diperiksa di Google Drive.');
        }

        $allowed = collect($this->visibleProdi($user, 'all', $drive->prodiItemsWithDriveFolders()))->pluck('folder_id')->all();
        abort_unless(count(array_intersect($metadata['parents'] ?? [], $allowed)) > 0, 403, 'Kamu tidak memiliki akses ke file ini.');

        try {
            $drive->trash($fileId);
            return back()->with('success', ($metadata['name'] ?? 'Dokumen').' berhasil dihapus dari arsip.');
        } catch (Throwable $error) {
            report($error);
            return back()->with('error', 'Dokumen gagal dihapus dari Google Drive.');
        }
    }

    public function open(Request $request, string $fileId, GoogleDriveService $drive): View
    {
        /** @var User $user */
        $user = $request->user();
        $metadata = $this->authorizedMetadata($user, $fileId, $drive);
        $name = (string) ($metadata['name'] ?? 'Dokumen');
        $mime = (string) ($metadata['mimeType'] ?? 'application/octet-stream');
        $ext = strtolower(pathinfo($name, PATHINFO_EXTENSION));

        $previewType = match (true) {
            in_array($mime, [
                'application/vnd.google-apps.document',
                'application/vnd.google-apps.presentation',
                'application/vnd.google-apps.drawing',
            ], true),
            $mime === 'application/pdf',
            $ext === 'pdf' => 'pdf',

            str_starts_with($mime, 'image/') => 'image',
            $ext === 'docx' => 'docx',
            $mime === 'application/vnd.google-apps.spreadsheet',
            in_array($ext, ['xlsx', 'xls', 'csv'], true) => 'sheet',
            str_starts_with($mime, 'text/'),
            in_array($ext, ['txt', 'log', 'md'], true) => 'text',
            default => 'unsupported',
        };

        return view('archive-preview', [
            'fileId' => $fileId,
            'fileName' => $name,
            'previewType' => $previewType,
            'contentUrl' => route('archive.preview-content', $fileId),
        ]);
    }

    public function previewContent(Request $request, string $fileId, GoogleDriveService $drive): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $metadata = $this->authorizedMetadata($user, $fileId, $drive);
        $download = $drive->download($metadata);
        $body = $download['response']->toPsrResponse()->getBody();
        $safeName = str_replace(["\r", "\n", '"'], ['', '', "'"], $download['name']);

        return response()->stream(function () use ($body) {
            while (!$body->eof()) echo $body->read(1024 * 64);
        }, 200, [
            'Content-Type' => $download['mime'],
            'Content-Disposition' => 'inline; filename="'.$safeName.'"',
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
            'X-Frame-Options' => 'SAMEORIGIN',
        ]);
    }

    public function download(Request $request, string $fileId, GoogleDriveService $drive): StreamedResponse
    {
        /** @var User $user */
        $user = $request->user();
        $metadata = $this->authorizedMetadata($user, $fileId, $drive);
        $download = $drive->download($metadata);
        $body = $download['response']->toPsrResponse()->getBody();
        $name = (string) $download['name'];
        $asciiName = preg_replace('/[^A-Za-z0-9._ -]/', '_', $name) ?: 'dokumen';
        $encodedName = rawurlencode($name);

        return response()->stream(function () use ($body) {
            while (!$body->eof()) echo $body->read(1024 * 64);
        }, 200, [
            'Content-Type' => $download['mime'],
            'Content-Disposition' => 'attachment; filename="'.$asciiName.'"; filename*=UTF-8\'\''.$encodedName,
            'Cache-Control' => 'private, no-store, max-age=0',
            'Pragma' => 'no-cache',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    private function authorizedMetadata(User $user, string $fileId, GoogleDriveService $drive): array
    {
        $metadata = $drive->metadata($fileId);
        $allowed = collect($this->visibleProdi($user, 'all', $drive->prodiItemsWithDriveFolders()))->pluck('folder_id')->all();
        abort_unless(count(array_intersect($metadata['parents'] ?? [], $allowed)) > 0, 403, 'Kamu tidak memiliki akses ke file ini.');
        return $metadata;
    }

    private function visibleProdi(User $user, string $selected, ?array $items = null): array
    {
        $items ??= config('prodi.items');
        if (!$user->isAdmin()) return isset($items[$user->prodi_key]) ? [$user->prodi_key => $items[$user->prodi_key]] : [];
        if ($selected !== 'all' && isset($items[$selected])) return [$selected => $items[$selected]];
        return $items;
    }

    private function uploadTarget(User $user, ?string $requested, GoogleDriveService $drive): ?array
    {
        $key = $user->isAdmin() ? $requested : $user->prodi_key;
        $items = $drive->prodiItemsWithDriveFolders();
        $target = $key ? ($items[$key] ?? null) : null;
        return $target ? ['key' => $key, ...$target] : null;
    }
}
