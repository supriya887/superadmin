@extends('superadmin.layouts.crm')

@section('content')

<div class="page-header">
    <div class="">
        <div class="breadcrumb welcome-text">
            <p>
                <a href="{{ route('crm.dashboard') }}">Dashboard</a> <span>/</span><a href="{{ route('crm.leads') }}">Leads</a>
            </p>
        </div>
        <h2>Upload Lead</h2>
    </div>
    <div class="crm-actions">
        <a href="{{ route('crm.leads.uploads.sample') }}" class="crm-action-btn active">
            <i data-lucide="download"></i>
            <span>Download Sample CSV</span>
        </a>
        <a href="{{ route('crm.leads') }}" class="back-btn">
            <svg xmlns="http://www.w3.org/2000/svg" width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                <path d="M19 12H5" />
                <path d="M12 19l-7-7 7-7" />
            </svg>
            <span>Back</span>
        </a>
    </div>
</div>

@if(session('success'))
    <div class="alert alert-success">{{ session('success') }}</div>
@endif

@if(session('error'))
    <div class="alert alert-danger">{{ session('error') }}</div>
@endif

@if($errors->any())
    <div class="alert alert-danger">
        {{ $errors->first() }}
    </div>
@endif

@if(session('import_errors') && count(session('import_errors')) > 0)
    <div class="import-error-report" style="margin-bottom: 24px; background: #fff; border: 1px solid #fed7aa; border-radius: 10px; overflow: hidden; box-shadow: 0 4px 12px rgba(0,0,0,0.04);">
        <div style="padding: 16px 20px; background: #fff7ed; border-bottom: 1px solid #ffedd5; display: flex; align-items: center; justify-content: space-between; flex-wrap: wrap; gap: 10px;">
            <div style="display: flex; align-items: center; gap: 10px;">
                <div style="width: 32px; height: 32px; border-radius: 50%; background: #fdba74; display: flex; align-items: center; justify-content: center; color: #9a3412;">
                    <i data-lucide="alert-triangle" style="width: 18px; height: 18px;"></i>
                </div>
                <div>
                    <h4 style="margin: 0; font-size: 16px; font-weight: 600; color: #9a3412;">
                        Skipped Records ({{ count(session('import_errors')) }})
                    </h4>
                    <span style="font-size: 13px; color: #c2410c;">The following rows were skipped during Excel import. Review the reasons below to fix and re-upload.</span>
                </div>
            </div>
            @if(session('success_count') !== null)
                <div style="display: flex; gap: 8px;">
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #dcfce7; color: #15803d; border-radius: 6px; font-size: 13px; font-weight: 600;">
                        ✓ {{ session('success_count') }} Imported
                    </span>
                    <span style="display: inline-flex; align-items: center; gap: 4px; padding: 4px 10px; background: #fee2e2; color: #b91c1c; border-radius: 6px; font-size: 13px; font-weight: 600;">
                        ✕ {{ count(session('import_errors')) }} Skipped
                    </span>
                </div>
            @endif
        </div>
        <div style="max-height: 380px; overflow-y: auto; padding: 0;">
            <table style="width: 100%; border-collapse: collapse; font-size: 13px; text-align: left;">
                <thead>
                    <tr style="background: #f8fafc; border-bottom: 1px solid #e2e8f0; color: #64748b; font-weight: 600;">
                        <th style="padding: 10px 16px; width: 85px;">Row #</th>
                        <th style="padding: 10px 16px; width: 220px;">Lead / Company</th>
                        <th style="padding: 10px 16px;">Reason for Skipping</th>
                    </tr>
                </thead>
                <tbody>
                    @foreach(session('import_errors') as $err)
                        @php
                            $rowNum = is_array($err) ? ($err['row'] ?? '—') : '—';
                            $leadName = is_array($err) ? ($err['lead'] ?? '—') : '—';
                            $reason = is_array($err) ? ($err['reason'] ?? $err) : $err;
                        @endphp
                        <tr style="border-bottom: 1px solid #f1f5f9;">
                            <td style="padding: 10px 16px; font-weight: bold; color: #475569; white-space: nowrap;">
                                Row {{ $rowNum }}
                            </td>
                            <td style="padding: 10px 16px; color: #1e293b; font-weight: 500;">
                                {{ $leadName }}
                            </td>
                            <td style="padding: 10px 16px; color: #b91c1c;">
                                {{ $reason }}
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@endif

<section class="upload-lead-page">
    <div class="upload-lead-card">
        <h3>UPLOAD CUSTOMER LEADS</h3>

        <form method="POST" action="{{ route('crm.leads.upload') }}" enctype="multipart/form-data" id="uploadLeadForm">
            @csrf

            <label class="upload-lead-box" id="leadUploadBox">
                <div class="upload-lead-icon">
                    <i data-lucide="cloud-upload"></i>
                </div>
                <div class="upload-lead-text">
                    Click to upload or drag file here
                </div>
                <div class="upload-lead-format">
                    (.csv, .xls or .xlsx)
                </div>
                <div style="margin-top:10px; font-size:13px; color:#4B5563;">
                    Use the sample CSV so columns match Name, Email, Mobile, Company Name, and Lead Status.
                </div>
                <input
                    type="file"
                    id="leadFile"
                    name="file"
                    class="lead-file-input"
                    accept=".csv,.xls,.xlsx,.txt"
                    required>
            </label>

            <div class="upload-file-section" id="uploadingSection" hidden>
                <div class="upload-file-title">
                    Uploading 1/1 files
                </div>
                <div class="upload-file-row">
                    <span id="uploadingFileName"></span>
                    <button type="button" class="upload-remove-btn" id="removeUploadBtn" aria-label="Remove">
                        <i data-lucide="x"></i>
                    </button>
                </div>
                <div class="upload-progress">
                    <span></span>
                </div>
            </div>

            <div class="upload-file-section uploaded-section" id="uploadedSection" hidden>
                <div class="upload-file-title">
                    Uploaded
                </div>
                <div class="upload-file-row uploaded-row">
                    <span id="uploadedFileName"></span>
                    <button type="button" class="upload-delete-btn" id="deleteUploadBtn" aria-label="Delete">
                        <i data-lucide="trash-2"></i>
                    </button>
                </div>
            </div>

            <div class="upload-submit-wrap">
                <button type="submit" class="submit-btn">
                    Submit
                </button>
            </div>
        </form>
    </div>
</section>

<script>
document.addEventListener('DOMContentLoaded', function () {
    const fileInput = document.getElementById('leadFile');
    const uploadingSection = document.getElementById('uploadingSection');
    const uploadedSection = document.getElementById('uploadedSection');
    const uploadingFileName = document.getElementById('uploadingFileName');
    const uploadedFileName = document.getElementById('uploadedFileName');
    const removeUploadBtn = document.getElementById('removeUploadBtn');
    const deleteUploadBtn = document.getElementById('deleteUploadBtn');

    function clearFile() {
        fileInput.value = '';
        uploadingFileName.textContent = '';
        uploadedFileName.textContent = '';
        uploadingSection.hidden = true;
        uploadedSection.hidden = true;
    }

    function showSelectedFile(name) {
        uploadingSection.hidden = true;
        uploadedSection.hidden = false;
        uploadingFileName.textContent = name;
        uploadedFileName.textContent = name;

        if (window.lucide) {
            lucide.createIcons();
        }
    }

    function setFile(file) {
        if (!file) {
            clearFile();
            return;
        }
        const ext = (file.name.split('.').pop() || '').toLowerCase();
        if (['csv', 'xls', 'xlsx', 'txt'].indexOf(ext) === -1) {
            if (typeof Swal !== 'undefined') {
                Swal.fire({ icon: 'warning', title: 'Use a CSV, XLS or XLSX file.' });
            } else {
                alert('Use a CSV, XLS or XLSX file.');
            }
            return;
        }
        const dataTransfer = new DataTransfer();
        dataTransfer.items.add(file);
        fileInput.files = dataTransfer.files;
        showSelectedFile(file.name);
    }

    fileInput.addEventListener('click', function (e) {
        e.stopPropagation();
    });

    fileInput.addEventListener('change', function () {
        if (!this.files.length) {
            clearFile();
            return;
        }
        setFile(this.files[0]);
    });

    const uploadBox = document.getElementById('leadUploadBox');
    if (uploadBox) {
        var dragCount = 0;
        uploadBox.addEventListener('dragenter', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragCount++;
            uploadBox.classList.add('is-dragover');
        });
        uploadBox.addEventListener('dragover', function (e) {
            e.preventDefault();
            e.stopPropagation();
            if (e.dataTransfer) {
                e.dataTransfer.dropEffect = 'copy';
            }
        });
        uploadBox.addEventListener('dragleave', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragCount--;
            if (dragCount <= 0) {
                dragCount = 0;
                uploadBox.classList.remove('is-dragover');
            }
        });
        uploadBox.addEventListener('drop', function (e) {
            e.preventDefault();
            e.stopPropagation();
            dragCount = 0;
            uploadBox.classList.remove('is-dragover');
            const files = e.dataTransfer ? e.dataTransfer.files : [];
            if (files.length) {
                setFile(files[0]);
            }
        });
    }

    removeUploadBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearFile();
    });

    deleteUploadBtn.addEventListener('click', function (e) {
        e.preventDefault();
        e.stopPropagation();
        clearFile();
    });
});
</script>

@endsection
