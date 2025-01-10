{{-- $gateway --}}
<div class="card border-primary mb-3">
    <div class="card-header">
        <h3 class="card-title">{!! __('admin.instruction') !!}</h3>
    </div>
    <div class="card-body">
        <p class="mb-3">
            <strong>{{ __('gatewaypack::messages.monobank.get_token') }}:</strong>
            <a href="https://api.monobank.ua/index.html" target="_blank" class="text-primary">
                https://api.monobank.ua/index.html
            </a>
        </p>
        <div class="mt-4">
            <div class="alert alert-primary" role="alert">
                <strong>token:</strong>
                {{ __('gatewaypack::messages.monobank.descriptions.token') }}
            </div>
            <div class="alert alert-primary" role="alert">
                <strong>banka_url:</strong>
                {{ __('gatewaypack::messages.monobank.descriptions.banka_url') }}
                (<a href="https://send.monobank.ua/jar/9usP3bgfeG" target="_blank" class="text-warning">https://send.monobank.ua/jar/9usP3bgfeG</a>)
            </div>
            <div class="alert alert-danger" role="alert">
                {{ __('gatewaypack::messages.monobank.currency_warning') }}
            </div>
        </div>
{{--        <div class="alert alert-primary" role="alert">--}}
{{--            --}}{{-- Gateway endpoint URL --}}
{{--            {{ route('payment.return', $gateway->endpoint) }}--}}
{{--        </div>--}}
    </div>
</div>
