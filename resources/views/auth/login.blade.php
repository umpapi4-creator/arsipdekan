@extends('layouts.app')

@section('title', 'Login — Sistem Arsip Prodi')

@section('content')
<main class="login-page">
    <section class="login-card" aria-labelledby="login-title">
        <div class="brand brand-login">
            <span class="brand-mark" aria-hidden="true">
                <svg viewBox="0 0 24 24"><path d="M4 7h16M6 7l1 13h10l1-13M9 11h6"/></svg>
            </span>
            <div>
                <h1 id="login-title">Sistem Arsip Prodi</h1>
                <p>Masuk menggunakan akun unit kerja</p>
            </div>
        </div>

        <form method="POST" action="{{ route('login.store') }}" class="login-form">
            @csrf
            <div class="field">
                <label for="username">Username</label>
                <div class="input-icon">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><circle cx="12" cy="8" r="4"/><path d="M4 21a8 8 0 0 1 16 0"/></svg>
                    <input id="username" name="username" value="{{ old('username') }}" autocomplete="username" placeholder="Masukkan username" required autofocus>
                </div>
            </div>

            <div class="field">
                <label for="password">Password</label>
                <div class="input-icon password-wrap">
                    <svg viewBox="0 0 24 24" aria-hidden="true"><rect x="5" y="10" width="14" height="11" rx="2"/><path d="M8 10V7a4 4 0 0 1 8 0v3"/></svg>
                    <input id="password" name="password" type="password" autocomplete="current-password" placeholder="Masukkan password" required>
                    <button type="button" class="password-toggle" data-password-toggle aria-label="Tampilkan password">
                        <svg viewBox="0 0 24 24"><path d="M2 12s3.5-6 10-6 10 6 10 6-3.5 6-10 6S2 12 2 12Z"/><circle cx="12" cy="12" r="2.5"/></svg>
                    </button>
                </div>
            </div>

            @if ($errors->any())
                <div class="alert alert-error" role="alert">{{ $errors->first() }}</div>
            @endif

            <label class="remember"><input type="checkbox" name="remember" value="1"> Ingat saya</label>
            <button class="btn btn-primary btn-block" type="submit">Masuk</button>
        </form>

        <div class="login-account-list" aria-label="Daftar akun unit">
            <div class="login-account-head">
                <div>
                    <strong>Daftar akun</strong>
                    <span>Semua password: <b>123456</b></span>
                </div>
            </div>
            <div class="login-account-grid">
                <div class="login-account-item"><span>D3 Kebidanan</span><code>d3kebidanan</code></div>
                <div class="login-account-item"><span>D3 Keperawatan</span><code>d3keperawatan</code></div>
                <div class="login-account-item"><span>D4 MIKES</span><code>d4mikes</code></div>
                <div class="login-account-item"><span>S1 Farmasi</span><code>s1farmasi</code></div>
                <div class="login-account-item"><span>S1 Kebidanan</span><code>s1kebidanan</code></div>
                <div class="login-account-item"><span>S1 Keperawatan</span><code>s1keperawatan</code></div>
                <div class="login-account-item"><span>S1 Teknologi Informasi</span><code>s1teknologiinformasi</code></div>
                <div class="login-account-item"><span>Arsip Dekan</span><code>dekan</code></div>
            </div>
        </div>
    </section>
</main>
@endsection

@push('scripts')
<script>
document.querySelector('[data-password-toggle]')?.addEventListener('click', function () {
    const input = document.getElementById('password');
    input.type = input.type === 'password' ? 'text' : 'password';
    this.setAttribute('aria-label', input.type === 'password' ? 'Tampilkan password' : 'Sembunyikan password');
});
</script>
@endpush
