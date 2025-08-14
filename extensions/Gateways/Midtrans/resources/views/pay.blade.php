<script src="{{ $snapUrl }}" data-client-key="{{ $clientKey }}"></script>
@script
    <script>
        snap.pay("{{ $token }}", {
            onSuccess: function () {
                window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true";
            },
            onPending: function () {
                window.location.href = "{{ route('invoices.show', $invoice) }}?checkPayment=true";
            },
            onError: function () {
                window.location.href = "{{ route('invoices.show', $invoice) }}";
            },
            onClose: function () {
                window.location.href = "{{ route('invoices.show', $invoice) }}";
            }
        });
    </script>
@endscript
