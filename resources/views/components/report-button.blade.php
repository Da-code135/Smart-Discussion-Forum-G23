@props(['type', 'id'])


    

    <div x-show="showReportModal" class="modal" x-cloak>
        <div class="modal-content">
            <span @click="showReportModal = false" class="close">&times;</span>
            <h3>Report Content</h3>
            <form action="{{ route('report.store') }}" method="POST">
                @csrf
                <input type="hidden" name="type" value="{{ $type }}">
                <input type="hidden" name="id" value="{{ $id }}">
                <div class="form-group" style="margin-bottom: 1rem;">
                    <textarea
                        name="reason"
                        rows="4"
                        required
                        maxlength="1000"
                        placeholder="Please explain why you're reporting this content..."
                        class="form-input @error('reason') is-invalid @enderror"
                        style="width: 100%; padding: 0.75rem; border: 1px solid #d1d5db; border-radius: 0.375rem; resize: vertical; font-family: inherit;"
                    >{{ old('reason') }}</textarea>
                    @error('reason')
                        <p class="form-error" style="color: var(--error); font-size: 0.875rem; margin-top: 0.25rem;">{{ $message }}</p>
                    @enderror
                </div>
                <button type="submit" class="btn btn-primary">Submit Report</button>
            </form>
        </div>
    </div>
