<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Model;

use Magento\Checkout\Model\Session as CheckoutSession;
use Magento\Framework\App\Area;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\App\State;
use Magento\Framework\Locale\CurrencyInterface;
use Magento\Framework\Phrase;
use Magento\Framework\Pricing\Helper\Data as PricingHelper;
use Magento\Quote\Model\Quote\Validator\MinimumOrderAmount\ValidationMessage;
use Magento\Store\Model\StoreManagerInterface;

/**
 * Tells guests how much more they need to add; everyone else gets the core message.
 *
 * getMessage() receives no quote, so the guest message is only built on the storefront (cart page and
 * checkout redirect), where the current quote is in the checkout session. Elsewhere (REST, GraphQL) the
 * core message is returned; the order is still blocked by GuestMinimumAmountPlugin.
 */
class GuestAwareValidationMessage extends ValidationMessage
{
    /**
     * @param ScopeConfigInterface $scopeConfig
     * @param StoreManagerInterface $storeManager
     * @param CurrencyInterface $currency
     * @param PricingHelper $priceHelper
     * @param GuestMinimumAmount $guestMinimumAmount
     * @param CheckoutSession $checkoutSession
     * @param State $appState
     */
    public function __construct(
        ScopeConfigInterface $scopeConfig,
        StoreManagerInterface $storeManager,
        CurrencyInterface $currency,
        private readonly PricingHelper $priceHelper,
        private readonly GuestMinimumAmount $guestMinimumAmount,
        private readonly CheckoutSession $checkoutSession,
        private readonly State $appState
    ) {
        parent::__construct($scopeConfig, $storeManager, $currency, $priceHelper);
    }

    /**
     * Get the guest shortfall message when it applies, otherwise the core message.
     *
     * @return Phrase
     */
    public function getMessage()
    {
        $message = $this->getGuestMessage();

        return $message ?? parent::getMessage();
    }

    /**
     * Build the guest shortfall message, or null when the guest rule does not apply.
     *
     * @return Phrase|null
     */
    private function getGuestMessage(): ?Phrase
    {
        if ($this->appState->getAreaCode() !== Area::AREA_FRONTEND) {
            return null;
        }

        $quote = $this->checkoutSession->getQuote();
        if (!$this->guestMinimumAmount->isApplicable($quote)) {
            return null;
        }

        $shortfall = $this->guestMinimumAmount->getShortfall($quote);
        if ($shortfall <= 0.0) {
            return null;
        }

        return __(
            'Add %1 more in products to check out. The minimum product total for guests is %2.',
            $this->priceHelper->currency($shortfall, true, false),
            $this->priceHelper->currency($this->guestMinimumAmount->getMinimumAmount($quote), true, false)
        );
    }
}
