@extends('layouts.guest')

@section('title', 'Platform Rules')
@section('main-class', 'main-content--top')

@section('content')
<div class="onboarding-page">
    {{-- Stepper --}}
    <div class="onboarding-stepper">
        <div class="onboarding-progress" role="progressbar" aria-valuenow="66" aria-valuemin="0" aria-valuemax="100" aria-label="Registration progress">
            <div class="onboarding-progress-bar" style="width: 66.6%;"></div>
        </div>
        <p class="onboarding-step-label">Step 2 of 3 — Platform Rules</p>
    </div>

    {{-- Rules Card --}}
    <section class="onboarding-card">
        <div class="onboarding-card-header">
            <h2>Platform Rules &amp; Guidelines</h2>
            <p>Please read carefully before joining</p>
        </div>

        <hr class="onboarding-divider">

        <div class="rules-scroll" tabindex="0" aria-label="Platform rules content">
            <div class="rule-item">
                <h3>1. Respect all members</h3>
                <p>
                    Treat all members with respect. We do not tolerate harassment, bullying, or discrimination
                    of any kind. Disagreements are welcome, but must be conducted professionally and courteously.
                </p>
            </div>

            <div class="rule-item">
                <h3>2. No irrelevant content</h3>
                <p>
                    Do not post content that is vulgar, offensive, or inappropriate. Keep discussions focused on
                    forum topics. Off-topic content should be posted in designated areas only.
                </p>
            </div>

            <div class="rule-item">
                <h3>3. Stay active on the platform</h3>
                <p>
                    Members are expected to participate regularly in discussions. Prolonged inactivity may result
                    in warnings or account restrictions to maintain an engaged academic community.
                </p>
            </div>

            <div class="rule-item">
                <h3>4. Academic integrity</h3>
                <p>
                    All work and discussions must be original or properly attributed. Plagiarism, cheating,
                    or other violations of academic integrity are strictly prohibited and will result in disciplinary action.
                </p>
            </div>

            <div class="rule-item">
                <h3>5. Privacy &amp; security</h3>
                <p>
                    Do not share personal information of other members without consent. Do not attempt to hack,
                    bypass security measures, or compromise the security of the platform.
                </p>
            </div>

            <div class="rule-item">
                <h3>6. Moderation rights</h3>
                <p>
                    Moderators and administrators reserve the right to edit, delete, or hide any content that
                    violates these rules. Repeated violations may result in temporary or permanent suspension.
                </p>
            </div>

            <p class="rules-scroll-hint">Agreement Version: {{ config('app.agreement_version', '1.0') }}</p>
        </div>

        <div class="onboarding-actions">
            <div class="form-check">
                <input type="checkbox" id="agreement-checkbox" aria-describedby="agreement-label">
                <label for="agreement-checkbox" id="agreement-label">I have read and agree to the platform rules</label>
            </div>

            <div class="onboarding-buttons">
                <form method="POST" action="{{ route('onboarding.decline') }}" class="w-100" style="flex: 1;">
                    @csrf
                    <button type="submit" class="btn btn-secondary btn-block">Decline</button>
                </form>

                <form method="POST" action="{{ route('onboarding.agree') }}" id="agree-form" class="w-100" style="flex: 1;">
                    @csrf
                    <button type="submit" id="agree-btn" class="btn btn-primary btn-block" disabled>
                        Agree &amp; Register
                    </button>
                </form>
            </div>
        </div>
    </section>

    @if (session('info'))
        <div class="alert alert-error onboarding-decline-toast" role="alert">
            You have declined the rules. Registration has been cancelled.
        </div>
    @endif
</div>
@endsection

@push('scripts')
<script>
    const checkbox = document.getElementById('agreement-checkbox');
    const agreeBtn = document.getElementById('agree-btn');
    const agreeForm = document.getElementById('agree-form');

    checkbox.addEventListener('change', function () {
        agreeBtn.disabled = !this.checked;
    });

    agreeForm.addEventListener('submit', function (e) {
        if (!checkbox.checked) {
            e.preventDefault();
        }
    });
</script>
@endpush
