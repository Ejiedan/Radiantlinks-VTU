<?php

declare(strict_types=1);

namespace App\Services;

use PDO;

final class PaymentGatewayService
{
    public function __construct(private PDO $db)
    {
    }

    public function configured(string $provider): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM provider_configs WHERE service = 'wallet_gateway' AND provider_key = ? AND status = 'active' LIMIT 1");
        $stmt->execute([$provider]);
        $config = $stmt->fetch();
        if (!$config) {
            return null;
        }
        $config['credentials_array'] = json_decode((string) ($config['credentials'] ?? '{}'), true) ?: [];
        $config['mappings_array'] = json_decode((string) ($config['mappings'] ?? '{}'), true) ?: [];
        return $config;
    }

    public function generateDedicatedAccount(string $provider, array $user, ?array $kyc): array
    {
        return match ($provider) {
            'securewave' => $this->securewaveAccount($user, $kyc),
            'paymentpoint' => $this->paymentpointAccount($user, $kyc),
            default => throw new \RuntimeException('This payment provider does not yet support automatic account generation.'),
        };
    }

    public function initializeCheckout(string $provider, array $user, float $amount, string $reference): array
    {
        $config = $this->configured($provider);
        if (!$config) {
            throw new \RuntimeException('Payment gateway is not configured.');
        }
        $credentials = $config['credentials_array'];
        $checkoutEndpoint = trim((string) ($credentials['checkout_endpoint'] ?? ''));
        if ($checkoutEndpoint === '') {
            throw new \RuntimeException('Checkout endpoint is not configured for this gateway. Use dedicated account or manual funding.');
        }

        $payload = [
            'email' => $user['email'] ?? '',
            'amount' => $amount,
            'reference' => $reference,
            'callback_url' => trim((string) ($credentials['callback_url'] ?? ''))
                ?: absolute_url('/payment/callback?provider=' . rawurlencode($provider)),
            'metadata' => ['user_id' => (int) ($user['id'] ?? 0), 'provider' => $provider],
        ];

        $response = $this->request('POST', $checkoutEndpoint, $this->headers($provider, $config), $payload);
        $url = $this->firstValue($response['body'], [
            'authorization_url', 'checkout_url', 'payment_url', 'url', 'data.authorization_url', 'data.checkout_url', 'data.payment_url', 'data.url',
        ]);
        if ($url === '') {
            throw new \RuntimeException('Gateway did not return a checkout URL.');
        }

        return ['url' => $url, 'reference' => $reference, 'raw_response' => $response['body']];
    }

    private function securewaveAccount(array $user, ?array $kyc): array
    {
        $config = $this->configured('securewave');
        if (!$config) {
            throw new \RuntimeException('Securewave is not configured or not active.');
        }
        if (!$kyc) {
            throw new \RuntimeException('Approved KYC is required before generating this account number.');
        }

        $credentials = $config['credentials_array'];
        $base = rtrim((string) ($config['base_url'] ?: 'https://securewaveng.com/api'), '/');
        $payload = [
            'email' => $user['email'] ?? '',
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?: ($user['first_name'] ?? 'Customer'),
            'phone_number' => $user['phone'] ?? '',
            'bank_code' => array_values(array_filter(array_map('intval', explode(',', (string) ($credentials['bank_code'] ?? '1'))))),
            'business_id' => (string) ($credentials['business_id'] ?? ''),
            'account_type' => 'static',
            'id_type' => $kyc['id_type'],
            'id_number' => $kyc['id_number'],
        ];
        if ($payload['business_id'] === '') {
            throw new \RuntimeException('Securewave business_id is required in API setup.');
        }

        $response = $this->request('POST', $base . '/virtual_accounts/generate', $this->headers('securewave', $config), $payload);
        $body = $response['body'];
        $accountNumber = $this->firstValue($body, ['account_number', 'accountNumber', 'data.account_number', 'data.accountNumber', 'data.accounts.0.account_number']);
        if ($accountNumber === '') {
            throw new \RuntimeException($this->firstValue($body, ['message', 'error', 'data.message']) ?: 'Securewave did not return an account number.');
        }

        return [
            'provider' => 'securewave',
            'account_name' => $this->firstValue($body, ['account_name', 'accountName', 'data.account_name', 'data.accountName']) ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'account_number' => $accountNumber,
            'bank_name' => $this->firstValue($body, ['bank_name', 'bankName', 'data.bank_name', 'data.bankName', 'data.accounts.0.bank_name']) ?: 'Securewave Virtual Account',
            'reference' => $this->firstValue($body, ['reference', 'data.reference', 'account_reference', 'data.account_reference']) ?: 'SWV' . date('YmdHis') . random_int(100, 999),
            'raw_response' => $body,
        ];
    }

    private function paymentpointAccount(array $user, ?array $kyc): array
    {
        $config = $this->configured('paymentpoint');
        if (!$config) {
            throw new \RuntimeException('PaymentPoint is not configured or not active.');
        }
        if (!$kyc && (($config['credentials_array']['requires_kyc'] ?? 'yes') !== 'no')) {
            throw new \RuntimeException('Approved KYC is required before generating this account number.');
        }

        $base = rtrim((string) ($config['base_url'] ?: 'https://api.paymentpoint.co'), '/');
        $headers = $this->headers('paymentpoint', $config);
        $customerPayload = [
            'first_name' => $user['first_name'] ?? '',
            'last_name' => $user['last_name'] ?: ($user['first_name'] ?? 'Customer'),
            'phone_number' => $user['phone'] ?? '',
            'email' => $user['email'] ?? '',
        ];
        if ($kyc) {
            $customerPayload['id_type'] = $kyc['id_type'];
            $customerPayload['id_number'] = $kyc['id_number'];
            $customerPayload['dob'] = $kyc['dob'];
        }

        $customer = $this->request('POST', $base . '/api/create/business/customer', $headers, $customerPayload);
        $customerId = $this->firstValue($customer['body'], ['id', 'user_id', 'customer_id', 'data.id', 'data.user_id', 'data.customer_id']);
        if ($customerId === '') {
            $customerId = (string) ($user['id'] ?? '');
        }

        $account = $this->request('POST', $base . '/api/create/reserved-account', $headers, [
            'user_id' => $customerId,
            'bank_type' => (string) ($config['credentials_array']['bank_type'] ?? '20946'),
        ]);
        $body = $account['body'];
        $accountNumber = $this->firstValue($body, ['account_number', 'accountNumber', 'data.account_number', 'data.accountNumber', 'data.account.account_number']);
        if ($accountNumber === '') {
            throw new \RuntimeException($this->firstValue($body, ['message', 'error', 'data.message']) ?: 'PaymentPoint did not return an account number.');
        }

        return [
            'provider' => 'paymentpoint',
            'account_name' => $this->firstValue($body, ['account_name', 'accountName', 'data.account_name', 'data.accountName']) ?: trim(($user['first_name'] ?? '') . ' ' . ($user['last_name'] ?? '')),
            'account_number' => $accountNumber,
            'bank_name' => $this->firstValue($body, ['bank_name', 'bankName', 'data.bank_name', 'data.bankName']) ?: 'PaymentPoint Virtual Account',
            'reference' => $this->firstValue($body, ['reference', 'data.reference', 'account_reference', 'data.account_reference']) ?: 'PMP' . date('YmdHis') . random_int(100, 999),
            'raw_response' => ['customer' => $customer['body'], 'account' => $body],
        ];
    }

    private function headers(string $provider, array $config): array
    {
        $credentials = $config['credentials_array'];
        if ($provider === 'securewave') {
            return [
                'Accept: application/json',
                'Content-Type: application/json',
                'Authorization: Bearer ' . (string) ($credentials['secret_key'] ?? ''),
                'x-api-key: ' . (string) ($credentials['public_key'] ?? $credentials['api_key'] ?? ''),
            ];
        }

        $token = (string) ($credentials['token'] ?? $credentials['secret_key'] ?? '');
        return [
            'Accept: application/json',
            'Content-Type: application/json',
            'Authorization: Bearer ' . $token,
        ];
    }

    private function request(string $method, string $url, array $headers, array $payload = []): array
    {
        $ch = curl_init($url);
        curl_setopt_array($ch, [
            CURLOPT_RETURNTRANSFER => true,
            CURLOPT_CUSTOMREQUEST => $method,
            CURLOPT_HTTPHEADER => $headers,
            CURLOPT_TIMEOUT => 45,
        ]);
        if ($method !== 'GET') {
            curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
        }
        $raw = curl_exec($ch);
        $error = curl_error($ch);
        $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
        curl_close($ch);

        if ($raw === false) {
            throw new \RuntimeException('Gateway connection failed: ' . $error);
        }
        $body = json_decode((string) $raw, true);
        if (!is_array($body)) {
            $body = ['raw' => (string) $raw];
        }
        if ($status >= 400) {
            throw new \RuntimeException($this->firstValue($body, ['message', 'error', 'data.message']) ?: 'Gateway returned HTTP ' . $status);
        }
        return ['status' => $status, 'body' => $body];
    }

    public function firstValue(array $payload, array $paths): string
    {
        foreach ($paths as $path) {
            $value = $this->path($payload, $path);
            if ($value !== null && $value !== '') {
                return is_scalar($value) ? (string) $value : json_encode($value);
            }
        }
        return '';
    }

    private function path(array $payload, string $path): mixed
    {
        $cursor = $payload;
        foreach (explode('.', $path) as $part) {
            if (is_array($cursor) && array_key_exists($part, $cursor)) {
                $cursor = $cursor[$part];
                continue;
            }
            if (is_array($cursor) && ctype_digit($part) && array_key_exists((int) $part, $cursor)) {
                $cursor = $cursor[(int) $part];
                continue;
            }
            return null;
        }
        return $cursor;
    }
}
