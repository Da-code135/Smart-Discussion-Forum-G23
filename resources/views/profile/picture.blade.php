@extends('layouts.app')

@section('title', 'Profile Picture')
@section('activeNav', 'profile')

@section('content')
@php
    $user = Auth::user();
    $initials = collect(explode(' ', $user->full_name))->map(fn ($w) => strtoupper(substr($w, 0, 1)))->take(2)->join('');
@endphp

<div class="picture-page">
    <nav class="mb-4">
        <a href="{{ route('profile.edit') }}" class="back-link">
            <span class="material-symbols-outlined" style="font-size: 1.125rem;">arrow_back</span>
            Back to Profile
        </a>
    </nav>

    <div class="bento-card picture-card">
        <div style="display: flex; flex-direction: column; align-items: center; text-align: center; gap: 2rem;">
            <header>
                <h1 style="font-size: 2rem; margin-bottom: 0.5rem;">Profile Picture</h1>
                <p class="text-muted" style="margin: 0;">Personalize your academic presence within the forum.</p>
            </header>

            <div class="picture-preview-wrap">
                @if ($user->profile_picture)
                    <img
                        id="preview-image"
                        src="{{ asset('storage/' . $user->profile_picture) }}"
                        alt="Profile picture of {{ $user->full_name }}"
                        class="picture-preview-large"
                    >
                @else
                    <div id="preview-placeholder" class="picture-preview-placeholder">{{ $initials }}</div>
                    <img id="preview-image" src="" alt="" class="picture-preview-large" style="display: none;">
                @endif
                <div class="profile-avatar-overlay" style="border-radius: 50%;">
                    <span class="material-symbols-outlined" style="color: white; font-size: 2.5rem;">photo_camera</span>
                </div>
            </div>

            <form
                method="POST"
                action="{{ route('profile.picture.upload') }}"
                enctype="multipart/form-data"
                class="picture-upload-form"
                id="upload-form"
            >
                @csrf

                <div style="display: flex; flex-direction: column; gap: 1rem; margin-bottom: 2rem;">
                    <label for="file-upload" class="file-upload-zone" id="upload-zone">
                        <span class="material-symbols-outlined upload-icon">cloud_upload</span>
                        <span class="upload-title" style="font-family: 'Manrope', sans-serif; font-size: 1.25rem; font-weight: 600; display: block;">Choose a new file</span>
                        <span class="upload-subtitle text-muted" style="font-size: 0.875rem;">or drag and drop here</span>
                        <input
                            type="file"
                            name="profile_picture"
                            id="file-upload"
                            accept="image/jpeg,image/png"
                            class="sr-only"
                            required
                        >
                    </label>

                    @error('profile_picture')
                        <span class="form-error">{{ $message }}</span>
                    @enderror

                    <div class="file-upload-info">
                        <span class="material-symbols-outlined">info</span>
                        <p style="font-size: 0.875rem; color: var(--on-surface-variant); margin: 0; line-height: 1.5;">
                            <strong style="color: var(--on-surface);">Supported formats:</strong> JPEG, PNG.<br>
                            <strong style="color: var(--on-surface);">Maximum size:</strong> 2MB. Ensure your face is centered and clearly visible for optimal recognition.
                        </p>
                    </div>
                </div>

                <div class="picture-actions">
                    <button type="submit" class="btn btn-primary btn-pill btn-lg" style="flex: 1;">
                        <span class="material-symbols-outlined">upload</span>
                        Upload Picture
                    </button>
                    <a href="{{ route('profile.edit') }}" class="btn btn-tertiary btn-pill btn-lg" style="flex: 1;">
                        Cancel
                    </a>
                </div>
            </form>
        </div>
    </div>

    <footer style="margin-top: 2rem; text-align: center;">
        <p class="text-muted" style="font-size: 0.875rem; margin: 0;">
            &copy; {{ date('Y') }} {{ config('app.name') }}. Committed to academic integrity and data privacy.
        </p>
    </footer>
</div>
@endsection

@push('scripts')
<script>
    const fileInput = document.getElementById('file-upload');
    const uploadZone = document.getElementById('upload-zone');
    const previewImage = document.getElementById('preview-image');
    const previewPlaceholder = document.getElementById('preview-placeholder');
    const uploadTitle = uploadZone.querySelector('.upload-title');
    const uploadSubtitle = uploadZone.querySelector('.upload-subtitle');

    fileInput.addEventListener('change', function (e) {
        if (e.target.files.length > 0) {
            const file = e.target.files[0];
            uploadTitle.textContent = file.name;
            uploadSubtitle.textContent = 'File selected — click upload to confirm';
            uploadZone.classList.add('is-selected');

            const reader = new FileReader();
            reader.onload = function (ev) {
                previewImage.src = ev.target.result;
                previewImage.style.display = 'block';
                if (previewPlaceholder) previewPlaceholder.style.display = 'none';
            };
            reader.readAsDataURL(file);
        }
    });

    ['dragenter', 'dragover'].forEach(function (eventName) {
        uploadZone.addEventListener(eventName, function (e) {
            e.preventDefault();
            uploadZone.classList.add('is-dragover');
        });
    });

    ['dragleave', 'drop'].forEach(function (eventName) {
        uploadZone.addEventListener(eventName, function (e) {
            e.preventDefault();
            uploadZone.classList.remove('is-dragover');
        });
    });

    uploadZone.addEventListener('drop', function (e) {
        if (e.dataTransfer.files.length > 0) {
            fileInput.files = e.dataTransfer.files;
            fileInput.dispatchEvent(new Event('change'));
        }
    });
</script>
@endpush
