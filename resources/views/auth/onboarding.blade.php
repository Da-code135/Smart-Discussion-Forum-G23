@extends('layouts.guest')

@section('title', 'Platform Rules')
@section('main-class', 'guest-main--wide')

@section('content')
<div class="onboarding-page">
    <div class="onboarding-stepper">
        <div class="onboarding-progress" role="progressbar" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100" aria-label="Registration progress">
            <div class="onboarding-progress-bar" style="width: 66.6%;"></div>
        </div>
        <p class="onboarding-step-label">Step 2 of 3 — Platform Rules</p>
    </div>

    <section class="onboarding-card">
        <div class="card-header-custom">
            <h1>Platform rules and guidelines</h1>
            <p>Please read carefully before joining Studdit.</p>
        </div>

        <div class="rules-scroll" tabindex="0" aria-label="Platform rules content">
            <div class="rule-item">
                <h2>1. Respect all members</h2>
                <p>Treat all members with respect. Harassment, bullying, or discrimination of any kind is not tolerated. Disagreement is welcome when it stays professional and courteous.</p>
            </div>

            <div class="rule-item">
                <h2>2. Keep content relevant</h2>
                <p>Do not post vulgar, offensive, or inappropriate content. Keep discussions focused on the subject matter of your group.</p>
            </div>

            <div class="rule-item">
                <h2>3. Stay active on the platform</h2>
                <p>Members are expected to participate regularly. Prolonged inactivity may result in warnings or account restrictions to keep discussions active.</p>
            </div>

            <div class="rule-item">
                <h2>4. Academic integrity</h2>
                <p>All work and discussion must be original or properly attributed. Plagiarism, cheating, or related violations are strictly prohibited.</p>
            </div>

            <div class="rule-item">
                <h2>5. Privacy and security</h2>
                <p>Do not share personal information of others without consent. Do not attempt to bypass security or compromise the platform.</p>
            </div>

            <div class="rule-item">
                <h2>6. Moderation rights</h2>
                <p>Moderators and administrators may edit, remove, or hide content that violates these rules. Repeated violations may result in suspension or blacklisting.</p>
            </div>

            <p class="rules-scroll-hint">Agreement version: {{ config('app.agreement_version', '1.0') }}</p>
        </div>

        {{-- Group Selection --}}
        <div class="onboarding-group-selection">
            <label for="group-select" class="group-select-label">
                <span class="material-symbols-outlined">group</span>
                Select your student group <span class="required">*</span>
            </label>
            <select id="group-select" name="group_id" form="agree-form" class="group-select-dropdown" required>
                <option value="" disabled selected>— Choose your group —</option>
                @foreach ($groups as $group)
                    <option value="{{ $group->id }}">{{ $group->group_name }}</option>
                @endforeach
            </select>
            @if ($groups->isEmpty())
                <p class="group-select-error">No student groups available. Please contact an administrator.</p>
            @endif
        </div>

        <div class="onboarding-actions">
            <div class="form-check">
                <input type="checkbox" id="agreement-checkbox" aria-describedby="agreement-label">
                <label for="agreement-checkbox" id="agreement-label">I have read and agree to the platform rules.</label>
            </div>

            <div class="onboarding-buttons">
                <form method="POST" action="{{ route('onboarding.decline') }}" style="flex: 1;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-block">Decline</button>
                </form>

                <form method="POST" action="{{ route('onboarding.agree') }}" id="agree-form" style="flex: 1;">
                    @csrf
                    <button type="submit" id="agree-btn" class="btn btn-primary btn-block" disabled>Agree and register</button>
                </form>
            </div>
        </div>
    </section>

    @if (session('info'))
        <div class="alert alert-info" role="alert">{{ session('info') }}</div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    const checkbox = document.getElementById('agreement-checkbox');
    const agreeBtn = document.getElementById('agree-btn');
    const agreeForm = document.getElementById('agree-form');
    const groupSelect = document.getElementById('group-select');

    function updateAgreeButton() {
        // Button is only enabled if BOTH conditions are met:
        // 1. Checkbox is checked
        // 2. A group has been selected from the dropdown
        agreeBtn.disabled = !(checkbox.checked && groupSelect && groupSelect.value !== '');
    }

    checkbox.addEventListener('change', updateAgreeButton);

    if (groupSelect) {
        groupSelect.addEventListener('change', updateAgreeButton);
    }

    agreeForm.addEventListener('submit', function (e) {
        if (!checkbox.checked || !groupSelect || groupSelect.value === '') {
            e.preventDefault();
            return;
        }

        // Double-check that group_id is set
        if (!document.querySelector('input[name="group_id"]') && !document.querySelector('select[name="group_id"]')) {
            e.preventDefault();
        }
    });
</script>
@endpush
