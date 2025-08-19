<?php

namespace Paymenter\Extensions\Gateways\Duitku;

use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class Duitku extends Gateway
{
    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('gateways.duitku', __DIR__ . '/resources/views');
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
                'description' => 'Enable sandbox mode',
            ],
        ];
    }

    private function endpoint()
    {
        return $this->config('sandbox_mode') ? 'https://sandbox.duitku.com' : 'https://duitku.com';
    }

    public function pay($invoice, $total)
    {
        $merchantCode = $this->config('merchant_code');
        $apiKey = $this->config('api_key');
        $orderId = (string) $invoice->id;
        $amount = $total;
        $signature = md5($merchantCode . $orderId . $amount . $apiKey);

        $payload = [
            'merchantCode' => $merchantCode,
            'merchantOrderId' => $orderId,
            'paymentAmount' => $amount,
            'productDetails' => 'Invoice #' . $invoice->id,
            'email' => $invoice->user->email,
            'callbackUrl' => route('extensions.gateways.duitku.webhook'),
            'returnUrl' => route('invoices.show', $invoice) . '?checkPayment=true',
            'signature' => $signature,
        ];

        $response = Http::withHeaders(['Content-Type' => 'application/json'])
            ->post($this->endpoint() . '/webapi/api/merchant/v2/inquiry', $payload)
            ->object();

        if (!isset($response->paymentUrl)) {
            abort(400, 'Unable to create Duitku payment');
        }

        return view('gateways.duitku::pay', [
            'invoice' => $invoice,
            'paymentUrl' => $response->paymentUrl,
        ]);
    }

    public function webhook(Request $request)
    {
        $data = $request->all();

        $signature = md5(
            ($data['merchantCode'] ?? '') .
            ($data['amount'] ?? '') .
            ($data['merchantOrderId'] ?? '') .
            $this->config('api_key')
        );

        if ($signature !== ($data['signature'] ?? '')) {
            return response()->json(['error' => 'Invalid signature'], 400);
        }

        if (($data['resultCode'] ?? '') === '00') {
            ExtensionHelper::addPayment(
                $data['merchantOrderId'],
                'Duitku',
                $data['amount'],
                null,
                $data['reference'] ?? null
            );
        }

        return response()->json(['message' => 'OK']);
    }
}
