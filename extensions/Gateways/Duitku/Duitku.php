<?php

namespace Paymenter\Extensions\Gateways\Duitku;

use App\Classes\Extension\Gateway;
use App\Models\Invoice;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

class Duitku extends Gateway
{
    /**
     * Get configuration fields for the gateway.
     */
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
                'name' => 'payment_method',
                'label' => 'Payment Method',
                'type' => 'select',
                'options' => [
                    'OV' => 'OVO',
                    'SA' => 'ShopeePay',
                    'DA' => 'DANA',
                    'LJ' => 'LinkAja',
                    'QRIS' => 'QRIS',
                    'BT' => 'Permata Virtual Account',
                    'BC' => 'BCA Virtual Account',
                    'BR' => 'BRI Virtual Account',
                    'NI' => 'BNI Virtual Account',
                    'AK' => 'Akulaku Paylater',
                ],
                'description' => 'Choose the payment method to use',
                'required' => true,
            ],
            [
                'name' => 'sandbox_mode',
                'label' => 'Sandbox Mode',
                'type' => 'checkbox',
                'description' => 'Enable sandbox mode for testing',
                'required' => false,
            ],
        ];
    }

    /**
     * Determine if the gateway can be used for the given items.
     */
    public function canUseGateway($items, $type)
    {
        return !empty($this->config('merchant_code')) && !empty($this->config('api_key'));
    }

    /**
     * Process a payment and return redirect URL or error message.
     */
    public function pay(Invoice $invoice, $total)
    {
        $merchantCode = $this->config('merchant_code');
        $apiKey = $this->config('api_key');
        $paymentMethod = $this->config('payment_method');
        $baseUrl = $this->config('sandbox_mode') ? 'https://api-sandbox.duitku.com' : 'https://api.duitku.com';

        $payload = [
            'merchantCode' => $merchantCode,
            'paymentAmount' => $total,
            'paymentMethod' => $paymentMethod,
            'merchantOrderId' => (string) $invoice->id,
            'productDetails' => 'Invoice #' . $invoice->id,
            'customerVaName' => $invoice->user->name ?? 'Customer',
            'email' => $invoice->user->email ?? 'customer@example.com',
            'signature' => md5($merchantCode . $invoice->id . $total . $apiKey),
        ];

        try {
            $response = Http::post($baseUrl . '/api/merchant/v2/inquiry', $payload);
        } catch (ConnectionException|Throwable $e) {
            Log::error('Duitku API request failed', ['message' => $e->getMessage()]);

            return 'Payment could not be processed. Please try again later.';
        }

        if (!$response->successful()) {
            Log::error('Duitku API responded with error', [
                'status' => $response->status(),
                'body' => $response->body(),
            ]);

            return 'Payment could not be processed. Please try again later.';
        }

        $data = $response->json();

        return $data['paymentUrl'] ?? '';
    }
}
