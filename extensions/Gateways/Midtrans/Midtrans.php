<?php

namespace Paymenter\Extensions\Gateways\Midtrans;

use App\Classes\Extension\Gateway;
use App\Helpers\ExtensionHelper;
use App\Models\Invoice;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;

class Midtrans extends Gateway
{
    public function boot()
    {
        require __DIR__ . '/routes.php';
        View::addNamespace('gateways.midtrans', __DIR__ . '/resources/views');
    }

    public function getConfig($values = [])
    {
        return [
            [
                'name' => 'server_key',
                'label' => 'Server Key',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'client_key',
                'label' => 'Client Key',
                'type' => 'text',
                'required' => true,
            ],
            [
                'name' => 'test_mode',
                'label' => 'Test Mode',
                'type' => 'checkbox',
                'required' => false,
            ],
        ];
    }

    protected function baseUrl()
    {
        return $this->config('test_mode') ? 'https://app.sandbox.midtrans.com' : 'https://app.midtrans.com';
    }

    protected function request($method, $path, $data = [])
    {
        return Http::withBasicAuth($this->config('server_key'), '')
            ->$method($this->baseUrl() . $path, $data)
            ->object();
    }

    public function pay(Invoice $invoice, $total)
    {
        $payload = [
            'transaction_details' => [
                'order_id' => $invoice->id,
                'gross_amount' => $total,
            ],
            'customer_details' => [
                'first_name' => $invoice->user->name,
                'email' => $invoice->user->email,
            ],
        ];

        $response = $this->request('post', '/snap/v1/transactions', $payload);

        return view('gateways.midtrans::pay', [
            'invoice' => $invoice,
            'token' => $response->token ?? null,
            'clientKey' => $this->config('client_key'),
            'snapUrl' => $this->baseUrl() . '/snap/snap.js',
        ]);
    }

    public function webhook(Request $request)
    {
        $payload = $request->json()->all();
        $signature = hash('sha512', $payload['order_id'] . $payload['status_code'] . $payload['gross_amount'] . $this->config('server_key'));

        if (!hash_equals($signature, $payload['signature_key'] ?? '')) {
            return response()->json(['status' => 'invalid'], 400);
        }

        if (in_array($payload['transaction_status'], ['capture', 'settlement'])) {
            ExtensionHelper::addPayment(
                $payload['order_id'],
                'Midtrans',
                $payload['gross_amount'],
                0,
                $payload['transaction_id'] ?? null
            );
        }

        return response()->json(['status' => 'success']);
    }
}
