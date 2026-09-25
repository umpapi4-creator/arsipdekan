@extends('layouts.app')

@section('title', 'Arsip — Sistem Arsip Prodi')

@php
    $isAdmin = $user->isAdmin();
    $ownProdi = $user->prodi();
    $connected = $status === 'connected';
    $formatBytes = function ($bytes) {
        $bytes = (int) $bytes;
        if ($bytes <= 0) return '0 KB';
        $units = ['B', 'KB', 'MB', 'GB'];
        $power = min((int) floor(log($bytes, 1024)), count($units) - 1);
        return number_format($bytes / (1024 ** $power), $bytes / (1024 ** $power) >= 10 ? 0 : 1, ',', '.').' '.$units[$power];
    };
    $totalBytes = collect($files)->sum(fn ($file) => (int) ($file['size'] ?? 0));
@endphp

@section('content')
<header class="app-header">
    <div class="header-inner">
        <a class="brand" href="{{ route('dashboard') }}">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 7h16M6 7l1 13h10l1-13M9 11h6"/></svg>
            </span>
            <span><strong>Sistem Arsip Prodi</strong><small>{{ $user->name }}</small></span>
        </a>
        <div class="header-actions">
            <span class="status-pill {{ $connected ? 'online' : '' }}"><i></i>{{ $connected ? 'Drive terhubung' : 'Drive belum terhubung' }}</span>
            <form method="POST" action="{{ route('logout') }}">@csrf<button class="icon-btn" title="Keluar" aria-label="Keluar"><svg viewBox="0 0 24 24"><path d="M10 17l5-5-5-5M15 12H3M15 3h4a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2h-4"/></svg></button></form>
        </div>
    </div>
</header>

<main class="workspace">
    <section class="numbering-panel">
        <div class="numbering-summary">
            <div class="numbering-title">
                <span class="numbering-icon" aria-hidden="true"><svg viewBox="0 0 24 24"><path d="M7 3h10v18H7zM10 7h4M10 11h4M10 15h2"/></svg></span>
                <div><span class="eyebrow">PENOMORAN SURAT</span><h2>Nomor terakhir: {{ str_pad((string) $lastLetterNumber, 3, '0', STR_PAD_LEFT) }}</h2><p>Satu urutan untuk seluruh prodi. Nomor berikutnya otomatis tidak akan dipakai dua kali.</p></div>
            </div>
            <div class="next-number-box"><small>Nomor berikutnya</small><strong>{{ str_pad((string) $nextLetterNumber, 3, '0', STR_PAD_LEFT) }}</strong></div>
        </div>

        <form class="numbering-form" method="POST" action="{{ route('letter-number.store') }}" enctype="multipart/form-data">
            @csrf
            @if ($isAdmin)
                <div class="field"><label for="numberingProdi">Program studi</label><select id="numberingProdi" name="prodi_key" required>@foreach ($prodiItems as $key => $prodi)<option value="{{ $key }}" @selected($uploadProdi === $key)>{{ $prodi['name'] }}</option>@endforeach</select></div>
            @else
                <input type="hidden" name="prodi_key" value="{{ $user->prodi_key }}">
                <div class="field"><label>Program studi</label><div class="readonly-field">{{ $ownProdi['name'] }}</div></div>
            @endif
            <div class="field"><label for="letterSubject">Keterangan surat <span>(opsional)</span></label><input id="letterSubject" name="subject" maxlength="255" placeholder="Contoh: Surat tugas kegiatan"></div>
            <div class="field"><label for="letterAttachment">Foto surat <span>(opsional)</span></label><input id="letterAttachment" name="attachment" type="file" accept="image/jpeg,image/png,image/webp" capture="environment"></div>
            <button class="btn btn-primary numbering-submit" type="submit"><svg viewBox="0 0 24 24"><path d="M12 5v14M5 12h14"/></svg> Gunakan nomor {{ str_pad((string) $nextLetterNumber, 3, '0', STR_PAD_LEFT) }}</button>
        </form>

        @if ($letterNumbers->isNotEmpty())
            <details class="numbering-history">
                <summary>Riwayat nomor terbaru <span>{{ $letterNumbers->count() }}</span></summary>
                <div class="numbering-history-wrap">
                    <table class="numbering-table">
                        <thead><tr><th>Nomor</th>@if($isAdmin)<th>Prodi</th>@endif<th>Keterangan</th><th>Tanggal</th><th>Foto surat</th></tr></thead>
                        <tbody>
                        @foreach ($letterNumbers as $letterNumber)
                            <tr>
                                <td><strong class="letter-number-value">{{ str_pad((string) $letterNumber->number, 3, '0', STR_PAD_LEFT) }}</strong></td>
                                @if($isAdmin)<td><span class="prodi-badge">{{ $prodiItems[$letterNumber->prodi_key]['name'] ?? $letterNumber->prodi_key }}</span></td>@endif
                                <td>{{ $letterNumber->subject ?: '—' }}</td>
                                <td>{{ $letterNumber->created_at->timezone('Asia/Jakarta')->translatedFormat('d M Y H:i') }}</td>
                                <td>
                                    <div class="attachment-actions">
                                        @if ($letterNumber->attachment_path)
                                            <a class="mini-link" href="{{ route('letter-number.attachment', $letterNumber) }}" target="_blank">Lihat foto</a>
                                        @endif
                                        <form method="POST" action="{{ route('letter-number.attachment.update', $letterNumber) }}" enctype="multipart/form-data">
                                            @csrf
                                            @method('PATCH')
                                            <label class="mini-upload">{{ $letterNumber->attachment_path ? 'Ganti' : 'Lampirkan' }}<input type="file" name="attachment" accept="image/jpeg,image/png,image/webp" capture="environment" onchange="this.form.submit()" required></label>
                                        </form>
                                    </div>
                                </td>
                            </tr>
                        @endforeach
                        </tbody>
                    </table>
                </div>
            </details>
        @endif
    </section>
    <section class="workspace-toolbar">
        <div>
            <h1>{{ $isAdmin ? 'Arsip seluruh unit' : 'Arsip '.$ownProdi['name'] }}</h1>
            <p>{{ $isAdmin ? 'Kelola dokumen dari delapan folder unit arsip.' : 'Akses hanya tersedia untuk folder unit ini.' }}</p>
        </div>
        <div class="toolbar-actions">
            @if ($isAdmin)
                <form method="GET" action="{{ route('dashboard') }}">
                    <select name="prodi" onchange="this.form.submit()" aria-label="Filter program studi">
                        <option value="all" @selected($selected === 'all')>Semua prodi</option>
                        @foreach ($prodiItems as $key => $prodi)
                            <option value="{{ $key }}" @selected($selected === $key)>{{ $prodi['name'] }}</option>
                        @endforeach
                    </select>
                </form>
            @endif
            <a class="btn btn-outline btn-compact" href="{{ request()->fullUrl() }}"><svg viewBox="0 0 24 24"><path d="M20 11a8 8 0 1 0-2 5.3M20 4v7h-7"/></svg> Muat ulang</a>
            @if ($isAdmin && $connected)
                <form method="POST" action="{{ route('google.disconnect') }}" onsubmit="return confirm('Putuskan Google Drive untuk seluruh akun prodi?')">@csrf<button class="icon-btn surface" title="Putuskan Google Drive"><svg viewBox="0 0 24 24"><path d="M2 2l20 20M5.6 5.6A8 8 0 0 0 12 20h5a5 5 0 0 0 3.9-1.9M18.6 14.6A5 5 0 0 0 17 5a7 7 0 0 0-11.8-1.9"/></svg></button></form>
            @endif
        </div>
    </section>

    @if (session('success')) <div class="alert alert-success">{{ session('success') }}</div> @endif
    @if (session('error')) <div class="alert alert-error">{{ session('error') }}</div> @endif
    @if ($errors->any()) <div class="alert alert-error">{{ $errors->first() }}</div> @endif

    @if ($status === 'needs_configuration')
        <div class="notice notice-warning">
            <svg viewBox="0 0 24 24"><path d="M12 9v4M12 17h.01M10.3 3.6 2.3 18a2 2 0 0 0 1.7 3h16a2 2 0 0 0 1.7-3l-8-14.4a2 2 0 0 0-3.4 0Z"/></svg>
            <div><strong>{{ $isAdmin ? 'Selesaikan konfigurasi Google Drive' : 'Google Drive sedang disiapkan admin' }}</strong><p>{{ $isAdmin ? 'Isi GOOGLE_DRIVE_CLIENT_ID dan GOOGLE_DRIVE_CLIENT_SECRET di file .env. Redirect URI: '.config('services.google_drive.redirect_uri') : 'Kamu dapat mulai setelah administrator menyelesaikan koneksi.' }}</p></div>
        </div>
    @elseif ($status === 'disconnected')
        <div class="notice notice-info">
            <svg viewBox="0 0 24 24"><path d="M17 19H7a5 5 0 1 1 .7-9.9A7 7 0 0 1 21 12a4 4 0 0 1-4 7Z"/></svg>
            <div><strong>{{ $isAdmin ? 'Hubungkan Google Drive' : 'Menunggu koneksi dari admin' }}</strong><p>{{ $isAdmin ? 'Satu koneksi admin akan mengaktifkan delapan folder unit arsip.' : 'Hubungi administrator agar arsip dapat digunakan.' }}</p></div>
            @if ($isAdmin)<a class="btn btn-primary notice-action" href="{{ route('google.connect') }}">Hubungkan sekarang</a>@endif
        </div>
    @elseif ($status === 'error')
        <div class="notice notice-danger">
            <svg viewBox="0 0 24 24"><circle cx="12" cy="12" r="9"/><path d="M12 7v6M12 17h.01"/></svg>
            <div><strong>Koneksi perlu diperiksa</strong><p>{{ $driveError }}</p></div>
            @if ($isAdmin)<a class="btn btn-primary notice-action" href="{{ route('google.connect') }}">Hubungkan ulang</a>@endif
        </div>
    @endif

    <div class="dashboard-grid">
        <section class="card upload-card">
            <div class="card-head"><span class="card-icon blue"><svg viewBox="0 0 24 24"><path d="M12 16V4M7 9l5-5 5 5M5 14v5a1 1 0 0 0 1 1h12a1 1 0 0 0 1-1v-5"/></svg></span><div><h2>Masukkan berkas</h2><p>Unggah file atau pindai kamera</p></div></div>
            <div class="card-body">
                <form id="uploadForm" method="POST" action="{{ route('archive.upload') }}" enctype="multipart/form-data">
                    @csrf
                    @if ($isAdmin)
                        <div class="field compact-field"><label for="uploadProdi">Simpan ke folder</label><select id="uploadProdi" name="prodi_key" required>@foreach ($prodiItems as $key => $prodi)<option value="{{ $key }}" @selected($uploadProdi === $key)>{{ $prodi['name'] }}</option>@endforeach</select></div>
                    @else
                        <input type="hidden" name="prodi_key" value="{{ $user->prodi_key }}">
                    @endif

                    <button id="openScanner" class="btn btn-scanner btn-block" type="button" @disabled(!$connected)><svg viewBox="0 0 24 24"><path d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M8 8h8v8H8z"/></svg> Pindai dokumen dengan kamera</button>

                    <label class="dropzone {{ !$connected ? 'disabled' : '' }}" id="dropzone">
                        <input id="fileInput" type="file" name="file" @disabled(!$connected) required>
                        <span class="drop-icon"><svg viewBox="0 0 24 24"><path d="M17 19H7a5 5 0 1 1 .7-9.9A7 7 0 0 1 21 12a4 4 0 0 1-4 7Z"/><path d="m9 13 3-3 3 3M12 10v7"/></svg></span>
                        <strong id="fileName">Pilih atau tarik file</strong>
                        <small id="fileMeta">PDF, Word, Excel, gambar, ZIP • maksimal 100 MB</small>
                    </label>
                    <button class="btn btn-primary btn-block upload-submit" type="submit" @disabled(!$connected)>Unggah ke Google Drive</button>
                </form>
            </div>
        </section>

        <section class="card files-card">
            <div class="card-head files-head"><div class="head-group"><span class="card-icon cyan"><svg viewBox="0 0 24 24"><path d="M3 6h7l2 2h9v11H3z"/></svg></span><div><h2>Dokumen tersimpan</h2><p>{{ $connected ? count($files).' file • '.$formatBytes($totalBytes) : 'Menunggu koneksi Google Drive' }}</p></div></div>@if($connected)<span class="sync-badge"><i></i>Tersinkron</span>@endif</div>
            <div class="file-area">
                @if (empty($files))
                    <div class="empty-state"><span><svg viewBox="0 0 24 24"><path d="M6 2h8l4 4v16H6zM14 2v5h5M9 13h6M9 17h4"/></svg></span><h3>{{ $connected ? 'Belum ada file' : 'Arsip belum ditampilkan' }}</h3><p>{{ $connected ? 'Unggah file atau pindai dokumen. Hasilnya akan muncul di sini.' : 'Admin perlu menghubungkan sistem ke Google Drive terlebih dahulu.' }}</p></div>
                @else
                    <div class="table-wrap"><table><thead><tr><th>Nama file</th>@if($isAdmin)<th class="hide-tablet">Prodi</th>@endif<th class="hide-mobile">Ukuran</th><th class="hide-small">Diperbarui</th><th></th></tr></thead><tbody>
                    @foreach ($files as $file)
                        @php
                            $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
                            $kind = str_contains($file['mimeType'] ?? '', 'pdf') ? 'pdf' : (str_starts_with($file['mimeType'] ?? '', 'image/') ? 'image' : (in_array($ext, ['xls','xlsx','csv']) ? 'sheet' : (in_array($ext, ['zip','rar','7z']) ? 'zip' : 'doc')));
                        @endphp
                        <tr><td><div class="file-name"><span class="file-icon {{ $kind }}"><svg viewBox="0 0 24 24"><path d="M6 2h8l4 4v16H6zM14 2v5h5M9 13h6M9 17h4"/></svg></span><span><a class="file-title-link" href="{{ route('archive.open', $file['id']) }}" target="_blank" rel="noopener" title="Preview {{ $file['name'] }}">{{ $file['name'] }}</a><small>{{ $isAdmin ? $file['prodi_name'].' • ' : '' }}{{ $formatBytes($file['size'] ?? 0) }}</small></span></div></td>@if($isAdmin)<td class="hide-tablet"><span class="prodi-badge">{{ $file['prodi_name'] }}</span></td>@endif<td class="hide-mobile">{{ $formatBytes($file['size'] ?? 0) }}</td><td class="hide-small">{{ \Carbon\Carbon::parse($file['modifiedTime'])->timezone('Asia/Jakarta')->translatedFormat('d M Y') }}</td><td class="action-cell"><div class="file-actions"><a class="open-btn" href="{{ route('archive.open', $file['id']) }}" target="_blank" rel="noopener" title="Preview {{ $file['name'] }}"><svg viewBox="0 0 24 24"><path d="M2.5 12s3.5-6 9.5-6 9.5 6 9.5 6-3.5 6-9.5 6-9.5-6-9.5-6Z"/><circle cx="12" cy="12" r="2.8"/></svg><span>Preview</span></a><form method="POST" action="{{ route('archive.destroy', $file['id']) }}" onsubmit="return confirm('Hapus {{ addslashes($file['name']) }} dari arsip? File akan dipindahkan ke Sampah Google Drive.')">@csrf @method('DELETE')<button class="delete-btn" type="submit" title="Hapus {{ $file['name'] }}"><svg viewBox="0 0 24 24"><path d="M4 7h16M9 7V4h6v3M7 7l1 13h8l1-13M10 11v5M14 11v5"/></svg><span>Hapus</span></button></form></div></td></tr>
                    @endforeach
                    </tbody></table></div>
                @endif
            </div>
        </section>
    </div>
</main>

<div class="modal" id="scannerModal" aria-hidden="true">
    <div class="modal-backdrop" data-close-scanner></div>
    <section class="modal-panel" role="dialog" aria-modal="true" aria-labelledby="scannerTitle">
        <button class="modal-close" type="button" data-close-scanner aria-label="Tutup">×</button>
        <div class="modal-head"><span class="card-icon blue"><svg viewBox="0 0 24 24"><path d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M8 8h8v8H8z"/></svg></span><div><h2 id="scannerTitle">Pindai dokumen</h2><p>Foto setiap halaman, lalu sistem menggabungkannya menjadi satu PDF.</p></div></div>
        <div class="field compact-field"><label for="scanName">Nama dokumen</label><input id="scanName" value="Hasil Pindai {{ now()->format('Y-m-d Hi') }}" placeholder="Contoh: Surat Masuk September 2026"></div>
        <input id="cameraInput" type="file" accept="image/*" capture="environment" multiple hidden>
        <button id="cameraStarter" class="camera-empty" type="button"><span><svg viewBox="0 0 24 24"><path d="M14.5 5 13 3h-2L9.5 5H5a2 2 0 0 0-2 2v11a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V7a2 2 0 0 0-2-2z"/><circle cx="12" cy="12" r="4"/></svg></span><strong>Buka kamera</strong><small>Letakkan berkas di tempat terang dan foto satu halaman penuh.</small></button>
        <div id="scanListWrap" class="scan-list-wrap" hidden><div class="scan-list-head"><strong><span id="scanCount">0</span> halaman</strong><button id="addScanPage" class="btn btn-outline btn-compact" type="button">+ Tambah halaman</button></div><div id="scanList" class="scan-list"></div></div>
        <div id="scanProgress" class="scan-progress" hidden><span class="spinner"></span><div><strong id="scanProgressTitle">Menyusun halaman menjadi PDF</strong><small>Jangan tutup halaman ini.</small></div></div>
        <div class="modal-actions"><button class="btn btn-outline" type="button" data-close-scanner>Batal</button><button id="saveScan" class="btn btn-primary" type="button" disabled><svg viewBox="0 0 24 24"><path d="M4 7V4h3M17 4h3v3M20 17v3h-3M7 20H4v-3M8 8h8v8H8z"/></svg> Jadikan PDF & simpan</button></div>
    </section>
</div>

<div id="toast" class="toast" role="status" aria-live="polite"></div>
@endsection

@push('scripts')
<script src="{{ asset('vendor/pdf-lib.min.js') }}"></script>
<script>
    window.ARSIP_CONFIG = {{ Illuminate\Support\Js::from([
        'uploadUrl' => route('archive.upload'),
        'csrf' => csrf_token(),
        'isAdmin' => $isAdmin,
        'prodiKey' => $user->prodi_key,
    ]) }};
</script>
<script src="{{ asset('js/archive.js') }}"></script>
@endpush
