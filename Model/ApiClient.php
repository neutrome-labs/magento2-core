<?php
declare(strict_types=1);

namespace NeutromeLabs\Core\Model;

use NeutromeLabs\Core\Helper\Data as CoreHelper;
use Magento\Framework\HTTP\Client\Curl;
use Psr\Log\LoggerInterface;

class ApiClient
{
    protected CoreHelper $coreHelper;
    protected Curl $curlClient;
    protected LoggerInterface $logger;

    public function __construct(
        CoreHelper $coreHelper,
        Curl $curlClient,
        LoggerInterface $logger
    ) {
        $this->coreHelper = $coreHelper;
        $this->curlClient = $curlClient;
        $this->logger = $logger;
    }

    public function fetch($url, $method = 'GET', $data = null, $headers = []) {
        $baseUrl = $this->coreHelper->getNeutromeBaseUrl();
        $apiUrl = rtrim($baseUrl, '/') . '/api' . $url;
        $this->curlClient->setHeaders(array_merge([
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $this->coreHelper->getStoredToken()
        ], $headers));

        try {
            if ($method === 'POST') {
                $this->curlClient->post($apiUrl, !!$data ? json_encode($data) : null);
            } else {
                $this->curlClient->get($apiUrl);
            }

            $responseBody = $this->curlClient->getBody();
            $statusCode = $this->curlClient->getStatus();

            if ($statusCode === 200) {
                return json_decode($responseBody, true);
            } else {
                $this->logger->error('ApiClient: API request failed.', ['url' => $apiUrl, 'status' => $statusCode, 'response' => $responseBody]);
                return null;
            }
        } catch (\Exception $e) {
            $this->logger->critical('ApiClient: Exception during API request.', ['exception' => $e->getMessage()]);
            return null;
        }
    }

    public function refreshAuthAndGetUserDetails(?string $currentToken): ?array
    {
        try {
            $responseData = $this->fetch('/collections/users/auth-refresh', 'POST', null, [
                'Authorization' => 'Bearer ' . ($currentToken ?? $this->coreHelper->getStoredToken())
            ]);
            if ($responseData === null) {
                $this->logger->error('ApiClient: Failed to fetch auth-refresh response.');
                return null;
            }

            if (isset($responseData['token']) && isset($responseData['record'])) {
                $this->coreHelper->saveToken($responseData['token']);
                $this->logger->info('ApiClient: Token refreshed and saved successfully.');
                return $responseData['record'];
            } else {
                $this->logger->warning('ApiClient: Auth-refresh response missing token or record.', ['response' => $responseData]);
                return null;
            }
        } catch (\Exception $e) {
            $this->logger->critical('ApiClient: Exception during auth-refresh API request.', ['exception' => $e->getMessage()]);
            return null;
        }
    }
}
