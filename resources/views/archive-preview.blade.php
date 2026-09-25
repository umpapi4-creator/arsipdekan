@extends('layouts.app')

@section('title', 'Preview — '.$fileName)

@push('head')
<style>
    .preview-page{min-height:100vh;background:#f4f7fb;color:#0f172a;font-family:Arial,sans-serif}
    .preview-top{position:sticky;top:0;z-index:20;display:flex;align-items:center;gap:14px;padding:14px 20px;background:#fff;border-bottom:1px solid #dbe4ef;box-shadow:0 2px 10px rgba(15,23,42,.05)}
    .preview-back{display:inline-flex;align-items:center;justify-content:center;width:40px;height:40px;border:1px solid #d9e2ef;border-radius:10px;text-decoration:none;color:#1d4ed8;background:#fff;font-size:22px;font-weight:700}
    .preview-title{min-width:0;flex:1}.preview-title strong{display:block;font-size:15px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis}.preview-title small{display:block;color:#64748b;margin-top:3px}
    .preview-actions{display:flex;align-items:center;gap:10px}
    .preview-download{display:inline-flex;align-items:center;gap:7px;padding:9px 13px;border-radius:10px;background:#1d4ed8;color:#fff;text-decoration:none;font-size:13px;font-weight:700;box-shadow:0 4px 12px rgba(29,78,216,.18)}
    .preview-download:hover{background:#1e40af}
    .preview-download svg{width:17px;height:17px;fill:none;stroke:currentColor;stroke-width:2;stroke-linecap:round;stroke-linejoin:round}
    .preview-badge{font-size:12px;font-weight:700;padding:7px 11px;border-radius:999px;background:#ecfdf3;color:#15803d;border:1px solid #bbf7d0}
    .preview-shell{padding:18px;max-width:1500px;margin:0 auto}
    .preview-stage{min-height:calc(100vh - 110px);background:#fff;border:1px solid #dbe4ef;border-radius:16px;overflow:hidden;box-shadow:0 10px 30px rgba(15,23,42,.06)}
    .preview-loading,.preview-message{min-height:420px;display:flex;flex-direction:column;align-items:center;justify-content:center;text-align:center;padding:32px;color:#64748b}
    .preview-loading strong,.preview-message strong{font-size:18px;color:#0f172a;margin-bottom:8px}.preview-message p{max-width:620px;line-height:1.6;margin:0}
    .preview-frame{width:100%;height:calc(100vh - 112px);border:0;background:#e5e7eb}
    .preview-image-wrap{display:flex;align-items:flex-start;justify-content:center;min-height:calc(100vh - 112px);padding:24px;background:#e5e7eb}.preview-image-wrap img{max-width:100%;height:auto;box-shadow:0 6px 28px rgba(0,0,0,.15);background:#fff}
    #docxContainer{background:#e5e7eb;min-height:calc(100vh - 112px);padding:24px;overflow:auto}.docx-wrapper{background:transparent!important;padding:0!important}.docx-wrapper>section.docx{box-shadow:0 4px 18px rgba(15,23,42,.16);margin:0 auto 24px!important}
    .sheet-toolbar{display:flex;gap:8px;flex-wrap:wrap;padding:12px;border-bottom:1px solid #e2e8f0;background:#f8fafc;position:sticky;top:0}.sheet-tab{border:1px solid #cbd5e1;background:#fff;border-radius:8px;padding:7px 12px;cursor:pointer;font-weight:700}.sheet-tab.active{background:#1d4ed8;color:#fff;border-color:#1d4ed8}
    .sheet-wrap{overflow:auto;padding:16px}.sheet-wrap table{border-collapse:collapse;min-width:100%;font-size:13px}.sheet-wrap th,.sheet-wrap td{border:1px solid #dbe4ef;padding:7px 9px;white-space:nowrap}.sheet-wrap th{background:#f8fafc}
    .text-preview{margin:0;padding:24px;min-height:calc(100vh - 112px);white-space:pre-wrap;word-break:break-word;font:14px/1.65 Consolas,monospace;background:#fff;color:#1e293b}
    @media(max-width:700px){.preview-top{padding:10px 12px}.preview-shell{padding:8px}.preview-badge{display:none}.preview-download span{display:none}.preview-download{padding:10px}.preview-stage{border-radius:10px}.preview-frame{height:calc(100vh - 92px)}#docxContainer{padding:8px}}
</style>
@endpush

@section('content')
<div class="preview-page">
    <header class="preview-top">
        <a class="preview-back" href="{{ route('dashboard') }}" title="Kembali">‹</a>
        <div class="preview-title"><strong>{{ $fileName }}</strong><small>Preview dokumen</small></div>
        <div class="preview-actions">
            <a class="preview-download" href="{{ route('archive.download', $fileId) }}" title="Download {{ $fileName }}">
                <svg viewBox="0 0 24 24"><path d="M12 3v12M7 10l5 5 5-5M5 21h14"/></svg>
                <span>Download</span>
            </a>
            <span class="preview-badge">Preview</span>
        </div>
    </header>
    <main class="preview-shell">
        <section class="preview-stage" id="previewStage">
            <div class="preview-loading" id="previewLoading"><strong>Memuat preview…</strong><span>Dokumen sedang dibaca dari Google Drive.</span></div>

            @if ($previewType === 'pdf')
                <iframe id="nativePreview" class="preview-frame" src="{{ $contentUrl }}#toolbar=0&navpanes=0" title="Preview {{ $fileName }}" hidden></iframe>
            @elseif ($previewType === 'image')
                <div id="imageWrap" class="preview-image-wrap" hidden><img id="imagePreview" alt="Preview {{ $fileName }}"></div>
            @elseif ($previewType === 'docx')
                <div id="docxContainer" hidden></div>
            @elseif ($previewType === 'sheet')
                <div id="sheetPreview" hidden><div class="sheet-toolbar" id="sheetTabs"></div><div class="sheet-wrap" id="sheetContent"></div></div>
            @elseif ($previewType === 'text')
                <pre id="textPreview" class="text-preview" hidden></pre>
            @else
                <div class="preview-message" id="unsupported" hidden><strong>Format ini belum dapat dipreview.</strong><p>File tetap tersimpan di arsip dan tidak diunduh otomatis. Untuk preview di web, gunakan PDF, gambar, DOCX, XLSX/XLS/CSV, atau file teks.</p></div>
            @endif
        </section>
    </main>
</div>
@endsection

@push('scripts')
@if ($previewType === 'docx')
<script src="https://cdn.jsdelivr.net/npm/jszip@3.10.1/dist/jszip.min.js"></script>
<script src="https://cdn.jsdelivr.net/npm/docx-preview@0.3.6/dist/docx-preview.min.js"></script>
@elseif ($previewType === 'sheet')
<script src="https://cdn.jsdelivr.net/npm/xlsx@0.18.5/dist/xlsx.full.min.js"></script>
@endif
<script>
(() => {
    const type = @json($previewType);
    const url = @json($contentUrl);
    const loading = document.getElementById('previewLoading');
    const fail = (message) => {
        if (loading) loading.remove();
        const box = document.createElement('div');
        box.className = 'preview-message';
        box.innerHTML = '<strong>Preview tidak dapat ditampilkan.</strong><p></p>';
        box.querySelector('p').textContent = message || 'Coba muat ulang halaman atau periksa koneksi Google Drive.';
        document.getElementById('previewStage').appendChild(box);
    };

    if (type === 'pdf') {
        const frame = document.getElementById('nativePreview');
        frame.hidden = false;
        frame.addEventListener('load', () => loading && loading.remove(), {once:true});
        setTimeout(() => loading && loading.remove(), 1800);
        return;
    }

    if (type === 'image') {
        const img = document.getElementById('imagePreview');
        const wrap = document.getElementById('imageWrap');
        img.onload = () => { loading && loading.remove(); wrap.hidden = false; };
        img.onerror = () => fail('Gambar gagal dimuat.');
        img.src = url;
        return;
    }

    if (type === 'unsupported') {
        loading && loading.remove();
        document.getElementById('unsupported').hidden = false;
        return;
    }

    fetch(url, {credentials:'same-origin', cache:'no-store'})
        .then(response => {
            if (!response.ok) throw new Error('HTTP ' + response.status);
            if (type === 'text') return response.text();
            return response.arrayBuffer();
        })
        .then(async data => {
            if (type === 'docx') {
                if (!window.docx || typeof window.docx.renderAsync !== 'function') throw new Error('Komponen preview Word gagal dimuat.');
                const container = document.getElementById('docxContainer');
                container.hidden = false;
                await window.docx.renderAsync(data, container, null, {inWrapper:true, breakPages:true, renderHeaders:true, renderFooters:true, useBase64URL:true});
            } else if (type === 'sheet') {
                if (!window.XLSX) throw new Error('Komponen preview Excel gagal dimuat.');
                const workbook = XLSX.read(data, {type:'array'});
                const tabs = document.getElementById('sheetTabs');
                const content = document.getElementById('sheetContent');
                const root = document.getElementById('sheetPreview');
                const showSheet = (name, button) => {
                    tabs.querySelectorAll('.sheet-tab').forEach(el => el.classList.remove('active'));
                    button.classList.add('active');
                    content.innerHTML = XLSX.utils.sheet_to_html(workbook.Sheets[name], {editable:false});
                };
                workbook.SheetNames.forEach((name, index) => {
                    const button = document.createElement('button');
                    button.type = 'button'; button.className = 'sheet-tab'; button.textContent = name;
                    button.addEventListener('click', () => showSheet(name, button));
                    tabs.appendChild(button);
                    if (index === 0) showSheet(name, button);
                });
                root.hidden = false;
            } else if (type === 'text') {
                const pre = document.getElementById('textPreview');
                pre.textContent = data;
                pre.hidden = false;
            }
            loading && loading.remove();
        })
        .catch(error => fail(error.message));
})();
</script>
@endpush
