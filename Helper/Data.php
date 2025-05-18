<?php
declare(strict_types=1);

namespace NeutromeLabs\Core\Helper;

use Magento\Backend\Model\UrlInterface as BackendUrlInterface;
use Magento\Framework\App\Config\Storage\WriterInterface;
use Magento\Framework\App\Helper\AbstractHelper;
use Magento\Framework\App\Helper\Context;
use Magento\Framework\App\RequestInterface;
use Psr\Log\LoggerInterface;

class Data extends AbstractHelper
{

    const BASE_URL_CONFIG_PATH = 'neutromelabs/cloud/base_url';

    const TOKEN_CONFIG_PATH = 'neutromelabs/cloud/token';

    protected WriterInterface $configWriter;

    protected BackendUrlInterface $backendUrlBuilder;

    protected RequestInterface $request;

    protected LoggerInterface $logger;

    public function __construct(
        Context             $context,
        WriterInterface     $configWriter,
        BackendUrlInterface $backendUrlBuilder,
        RequestInterface    $request
    )
    {
        parent::__construct($context);
        $this->configWriter = $configWriter;
        $this->backendUrlBuilder = $backendUrlBuilder;
        $this->request = $request;
        $this->logger = $context->getLogger();
    }

    public function getCallbackToken(): ?string
    {
        $callbackValue = $this->request->getParam('callback');
        if ($callbackValue && is_string($callbackValue)) {
            $decodedToken = base64_decode($callbackValue, true);
            if ($decodedToken === false) {
                $this->logger->warning('NeutromeLabs Core: Invalid base64 in callback parameter.');
                return null;
            }
            return $decodedToken;
        }
        return null;
    }

    public function getStoredToken(): ?string
    {
        return $this->scopeConfig->getValue(
            self::TOKEN_CONFIG_PATH
        );
    }

    public function saveToken(string $token): void
    {
        $this->configWriter->save(self::TOKEN_CONFIG_PATH, $token);
    }

    public function getEmailFromRecord(?array $userRecord): ?string
    {
        return $userRecord['email'] ?? null;
    }

    public function getSignInUrl(): ?string
    {
        $baseUrl = $this->getNeutromeBaseUrl();
        if (!$baseUrl) {
            return null;
        }
        return rtrim($baseUrl, '/') . '/profile';
    }

    public function getNeutromeBaseUrl(): ?string
    {
        return $this->scopeConfig->getValue(
            self::BASE_URL_CONFIG_PATH
        );
    }
}
