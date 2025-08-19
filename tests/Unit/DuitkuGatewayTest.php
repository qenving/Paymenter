<?php

namespace Tests\Unit;

use App\Models\Invoice;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Paymenter\Extensions\Gateways\Duitku\Duitku;
use Tests\TestCase;

class DuitkuGatewayTest extends TestCase
{
    public function test_pay_success()
    {
        Http::fake([
            '*' => Http::response(['paymentUrl' => 'https://duitku.test/redirect'], 200),
        ]);

        $invoice = new Invoice;
        $invoice->id = 1;
        $invoice->currency_code = 'IDR';
        $invoice->user = (object) ['name' => 'John Doe', 'email' => 'john@example.com'];

        $gateway = new Duitku([
            'merchant_code' => 'M123',
            'api_key' => 'secret',
            'payment_method' => 'QRIS',
            'sandbox_mode' => true,
        ]);

        $url = $gateway->pay($invoice, 10);

        $this->assertSame('https://duitku.test/redirect', $url);
    }

    public function test_pay_failure_logs_and_returns_message()
    {
        Http::fake([
            '*' => Http::response('Error', 500),
        ]);

        Log::spy();

        $invoice = new Invoice;
        $invoice->id = 1;
        $invoice->currency_code = 'IDR';
        $invoice->user = (object) ['name' => 'John Doe', 'email' => 'john@example.com'];

        $gateway = new Duitku([
            'merchant_code' => 'M123',
            'api_key' => 'secret',
            'payment_method' => 'QRIS',
            'sandbox_mode' => true,
        ]);

        $message = $gateway->pay($invoice, 10);

        Log::shouldHaveReceived('error')->once();
        $this->assertSame('Payment could not be processed. Please try again later.', $message);
    }
}
