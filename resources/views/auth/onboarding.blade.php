


<div class="container">
    <div class="row justify-content-center">
        <div class="col-md-8">
            <div class="card">
                <div class="card-header">{{ __('Platform Rules & Terms') }}</div>

                <div class="card-body">
                    <div class="alert alert-info">
                        <strong>{{ __('Please read and agree to our platform rules before continuing') }}</strong>
                    </div>

                    <div class="platform-rules mb-4">
                        <h5>{{ __('Smart Discussion Forum - Community Guidelines') }}</h5>
                        
                        <h6 class="mt-3">1. {{ __('Respectful Communication') }}</h6>
                        <p>
                            Treat all members with respect. We do not tolerate harassment, bullying, or discrimination 
                            of any kind. Disagreements are welcome, but must be conducted professionally and courteously.
                        </p>

                        <h6>2. {{ __('Academic Integrity') }}</h6>
                        <p>
                            All work and discussions must be original or properly attributed. Plagiarism, cheating, 
                            or other violations of academic integrity are strictly prohibited and will result in disciplinary action.
                        </p>

                        <h6>3. {{ __('Appropriate Content') }}</h6>
                        <p>
                            Do not post content that is vulgar, offensive, or inappropriate. Keep discussions focused on 
                            the forum topics. Off-topic content should be posted in designated areas only.
                        </p>

                        <h6>4. {{ __('No Spam or Advertising') }}</h6>
                        <p>
                            Do not post unsolicited advertising, promotional content, or spam. Repetitive or irrelevant 
                            posts will be removed, and repeated violations may result in account suspension.
                        </p>

                        <h6>5. {{ __('Privacy & Security') }}</h6>
                        <p>
                            Do not share personal information of other members without consent. Do not attempt to hack, 
                            bypass security measures, or compromise the security of the platform.
                        </p>

                        <h6>6. {{ __('Compliance with Laws') }}</h6>
                        <p>
                            All members must comply with applicable laws and regulations. The forum is not responsible 
                            for user-generated content that violates local, state, or international laws.
                        </p>

                        <h6>7. {{ __('Moderation Rights') }}</h6>
                        <p>
                            Moderators and administrators reserve the right to edit, delete, or hide any content that 
                            violates these rules. Repeated violations may result in temporary or permanent suspension.
                        </p>

                        <hr>

                        <p class="text-muted">
                            <strong>{{ __('Agreement Version:') }}</strong> 1.0
                        </p>
                    </div>

                    <div class="row mt-4">
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('onboarding.agree') }}">
                                @csrf
                                <button type="submit" class="btn btn-success btn-lg btn-block w-100">
                                    {{ __('I Agree to the Rules') }}
                                </button>
                            </form>
                        </div>
                        <div class="col-md-6">
                            <form method="POST" action="{{ route('onboarding.decline') }}">
                                @csrf
                                <button type="submit" class="btn btn-secondary btn-lg btn-block w-100">
                                    {{ __('I Do Not Agree') }}
                                </button>
                            </form>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>
