<?php
declare(strict_types=1);

namespace NeutromeLabs\Core\Block\Adminhtml\System\Config;

use Exception;
use Magento\Backend\Block\Template\Context;
use Magento\Config\Block\System\Config\Form\Field;
use Magento\Framework\Data\Form\Element\AbstractElement;
use Magento\Framework\Module\Manager;
use NeutromeLabs\Core\Helper\Data as CoreHelper;
use NeutromeLabs\Core\Model\ApiClient;
use Psr\Log\LoggerInterface;

class AccountStatus extends Field
{
    protected $_template = 'NeutromeLabs_Core::system/config/account_status.phtml';

    protected CoreHelper $coreHelper;
    protected ApiClient $apiClient;
    protected Manager $moduleManager;
    protected LoggerInterface $logger;

    protected ?array $userRecord = null;
    protected ?string $userEmail = null;
    protected bool $tokenProcessed = false;
    protected ?string $statusMessage = null;

    public function __construct(
        Context    $context,
        CoreHelper $coreHelper,
        ApiClient  $apiClient,
        Manager    $moduleManager,
        array      $data = []
    )
    {
        $this->coreHelper = $coreHelper;
        $this->apiClient = $apiClient;
        $this->moduleManager = $moduleManager;
        $this->logger = $context->getLogger();
        parent::__construct($context, $data);

        // disable caching
        $this->setCacheLifetime(false);
        $this->setCacheKey(null);
    }

    public function render(AbstractElement $element): string
    {
        $element->unsScope()->unsCanUseWebsiteValue()->unsCanUseDefaultValue();
        if (!$this->tokenProcessed) {
            $this->processTokenAndFetchAccount();
        }
        return parent::render($element);
    }

    protected function processTokenAndFetchAccount(): void
    {
        if ($this->tokenProcessed) {
            return;
        }

        try {
            $this->userRecord = $this->apiClient->refreshAuthAndGetUserDetails($this->coreHelper->getCallbackToken());
            if ($this->userRecord) {
                $this->userEmail = $this->coreHelper->getEmailFromRecord($this->userRecord);
                if (!!$this->coreHelper->getCallbackToken() && $this->userEmail) {
                    $this->statusMessage = (string)__('Account status refreshed.');
                } else {
                    $this->statusMessage = (string)__('Token seems valid, but could not retrieve email. Please check NeutromeLabs account details.');
                    $this->logger->warning('NeutromeLabs Core: Token refreshed, but no email in user record.', ['record' => $this->userRecord]);
                }
            } else {
                $this->statusMessage = (string)__('Failed to verify account. It might be invalid or expired.');
                $this->logger->warning('NeutromeLabs Core: Failed to refresh token or get user record.', ['callbackTokenProvided' => !empty($this->coreHelper->getCallbackToken())]);
            }
        } catch (Exception $e) {
            $this->logger->critical('NeutromeLabs Core: Error processing token in AccountStatus block.', ['exception' => $e]);
            $this->statusMessage = (string)__('An unexpected error occurred. Please check logs.');
        }

        $this->tokenProcessed = true;
    }

    public function getAccountEmail(): ?string
    {
        return $this->userEmail;
    }

    public function getSignInUrl(): string
    {
        return $this->coreHelper->getSignInUrl() . "?wtoken=1&then=";
    }

    public function isSignedIn(): bool
    {
        return $this->userEmail !== null;
    }

    public function getStatusMessage(): ?string
    {
        return $this->statusMessage;
    }

    protected function _getElementHtml(AbstractElement $element): string
    {
        if (!$this->moduleManager->isEnabled('NeutromeLabs_Core')) {
            return '<p>' . (string)__('NeutromeLabs Core module is disabled.') . '</p>';
        }
        if (!$this->tokenProcessed) {
            $this->processTokenAndFetchAccount();
        }
        return $this->_toHtml();
    }
}
