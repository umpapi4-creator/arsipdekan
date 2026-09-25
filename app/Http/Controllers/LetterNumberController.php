<?php

namespace App\Http\Controllers;

use App\Models\LetterNumber;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Throwable;

class LetterNumberController extends Controller
{
    private const LAST_NUMBER_KEY = 'letter_number_last';
    private const STARTING_NUMBER = 973;

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'prodi_key' => ['nullable', 'string'],
            'subject' => ['nullable', 'string', 'max:255'],
            'attachment' => ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        /** @var User $user */
        $user = $request->user();
        $prodiKey = $this->resolveProdiKey($user, $validated['prodi_key'] ?? null);
        abort_unless($prodiKey, 422, 'Program studi untuk nomor surat tidak valid.');

        $attachmentPath = null;
        if ($request->hasFile('attachment')) {
            $attachmentPath = $request->file('attachment')->store('letter-numbering', 'local');
            if (!$attachmentPath) {
                return back()->with('error', 'Foto surat gagal disimpan.');
            }
        }

        try {
            $record = DB::transaction(function () use ($user, $prodiKey, $validated, $attachmentPath) {
                DB::table('settings')->insertOrIgnore([
                    'key' => self::LAST_NUMBER_KEY,
                    'value' => (string) self::STARTING_NUMBER,
                ]);

                /** @var Setting $setting */
                $setting = Setting::query()->whereKey(self::LAST_NUMBER_KEY)->lockForUpdate()->firstOrFail();
                $nextNumber = max(self::STARTING_NUMBER, (int) $setting->value) + 1;

                $record = LetterNumber::query()->create([
                    'number' => $nextNumber,
                    'prodi_key' => $prodiKey,
                    'user_id' => $user->id,
                    'subject' => trim((string) ($validated['subject'] ?? '')) ?: null,
                    'attachment_path' => $attachmentPath,
                ]);

                $setting->value = (string) $nextNumber;
                $setting->save();

                return $record;
            });
        } catch (Throwable $error) {
            if ($attachmentPath) Storage::disk('local')->delete($attachmentPath);
            report($error);
            return back()->with('error', 'Nomor surat gagal dibuat. Silakan coba lagi.');
        }

        return back()->with('success', 'Nomor '.str_pad((string) $record->number, 3, '0', STR_PAD_LEFT).' berhasil digunakan.');
    }

    public function updateAttachment(Request $request, LetterNumber $letterNumber): RedirectResponse
    {
        $this->authorizeRecord($request->user(), $letterNumber);

        $request->validate([
            'attachment' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ]);

        $newPath = $request->file('attachment')->store('letter-numbering', 'local');
        if (!$newPath) return back()->with('error', 'Foto surat gagal disimpan.');

        $oldPath = $letterNumber->attachment_path;
        $letterNumber->attachment_path = $newPath;
        $letterNumber->save();

        if ($oldPath) Storage::disk('local')->delete($oldPath);

        return back()->with('success', 'Foto untuk nomor '.str_pad((string) $letterNumber->number, 3, '0', STR_PAD_LEFT).' berhasil dilampirkan.');
    }

    public function attachment(Request $request, LetterNumber $letterNumber): BinaryFileResponse
    {
        $this->authorizeRecord($request->user(), $letterNumber);
        abort_unless($letterNumber->attachment_path && Storage::disk('local')->exists($letterNumber->attachment_path), 404);

        return response()->file(Storage::disk('local')->path($letterNumber->attachment_path));
    }

    private function resolveProdiKey(User $user, ?string $requested): ?string
    {
        $key = $user->isAdmin() ? $requested : $user->prodi_key;
        return $key && isset(config('prodi.items')[$key]) ? $key : null;
    }

    private function authorizeRecord(User $user, LetterNumber $letterNumber): void
    {
        abort_unless($user->isAdmin() || $user->prodi_key === $letterNumber->prodi_key, 403, 'Kamu tidak memiliki akses ke nomor surat ini.');
    }
}
