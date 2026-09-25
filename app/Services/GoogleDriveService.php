<?php

namespace App\Services;

use App\Models\Setting;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Throwable;

class GoogleDriveService
{
    private const CONNECTION_KEY = 'google_drive_connection';
    private const FOLDER_MAP_KEY = 'google_drive_folder_map';
    private const FOLDER_MIME = 'application/vnd.google-apps.folder';

    public function configured(): bool
    {
        return filled(config('services.google_drive.client_id'))
            && filled(config('services.google_drive.client_secret'))
            && filled(config('services.google_drive.redirect_uri'));
    }

    public function authorizationUrl(string $state): string
    {
        $query = http_build_query([
            'client_id' => config('services.google_drive.client_id'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'response_type' => 'code',
            'scope' => 'https://www.googleapis.com/auth/drive',
            'access_type' => 'offline',
            'prompt' => 'consent',
            'include_granted_scopes' => 'true',
            'state' => $state,
        ]);

        return 'https://accounts.google.com/o/oauth2/v2/auth?'.$query;
    }

    public function exchangeCode(string $code): array
    {
        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'code' => $code,
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'redirect_uri' => config('services.google_drive.redirect_uri'),
            'grant_type' => 'authorization_code',
        ])->throw();

        $tokens = $response->json();
        if (blank($tokens['access_token'] ?? null) || blank($tokens['refresh_token'] ?? null)) {
            throw new RuntimeException('Google tidak memberikan token akses lengkap.');
        }
        return $tokens;
    }

    public function saveConnection(array $tokens): void
    {
        $payload = [
            'access_token' => $tokens['access_token'],
            'refresh_token' => $tokens['refresh_token'],
            'expires_at' => now()->addSeconds((int) ($tokens['expires_in'] ?? 3600))->timestamp,
            'connected_at' => now()->toIso8601String(),
        ];
        Setting::put(self::CONNECTION_KEY, Crypt::encryptString(json_encode($payload)));
    }

    public function hasConnection(): bool
    {
        return $this->connection() !== null;
    }

    public function disconnect(): void
    {
        $connection = $this->connection();
        if ($connection && filled($connection['refresh_token'] ?? null)) {
            Http::asForm()->post('https://oauth2.googleapis.com/revoke', [
                'token' => $connection['refresh_token'],
            ]);
        }
        Setting::query()->whereKey(self::CONNECTION_KEY)->delete();
    }

    public function verifyConfiguredFolders(string $accessToken): void
    {
        $root = $this->folderMetadata($accessToken, config('prodi.root_folder_id'));
        if (($root['mimeType'] ?? null) !== self::FOLDER_MIME || !($root['capabilities']['canAddChildren'] ?? false)) {
            throw new RuntimeException('Folder utama Google Drive tidak ditemukan.');
        }

        $folderMap = [];
        foreach (config('prodi.items') as $key => $prodi) {
            $folderMap[$key] = $this->findOrCreateChildFolder($accessToken, config('prodi.root_folder_id'), $prodi['folder_name'] ?? $prodi['name']);
        }
        Setting::put(self::FOLDER_MAP_KEY, json_encode($folderMap));
    }

    public function prodiItemsWithDriveFolders(): array
    {
        $items = config('prodi.items');
        $folderMap = $this->folderMap();

        // Jika ada unit baru setelah Google Drive sudah pernah dihubungkan,
        // buat hanya folder unit yang belum memiliki ID tanpa menyentuh folder lama.
        $missing = array_filter($items, function (array $item, string $key) use ($folderMap) {
            return blank($folderMap[$key] ?? null) && blank($item['folder_id'] ?? null);
        }, ARRAY_FILTER_USE_BOTH);

        if ($missing && $this->hasConnection()) {
            $accessToken = $this->accessToken();
            foreach ($missing as $key => $item) {
                $folderMap[$key] = $this->findOrCreateChildFolder(
                    $accessToken,
                    config('prodi.root_folder_id'),
                    $item['folder_name'] ?? $item['name']
                );
            }
            Setting::put(self::FOLDER_MAP_KEY, json_encode($folderMap));
        }

        foreach ($items as $key => $item) {
            if (filled($folderMap[$key] ?? null)) {
                $items[$key]['folder_id'] = $folderMap[$key];
            }
        }

        return $items;
    }

    public function listFiles(string $folderId): array
    {
        $response = Http::withToken($this->accessToken())->get(
            'https://www.googleapis.com/drive/v3/files',
            [
                'q' => "'{$folderId}' in parents and trashed = false and mimeType != 'application/vnd.google-apps.folder'",
                'spaces' => 'drive',
                'pageSize' => 100,
                'orderBy' => 'modifiedTime desc',
                'fields' => 'files(id,name,mimeType,size,modifiedTime)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ]
        )->throw();

        return $response->json('files', []);
    }

    public function upload(UploadedFile $file, string $folderId): array
    {
        $accessToken = $this->accessToken();
        $mime = $file->getMimeType() ?: 'application/octet-stream';
        $size = $file->getSize();
        $session = Http::withToken($accessToken)
            ->withHeaders([
                'X-Upload-Content-Type' => $mime,
                'X-Upload-Content-Length' => (string) $size,
            ])
            ->post('https://www.googleapis.com/upload/drive/v3/files?uploadType=resumable&supportsAllDrives=true&fields=id,name,mimeType,size,modifiedTime', [
                'name' => $file->getClientOriginalName(),
                'parents' => [$folderId],
            ])->throw();

        $location = $session->header('Location');
        if (!$location) throw new RuntimeException('Google Drive tidak membuat sesi unggahan.');

        $stream = fopen($file->getRealPath(), 'rb');
        if ($stream === false) throw new RuntimeException('File unggahan tidak dapat dibaca.');

        try {
            return Http::withToken($accessToken)
                ->withHeaders(['Content-Length' => (string) $size])
                ->withBody($stream, $mime)
                ->put($location)
                ->throw()
                ->json();
        } finally {
            fclose($stream);
        }
    }

    public function trash(string $fileId): void
    {
        $this->assertDriveId($fileId);
        Http::withToken($this->accessToken())
            ->patch("https://www.googleapis.com/drive/v3/files/{$fileId}?supportsAllDrives=true", [
                'trashed' => true,
            ])
            ->throw();
    }

    public function metadata(string $fileId): array
    {
        $this->assertDriveId($fileId);
        return Http::withToken($this->accessToken())
            ->get("https://www.googleapis.com/drive/v3/files/{$fileId}", [
                'fields' => 'id,name,mimeType,parents',
                'supportsAllDrives' => 'true',
            ])->throw()->json();
    }

    public function download(array $metadata): array
    {
        $id = $metadata['id'];
        $mime = $metadata['mimeType'] ?? 'application/octet-stream';
        $name = $metadata['name'] ?? 'dokumen';
        $export = $this->nativeExport($mime, $name);
        $url = $export
            ? "https://www.googleapis.com/drive/v3/files/{$id}/export?".http_build_query(['mimeType' => $export['mime']])
            : "https://www.googleapis.com/drive/v3/files/{$id}?alt=media&supportsAllDrives=true";

        $response = Http::withToken($this->accessToken())
            ->withOptions(['stream' => true])
            ->get($url)
            ->throw();

        return [
            'response' => $response,
            'name' => $export['name'] ?? $name,
            'mime' => $export['mime'] ?? $mime,
        ];
    }

    private function folderMetadata(string $accessToken, string $folderId): array
    {
        $this->assertDriveId($folderId);
        return Http::withToken($accessToken)
            ->get("https://www.googleapis.com/drive/v3/files/{$folderId}", [
                'fields' => 'id,name,mimeType,capabilities(canAddChildren)',
                'supportsAllDrives' => 'true',
            ])->throw()->json();
    }

    private function findOrCreateChildFolder(string $accessToken, string $parentId, string $name): string
    {
        $query = "'{$parentId}' in parents and trashed = false and mimeType = '".self::FOLDER_MIME."' and name = '".str_replace("'", "\\'", $name)."'";
        $existing = Http::withToken($accessToken)
            ->get('https://www.googleapis.com/drive/v3/files', [
                'q' => $query,
                'spaces' => 'drive',
                'pageSize' => 1,
                'fields' => 'files(id,name)',
                'supportsAllDrives' => 'true',
                'includeItemsFromAllDrives' => 'true',
            ])->throw()->json('files.0');

        if (filled($existing['id'] ?? null)) {
            return $existing['id'];
        }

        $created = Http::withToken($accessToken)
            ->post('https://www.googleapis.com/drive/v3/files?supportsAllDrives=true&fields=id', [
                'name' => $name,
                'mimeType' => self::FOLDER_MIME,
                'parents' => [$parentId],
            ])->throw()->json();

        if (blank($created['id'] ?? null)) {
            throw new RuntimeException('Folder '.$name.' gagal dibuat di Google Drive.');
        }

        return $created['id'];
    }

    private function accessToken(): string
    {
        $connection = $this->connection();
        if (!$connection) throw new RuntimeException('Google Drive belum dihubungkan oleh admin.');

        if (($connection['expires_at'] ?? 0) > now()->addMinute()->timestamp) {
            return $connection['access_token'];
        }

        $response = Http::asForm()->post('https://oauth2.googleapis.com/token', [
            'client_id' => config('services.google_drive.client_id'),
            'client_secret' => config('services.google_drive.client_secret'),
            'refresh_token' => $connection['refresh_token'],
            'grant_type' => 'refresh_token',
        ])->throw();

        $connection['access_token'] = $response->json('access_token');
        $connection['expires_at'] = now()->addSeconds((int) $response->json('expires_in', 3600))->timestamp;
        Setting::put(self::CONNECTION_KEY, Crypt::encryptString(json_encode($connection)));
        return $connection['access_token'];
    }

    private function connection(): ?array
    {
        $encrypted = Setting::valueOf(self::CONNECTION_KEY);
        if (!$encrypted) return null;
        try {
            $connection = json_decode(Crypt::decryptString($encrypted), true, flags: JSON_THROW_ON_ERROR);
            return is_array($connection) && filled($connection['refresh_token'] ?? null) ? $connection : null;
        } catch (Throwable) {
            return null;
        }
    }

    private function folderMap(): array
    {
        $map = json_decode(Setting::valueOf(self::FOLDER_MAP_KEY) ?: '[]', true);
        return is_array($map) ? $map : [];
    }

    private function nativeExport(string $mime, string $name): ?array
    {
        $formats = [
            'application/vnd.google-apps.document' => ['mime' => 'application/pdf', 'ext' => 'pdf'],
            'application/vnd.google-apps.spreadsheet' => ['mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'ext' => 'xlsx'],
            'application/vnd.google-apps.presentation' => ['mime' => 'application/pdf', 'ext' => 'pdf'],
            'application/vnd.google-apps.drawing' => ['mime' => 'application/pdf', 'ext' => 'pdf'],
        ];
        if (!isset($formats[$mime])) return null;
        return [
            'mime' => $formats[$mime]['mime'],
            'name' => pathinfo($name, PATHINFO_FILENAME).'.'.$formats[$mime]['ext'],
        ];
    }

    private function assertDriveId(string $value): void
    {
        if (!preg_match('/^[A-Za-z0-9_-]{10,}$/', $value)) {
            throw new RuntimeException('ID Google Drive tidak valid.');
        }
    }
}
