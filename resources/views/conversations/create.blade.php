@extends('layouts.app')

@section('title', 'New Conversation')
@section('activeNav', 'conversations')

@section('content')
<div class="page-stack">
    <div class="page-header">
        <h1>New Conversation</h1>
        <p>Start a direct message or a group chat with your group members.</p>
    </div>

    <div class="card" style="max-width: 640px;">
        <div class="card-body">
            <form action="{{ route('conversations.store') }}" method="POST" class="form-stack">
                @csrf

                {{-- Conversation Type --}}
                <div class="form-group">
                    <label class="form-label">Conversation type *</label>
                    <div style="display: flex; gap: 16px; margin-top: 4px;">
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="type" value="direct"
                                {{ old('type', 'direct') === 'direct' ? 'checked' : '' }}
                                onchange="toggleType(this.value)">
                            <span>Direct (1-to-1)</span>
                        </label>
                        <label style="display: flex; align-items: center; gap: 6px; cursor: pointer;">
                            <input type="radio" name="type" value="group"
                                {{ old('type') === 'group' ? 'checked' : '' }}
                                onchange="toggleType(this.value)">
                            <span>Group (3+)</span>
                        </label>
                    </div>
                    @error('type')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Group Name (shown only for group type) --}}
                <div class="form-group" id="group-name-field" style="{{ old('type') === 'group' ? '' : 'display: none;' }}">
                    <label for="name" class="form-label">Group name *</label>
                    <input type="text"
                           id="name"
                           name="name"
                           class="form-input @error('name') is-invalid @enderror"
                           placeholder="E.g., Project Alpha Team"
                           value="{{ old('name') }}">
                    @error('name')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- User Picker --}}
                <div class="form-group">
                    <label for="participant_ids" class="form-label">Select members *</label>
                    <p class="form-hint">Choose one person for a direct conversation, or multiple for a group.</p>
                    <div style="display: flex; flex-direction: column; gap: 4px; max-height: 300px; overflow-y: auto; border: 1px solid var(--border); border-radius: 8px; padding: 8px;">
                        @forelse ($users as $user)
                            <label style="display: flex; align-items: center; gap: 8px; padding: 6px 8px; cursor: pointer; border-radius: 6px; transition: background 0.15s;"
                                   onmouseenter="this.style.background='var(--surface-hover)'"
                                   onmouseleave="this.style.background='transparent'">
                                <input type="checkbox"
                                       name="participant_ids[]"
                                       value="{{ $user->id }}"
                                       {{ in_array($user->id, old('participant_ids', [])) ? 'checked' : '' }}>
                                <span>{{ $user->full_name }}</span>
                            </label>
                        @empty
                            <p style="padding: 12px; text-align: center; color: var(--text-muted);">
                                {{ Auth::user()->isSystemAdmin() ? 'No other users available on the platform.' : 'No other members available in your group.' }}
                            </p>
                        @endforelse
                    </div>
                    @error('participant_ids')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                    @error('participant_ids.*')
                        <p class="form-error">{{ $message }}</p>
                    @enderror
                </div>

                {{-- Submit --}}
                <div class="form-actions-row">
                    <a href="{{ route('conversations.index') }}" class="btn btn-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary">Start conversation</button>
                </div>
            </form>
        </div>
    </div>
</div>

@push('scripts')
<script>
function toggleType(value) {
    const groupNameField = document.getElementById('group-name-field');
    if (groupNameField) {
        groupNameField.style.display = value === 'group' ? '' : 'none';
    }

    // When switching to 'direct', uncheck all but the first checked box
    const checkboxes = document.querySelectorAll('input[name="participant_ids[]"]');
    if (value === 'direct') {
        const checked = Array.from(checkboxes).filter(cb => cb.checked);
        if (checked.length > 1) {
            // Keep only the first checked, uncheck the rest
            for (let i = 1; i < checked.length; i++) {
                checked[i].checked = false;
            }
        }
    }
}

// Limit checkboxes to 1 when type is direct
document.addEventListener('DOMContentLoaded', function () {
    const checkboxes = document.querySelectorAll('input[name="participant_ids[]"]');
    const typeRadios = document.querySelectorAll('input[name="type"]');
    const selectedType = document.querySelector('input[name="type"]:checked');

    function enforceLimit() {
        const isDirect = document.querySelector('input[name="type"]:checked')?.value === 'direct';
        if (!isDirect) return;

        const checked = Array.from(checkboxes).filter(cb => cb.checked);
        if (checked.length > 1) {
            // Uncheck the last checked one
            checked[checked.length - 1].checked = false;
            alert('Direct conversations can only have one participant. Please uncheck additional selections or switch to Group.');
        }
    }

    checkboxes.forEach(cb => {
        cb.addEventListener('change', function (e) {
            const isDirect = document.querySelector('input[name="type"]:checked')?.value === 'direct';
            if (isDirect && this.checked) {
                // Uncheck all others
                checkboxes.forEach(other => {
                    if (other !== this) other.checked = false;
                });
            }
        });
    });
});
</script>
@endpush
@endsection
