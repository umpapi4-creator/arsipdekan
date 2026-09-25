# Sistem Arsip Prodi — Laravel 10

Versi tahap awal sistem arsip berbasis **Laravel 10** dan **PHP 8.1**. Dokumen disimpan langsung ke delapan folder unit arsip pada Google Drive.

## Fitur yang sudah tersedia

- Login terpisah untuk 1 admin dan 8 unit arsip (7 program studi + Arsip Dekan).
- Admin dapat melihat seluruh folder, memfilter prodi, dan memilih folder tujuan unggahan.
- Akun unit hanya dapat melihat, mengunggah, memindai, preview, dan menghapus dokumen pada foldernya sendiri.
- Unggah berbagai jenis file sampai 100 MB.
- Pindai berkas memakai kamera ponsel, tambah/hapus halaman, gabungkan menjadi PDF, lalu kirim langsung ke Drive.
- Unduh file biasa serta ekspor Google Docs/Slides ke PDF dan Google Sheets ke XLSX.
- Satu koneksi Google Drive terpusat yang hanya dapat diatur admin.
- Token Google disimpan terenkripsi menggunakan `APP_KEY` Laravel.

## Persyaratan

- PHP 8.1 atau 8.2
- Composer 2
- MySQL/MariaDB
- Ekstensi PHP: `curl`, `fileinfo`, `mbstring`, `openssl`, `pdo_mysql`
- Laravel 10

## Instalasi cepat di Laragon

1. Ekstrak folder ini ke `C:\laragon\www\arsip-prodi`.
2. Buka Laragon, jalankan **Start All**, lalu buka Terminal Laragon.
3. Buat database MySQL bernama `arsip_prodi` melalui HeidiSQL/phpMyAdmin, atau jalankan:

   ```sql
   CREATE DATABASE arsip_prodi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
   ```

4. Masuk ke folder proyek dan jalankan:

   ```bash
   composer install
   copy .env.example .env
   php artisan key:generate
   php artisan migrate --seed
   php artisan serve
   ```

5. Buka `http://127.0.0.1:8000`.

Alternatif: sesudah database dibuat, klik dua kali `SETUP_WINDOWS.bat`.

## Akun awal

| Pengguna | Username | Password awal |
|---|---|---|
| Administrator | `admin` | `Admin@Arsip26` |
| D3 Kebidanan | `d3kebidanan` | `123456` |
| D3 Keperawatan | `d3keperawatan` | `123456` |
| D4 MIKES | `d4mikes` | `123456` |
| S1 Farmasi | `s1farmasi` | `123456` |
| S1 Kebidanan | `s1kebidanan` | `123456` |
| S1 Keperawatan | `s1keperawatan` | `123456` |
| S1 Teknologi Informasi | `s1teknologiinformasi` | `123456` |
| Arsip Dekan | `dekan` | `123456` |

Ganti password awal di `.env` sebelum menjalankan `php artisan migrate --seed` untuk pemakaian sebenarnya.

## Mengaktifkan Google Drive

Tautan berbagi Drive saja tidak dapat memberikan izin unggah dari aplikasi. Buat kredensial OAuth agar sistem mendapat izin resmi:

1. Buka Google Cloud Console dan buat/ pilih sebuah project.
2. Aktifkan **Google Drive API**.
3. Konfigurasikan OAuth consent screen. Saat masih mode Testing, tambahkan akun Google admin sebagai test user.
4. Buat OAuth Client ID bertipe **Web application**.
5. Tambahkan Authorized redirect URI yang sama persis dengan aplikasi, misalnya:

   ```text
   http://127.0.0.1:8000/google/callback
   ```

6. Isi `.env`:

   ```env
   GOOGLE_DRIVE_CLIENT_ID=client_id_dari_google
   GOOGLE_DRIVE_CLIENT_SECRET=client_secret_dari_google
   GOOGLE_DRIVE_REDIRECT_URI=http://127.0.0.1:8000/google/callback
   ```

7. Jalankan `php artisan config:clear`, login sebagai admin, lalu klik **Hubungkan sekarang**.
8. Pilih akun Google yang memiliki akses **Editor** ke seluruh delapan folder unit arsip.

Folder utama yang digunakan:

```text
https://drive.google.com/drive/folders/1LSfYABH8Ouwc60Wcjvzv7LpnsFzLw2D2
```

Tujuh subfolder prodi lama tetap memakai ID yang sama. Folder **Arsip Dekan** dibuat otomatis di folder utama saat koneksi Google Drive aktif.

## Catatan pemindai

- Di ponsel, tombol **Pindai dokumen dengan kamera** membuka kamera belakang.
- Beberapa halaman dapat ditambahkan sebelum dibuat menjadi satu PDF.
- Foto dikompres di browser agar PDF tidak terlalu besar.
- Untuk scanner USB, pindai melalui aplikasi scanner ke PDF/JPG lalu gunakan area **Pilih atau tarik file**.

## Keamanan sebelum dipakai publik

- Ubah seluruh password awal.
- Gunakan HTTPS pada hosting.
- Set `APP_ENV=production` dan `APP_DEBUG=false`.
- Jangan mengunggah file `.env` ke GitHub.
- Pastikan hanya akun Google pengelola yang mendapat akses Editor ke folder Drive.
