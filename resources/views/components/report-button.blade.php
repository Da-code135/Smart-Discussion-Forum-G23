@props(['type', 'id'])

@php
    $modalId = 'report-modal-' . $type . '-' . $id;
@endphp

<button type="button" class="btn btn-secondary" onclick="document.getElementById('{{ $modalId }}').showModal()">
    <span class="material-symbols-outlined">flag</span>
    Report
</button>

<dialog id="{{ $modalId }}" class="modal-content" style="border: 0; max-width: 520px; width: calc(100% - 24px);">
    <div class="page-stack">
        <div class="section-header">
            <div>
                <h2>Report content</h2>
                <p>Explain why this content should be reviewed.</p>
            </div>
            <button type="button" class="btn btn-ghost btn-sm" onclick="document.getElementById('{{ $modalId }}').close()">Close</button>
        </div>

        <form action="{{ route('report.store') }}" method="POST" class="form-stack">
            @csrf
            <input type="hidden" name="type" value="{{ $type }}">
            <input type="hidden" name="id" value="{{ $id }}">
            <div class="form-group">
                <label for="reason-{{ $type }}-{{ $id }}" class="form-label">Reason</label>
                <textarea
                    id="reason-{{ $type }}-{{ $id }}"
                    name="reason"
                    rows="4"
                    required
                    maxlength="1000"
                    placeholder="Please explain why you're reporting this content..."
                    class="form-input @error('reason') is-invalid @enderror"
                >{{ old('reason') }}</textarea>
                @error('reason')
                    <p class="form-error">{{ $message }}</p>
                @enderror
            </div>
            <div class="form-actions-row" style="justify-content: flex-end;">
                <button type="button" class="btn btn-ghost" onclick="document.getElementById('{{ $modalId }}').close()">Cancel</button>
                <button type="submit" class="btn btn-primary">Submit report</button>
            </div>
        </form>
    </div>
</dialog>
