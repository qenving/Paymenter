<?php

namespace Paymenter\Extensions\Gateways\Duitku;

use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;

class Duitku extends Gateway
{
    public function boot()
    {
        require __DIR__ . '/routes.php';
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'merchant_code',
                'label' => 'Merchant Code',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'api_key',
                'label' => 'API Key',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'sandbox_mode',
                'label' => 'Sandbox Mode',
                'type' => 'checkbox',
                'description' => 'Enable sandbox environment',
                'required' => false,
            ],
        ];
    }

    private function endpoint()
    {
        return $this->config('sandbox_mode') ? 'https://sandbox.duitku.com' : 'https://passport.duitku.com';
    }

    public function pay(Invoice $invoice, $total)
    {
        $amount = number_format($total, 0, '', '');
        $orderId = (string) $invoice->id;
        $merchantCode = $this->config('merchant_code');
        $signature = hash('sha256', $merchantCode . $orderId . $amount . $this->config('api_key'));

        $response = Http::asJson()->post($this->endpoint() . '/api/merchant/createinvoice', [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $amount,
            'merchantOrderId' => $orderId,
            'productDetails' => 'Invoice #' . $invoice->id,
            'email' => $invoice->user->email,
            'callbackUrl' => route('extensions.gateways.duitku.webhook'),
            'returnUrl' => route('invoices.show', $invoice) . '?checkPayment=true',
            'signature' => $signature,
        ])->throw()->json();

        if (isset($response['paymentUrl'])) {
            return $response['paymentUrl'];
        }

        throw new \Exception('Duitku error: ' . ($response['message'] ?? 'Unknown error'));
    }

    public function webhook(Request $request)
    {
        $data = $request->all();
        $signature = hash('sha256', $data['merchantCode'] . $data['merchantOrderId'] . $data['amount'] . $data['resultCode'] . $this->config('api_key'));

        if ($signature !== ($data['signature'] ?? '')) {
            return response('Invalid signature', 400);
        }

        if ($data['resultCode'] === '00') {
            ExtensionHelper::addPayment($data['merchantOrderId'], 'Duitku', $data['amount'], $data['reference'] ?? null);
        }

        return response('OK');
    }
}
