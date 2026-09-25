(function () {
    'use strict';

    const fileInput = document.getElementById('fileInput');
    const fileName = document.getElementById('fileName');
    const fileMeta = document.getElementById('fileMeta');
    const dropzone = document.getElementById('dropzone');
    const uploadForm = document.getElementById('uploadForm');
    const scannerModal = document.getElementById('scannerModal');
    const openScanner = document.getElementById('openScanner');
    const cameraInput = document.getElementById('cameraInput');
    const cameraStarter = document.getElementById('cameraStarter');
    const addScanPage = document.getElementById('addScanPage');
    const scanListWrap = document.getElementById('scanListWrap');
    const scanList = document.getElementById('scanList');
    const scanCount = document.getElementById('scanCount');
    const scanName = document.getElementById('scanName');
    const saveScan = document.getElementById('saveScan');
    const scanProgress = document.getElementById('scanProgress');
    const scanProgressTitle = document.getElementById('scanProgressTitle');
    const toast = document.getElementById('toast');
    const config = window.ARSIP_CONFIG || {};
    const pages = [];
    let busy = false;

    function readableBytes(bytes) {
        if (!bytes) return '0 KB';
        const units = ['B', 'KB', 'MB', 'GB'];
        const power = Math.min(Math.floor(Math.log(bytes) / Math.log(1024)), units.length - 1);
        const value = bytes / Math.pow(1024, power);
        return new Intl.NumberFormat('id-ID', { maximumFractionDigits: value >= 10 ? 0 : 1 }).format(value) + ' ' + units[power];
    }

    function showToast(message, error) {
        if (!toast) return;
        toast.textContent = message;
        toast.classList.toggle('error', Boolean(error));
        toast.classList.add('show');
        clearTimeout(showToast.timer);
        showToast.timer = setTimeout(function () { toast.classList.remove('show'); }, 3500);
    }

    if (fileInput) {
        fileInput.addEventListener('change', function () {
            const files = Array.from(fileInput.files || []);
            if (!files.length) {
                fileName.textContent = 'Pilih atau tarik beberapa file';
                fileMeta.textContent = 'Bisa pilih banyak file sekaligus • maksimal 100 MB/file';
                return;
            }

            const oversized = files.find(function (file) {
                return file.size > 100 * 1024 * 1024;
            });
            if (oversized) {
                showToast(oversized.name + ' melebihi batas 100 MB.', true);
                fileInput.value = '';
                fileName.textContent = 'Pilih atau tarik beberapa file';
                fileMeta.textContent = 'Bisa pilih banyak file sekaligus • maksimal 100 MB/file';
                return;
            }

            const totalSize = files.reduce(function (total, file) { return total + file.size; }, 0);
            fileName.textContent = files.length === 1 ? files[0].name : files.length + ' file dipilih';
            fileMeta.textContent = readableBytes(totalSize) + ' total • siap diunggah';
        });
    }

    if (dropzone) {
        ['dragenter', 'dragover'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.add('dragging');
            });
        });
        ['dragleave', 'drop'].forEach(function (eventName) {
            dropzone.addEventListener(eventName, function (event) {
                event.preventDefault();
                dropzone.classList.remove('dragging');
            });
        });
        dropzone.addEventListener('drop', function (event) {
            if (!fileInput || !event.dataTransfer.files.length) return;
            const transfer = new DataTransfer();
            Array.from(event.dataTransfer.files).forEach(function (file) {
                transfer.items.add(file);
            });
            fileInput.files = transfer.files;
            fileInput.dispatchEvent(new Event('change'));
        });
    }

    if (uploadForm) {
        uploadForm.addEventListener('submit', async function (event) {
            event.preventDefault();

            const files = Array.from((fileInput && fileInput.files) || []);
            if (!files.length) {
                showToast('Pilih minimal satu file.', true);
                return;
            }

            const oversized = files.find(function (file) {
                return file.size > 100 * 1024 * 1024;
            });
            if (oversized) {
                showToast(oversized.name + ' melebihi batas 100 MB.', true);
                return;
            }

            const submit = uploadForm.querySelector('.upload-submit');
            const prodiSelect = document.getElementById('uploadProdi');
            const hiddenProdi = uploadForm.querySelector('input[name="prodi_key"]');
            const prodiKey = prodiSelect ? prodiSelect.value : (hiddenProdi ? hiddenProdi.value : (config.prodiKey || ''));
            const failures = [];
            let successCount = 0;

            if (submit) submit.disabled = true;
            if (fileInput) fileInput.disabled = true;

            for (let index = 0; index < files.length; index += 1) {
                const file = files[index];
                if (submit) submit.textContent = 'Mengunggah ' + (index + 1) + '/' + files.length + '...';
                fileName.textContent = files.length === 1 ? file.name : 'Mengunggah ' + (index + 1) + ' dari ' + files.length + ' file';
                fileMeta.textContent = file.name + ' • ' + readableBytes(file.size);

                try {
                    const form = new FormData();
                    form.append('file', file);
                    form.append('prodi_key', prodiKey);

                    const response = await fetch(config.uploadUrl || uploadForm.action, {
                        method: 'POST',
                        headers: {
                            'Accept': 'application/json',
                            'X-Requested-With': 'XMLHttpRequest',
                            'X-CSRF-TOKEN': config.csrf
                        },
                        body: form
                    });
                    const result = await response.json().catch(function () { return {}; });
                    if (!response.ok) {
                        const validation = result.errors ? Object.values(result.errors).flat()[0] : null;
                        throw new Error(validation || result.message || 'Gagal diunggah.');
                    }
                    successCount += 1;
                } catch (error) {
                    failures.push(file.name + ': ' + (error.message || 'gagal diunggah'));
                }
            }

            if (failures.length) {
                showToast(successCount + ' file berhasil, ' + failures.length + ' file gagal. ' + failures[0], true);
            } else {
                showToast(successCount + ' file berhasil disimpan ke Google Drive.');
            }

            if (successCount > 0) {
                window.setTimeout(function () { window.location.reload(); }, 900);
                return;
            }

            if (submit) {
                submit.disabled = false;
                submit.textContent = 'Unggah ke Google Drive';
            }
            if (fileInput) fileInput.disabled = false;
        });
    }

    function openModal() {
        if (!scannerModal) return;
        scannerModal.classList.add('open');
        scannerModal.setAttribute('aria-hidden', 'false');
        document.body.style.overflow = 'hidden';
    }

    function closeModal() {
        if (!scannerModal || busy) return;
        scannerModal.classList.remove('open');
        scannerModal.setAttribute('aria-hidden', 'true');
        document.body.style.overflow = '';
    }

    openScanner && openScanner.addEventListener('click', openModal);
    document.querySelectorAll('[data-close-scanner]').forEach(function (button) {
        button.addEventListener('click', closeModal);
    });
    document.addEventListener('keydown', function (event) {
        if (event.key === 'Escape') closeModal();
    });
    cameraStarter && cameraStarter.addEventListener('click', function () { cameraInput.click(); });
    addScanPage && addScanPage.addEventListener('click', function () { cameraInput.click(); });

    cameraInput && cameraInput.addEventListener('change', async function () {
        const available = 30 - pages.length;
        const selected = Array.from(cameraInput.files || []).filter(function (file) {
            return file.type.indexOf('image/') === 0;
        }).slice(0, available);
        if (!selected.length) {
            showToast(available <= 0 ? 'Maksimal 30 halaman per dokumen.' : 'Pilih foto berformat gambar.', true);
            return;
        }

        for (const file of selected) {
            pages.push({ id: Date.now().toString(36) + Math.random().toString(36).slice(2), file: file, preview: await readDataUrl(file) });
        }
        cameraInput.value = '';
        renderPages();
    });

    function readDataUrl(file) {
        return new Promise(function (resolve, reject) {
            const reader = new FileReader();
            reader.onload = function () { resolve(String(reader.result)); };
            reader.onerror = function () { reject(new Error('Foto tidak dapat dibaca.')); };
            reader.readAsDataURL(file);
        });
    }

    function renderPages() {
        if (!scanList) return;
        scanList.innerHTML = '';
        pages.forEach(function (page, index) {
            const item = document.createElement('div');
            item.className = 'scan-page';
            const image = document.createElement('img');
            image.src = page.preview;
            image.alt = 'Halaman ' + (index + 1);
            const number = document.createElement('span');
            number.className = 'scan-page-index';
            number.textContent = String(index + 1);
            const remove = document.createElement('button');
            remove.type = 'button';
            remove.className = 'scan-remove';
            remove.setAttribute('aria-label', 'Hapus halaman ' + (index + 1));
            remove.textContent = '×';
            remove.addEventListener('click', function () {
                if (busy) return;
                pages.splice(index, 1);
                renderPages();
            });
            item.append(image, number, remove);
            scanList.appendChild(item);
        });
        cameraStarter.hidden = pages.length > 0;
        scanListWrap.hidden = pages.length === 0;
        scanCount.textContent = String(pages.length);
        saveScan.disabled = pages.length === 0 || busy;
    }

    saveScan && saveScan.addEventListener('click', async function () {
        if (!pages.length || busy) return;
        if (!window.PDFLib) {
            showToast('Komponen PDF tidak ditemukan.', true);
            return;
        }
        const name = (scanName.value || '').trim();
        if (!name) {
            showToast('Isi nama dokumen terlebih dahulu.', true);
            scanName.focus();
            return;
        }

        busy = true;
        saveScan.disabled = true;
        scanProgress.hidden = false;
        scanProgressTitle.textContent = 'Menyusun halaman menjadi PDF';

        try {
            const pdfFile = await makePdf(name);
            if (pdfFile.size > 100 * 1024 * 1024) throw new Error('Hasil PDF melebihi batas 100 MB. Kurangi jumlah halaman.');
            scanProgressTitle.textContent = 'Mengunggah PDF ke Google Drive';

            const form = new FormData();
            form.append('file', pdfFile);
            const prodiSelect = document.getElementById('uploadProdi');
            form.append('prodi_key', prodiSelect ? prodiSelect.value : (config.prodiKey || ''));
            const response = await fetch(config.uploadUrl, {
                method: 'POST',
                headers: {
                    'Accept': 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                    'X-CSRF-TOKEN': config.csrf
                },
                body: form
            });
            const result = await response.json().catch(function () { return {}; });
            if (!response.ok) {
                const validation = result.errors ? Object.values(result.errors).flat()[0] : null;
                throw new Error(validation || result.message || 'Hasil pindai gagal diunggah.');
            }
            showToast('Hasil pindai berhasil disimpan ke Google Drive.');
            window.setTimeout(function () { window.location.reload(); }, 700);
        } catch (error) {
            showToast(error.message || 'Hasil pindai gagal diproses.', true);
            busy = false;
            saveScan.disabled = false;
            scanProgress.hidden = true;
        }
    });

    async function makePdf(name) {
        const documentPdf = await window.PDFLib.PDFDocument.create();
        for (const page of pages) {
            const jpeg = await imageToJpeg(page.file);
            const embedded = await documentPdf.embedJpg(await jpeg.arrayBuffer());
            const portrait = embedded.height >= embedded.width;
            const pageWidth = portrait ? 595.28 : 841.89;
            const pageHeight = portrait ? 841.89 : 595.28;
            const pdfPage = documentPdf.addPage([pageWidth, pageHeight]);
            const margin = 24;
            const scale = Math.min((pageWidth - margin * 2) / embedded.width, (pageHeight - margin * 2) / embedded.height);
            const width = embedded.width * scale;
            const height = embedded.height * scale;
            pdfPage.drawImage(embedded, { x: (pageWidth - width) / 2, y: (pageHeight - height) / 2, width: width, height: height });
        }
        const bytes = await documentPdf.save({ useObjectStreams: true });
        const cleaned = name.replace(/[\\/:*?"<>|\u0000-\u001f]/g, '-').slice(0, 180).trim() || 'Hasil Pindai';
        const fileName = /\.pdf$/i.test(cleaned) ? cleaned : cleaned + '.pdf';
        return new File([bytes], fileName, { type: 'application/pdf' });
    }

    async function imageToJpeg(file) {
        let source;
        try {
            source = await createImageBitmap(file, { imageOrientation: 'from-image' });
        } catch (error) {
            source = await loadImage(file);
        }
        const maxSide = 2200;
        const sourceWidth = source.width || source.naturalWidth;
        const sourceHeight = source.height || source.naturalHeight;
        const ratio = Math.min(1, maxSide / Math.max(sourceWidth, sourceHeight));
        const width = Math.max(1, Math.round(sourceWidth * ratio));
        const height = Math.max(1, Math.round(sourceHeight * ratio));
        const canvas = document.createElement('canvas');
        canvas.width = width;
        canvas.height = height;
        const context = canvas.getContext('2d');
        context.fillStyle = '#ffffff';
        context.fillRect(0, 0, width, height);
        context.drawImage(source, 0, 0, width, height);
        if (source.close) source.close();
        return new Promise(function (resolve, reject) {
            canvas.toBlob(function (blob) { blob ? resolve(blob) : reject(new Error('Foto tidak dapat dikonversi.')); }, 'image/jpeg', .88);
        });
    }

    function loadImage(file) {
        return new Promise(function (resolve, reject) {
            const url = URL.createObjectURL(file);
            const image = new Image();
            image.onload = function () { URL.revokeObjectURL(url); resolve(image); };
            image.onerror = function () { URL.revokeObjectURL(url); reject(new Error('Format foto tidak didukung.')); };
            image.src = url;
        });
    }
})();
