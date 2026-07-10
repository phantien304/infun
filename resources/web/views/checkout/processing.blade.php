@extends('web::layouts.main')

@section('content')
    <div class="container py-5">
        <div class="row justify-content-center">
            <div class="col-lg-6 text-center py-5" id="order-processing">
                <div class="spinner-border text-primary mb-4" role="status" style="width: 3rem; height: 3rem;">
                    <span class="visually-hidden">...</span>
                </div>
                <h4 class="mb-3">{{ trans('messages.OrderProcessingTitle') }}</h4>
                <p class="text-muted">{{ trans('messages.OrderProcessing') }}</p>
            </div>
        </div>
    </div>
@endsection

@section('script')
    <script>
        (function() {
            var attempts = 0;
            var maxAttempts = 120;

            var timer = setInterval(function() {
                attempts++;

                fetch('{{ route('checkout.status') }}', {
                        headers: {
                            'Accept': 'application/json'
                        },
                        cache: 'no-store'
                    })
                    .then(function(res) {
                        return res.json();
                    })
                    .then(function(json) {
                        var state = json.data || {};
                        if (state.status === 'done' || state.status === 'failed' || state.status ===
                            'none') {
                            clearInterval(timer);
                            window.location.href = state.redirect || '{{ route('checkout.index') }}';
                            return;
                        }
                        if (attempts >= maxAttempts) {
                            clearInterval(timer);
                            window.location.href = '{{ route('checkout.index') }}';
                        }
                    })
                    .catch(function() {});
            }, 500);
        })();
    </script>
@endsection
