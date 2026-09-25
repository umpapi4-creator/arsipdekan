<?php

namespace App\Http\Controllers;

use App\Services\GoogleDriveService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Throwable;

class GoogleDriveController extends Controller
{
    public function connect(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        if (!$drive->configured()) return back()->with('error', 'Isi Client ID dan Client Secret Google pada file .env terlebih dahulu.');
        $state = Str::random(48);
        $request->session()->put('google_oauth_state', $state);
        return redirect()->away($drive->authorizationUrl($state));
    }

    public function callback(Request $request, GoogleDriveService $drive): RedirectResponse
    {
        $expected = (string) $request->session()->pull('google_oauth_state', '');
        $state = (string) $request->query('state', '');
        if (!$expected || !$state || !hash_equals($expected, $state)) {
            return redirect()->route('dashboard')->with('error', 'Sesi koneksi Google tidak valid. Silakan hubungkan ulang.');
        }

        try {
            $tokens = $drive->exchangeCode((string) $request->query('code'));
            $drive->verifyConfiguredFolders($tokens['access_token']);
            $drive->saveConnection($tokens);
            return redirect()->route('dashboard')->with('success', 'Google Drive berhasil dihubungkan untuk semua prodi.');
        } catch (Throwable $error) {
            report($error);
            return redirect()->route('dashboard')->with('error', 'Koneksi gagal: '.$error->getMessage());
        }
    }

    public function disconnect(GoogleDriveService $drive): RedirectResponse
    {
        $drive->disconnect();
        return redirect()->route('dashboard')->with('success', 'Koneksi Google Drive diputuskan.');
    }
}
