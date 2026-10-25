<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use Magento\Store\Model\ScopeInterface;

/**
 * Minimum order amount policy for customers who are not logged in.
 *
 * Mirrors Address::validateMinimumAmount() (same address selection, discount/tax flags and rounding
 * tolerance) but compares against the guest threshold instead of sales/minimum_order/amount.
 */
class GuestMinimumAmount
{
    public const XML_PATH_ACTIVE = 'sales/minimum_order/active';
    public const XML_PATH_GUEST_AMOUNT = 'sales/minimum_order/guest_amount';
    private const XML_PATH_INCLUDE_DISCOUNT = 'sales/minimum_order/include_discount_amount';
    private const XML_PATH_TAX_INCLUDING = 'sales/minimum_order/tax_including';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Whether the guest minimum governs this quote: feature enabled, amount set and customer not logged in.
     *
     * @param Quote $quote
     * @return bool
     */
    public function isApplicable(Quote $quote): bool
    {
        return !$quote->getCustomerId()
            && $this->scopeConfig->isSetFlag(self::XML_PATH_ACTIVE, ScopeInterface::SCOPE_STORE, $quote->getStoreId())
            && $this->getMinimumAmount($quote) > 0;
    }

    /**
     * Guest minimum amount in base currency.
     *
     * @param Quote $quote
     * @return float
     */
    public function getMinimumAmount(Quote $quote): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_GUEST_AMOUNT,
            ScopeInterface::SCOPE_STORE,
            $quote->getStoreId()
        );
    }

    /**
     * Whether every relevant address reaches the guest minimum.
     *
     * @param Quote $quote
     * @return bool
     */
    public function isMet(Quote $quote): bool
    {
        return $this->getShortfall($quote) <= 0.0;
    }

    /**
     * Largest amount still missing across relevant addresses, in base currency (0 when the minimum is met).
     *
     * @param Quote $quote
     * @return float
     */
    public function getShortfall(Quote $quote): float
    {
        $minimum = $this->getMinimumAmount($quote);
        $storeId = $quote->getStoreId();
        $includeDiscount = $this->scopeConfig->isSetFlag(
            self::XML_PATH_INCLUDE_DISCOUNT,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
        $includeTax = $this->scopeConfig->isSetFlag(
            self::XML_PATH_TAX_INCLUDING,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );

        $shortfall = 0.0;
        foreach ($quote->getAllAddresses() as $address) {
            if (!$this->isRelevant($quote, $address)) {
                continue;
            }
            $taxes = $includeTax
                ? $address->getBaseTaxAmount() + $address->getBaseDiscountTaxCompensationAmount()
                : 0;
            $subtotal = $includeDiscount ? $address->getBaseSubtotalWithDiscount() : $address->getBaseSubtotal();
            $missing = $minimum - ($subtotal + $taxes);
            // Same tolerance as core: ($x > $y - 0.0001) means ($x >= $y) for floats.
            if ($missing > 0.0001) {
                $shortfall = max($shortfall, $missing);
            }
        }

        return $shortfall;
    }

    /**
     * Core checks the shipping address for physical quotes and the billing address for virtual ones.
     *
     * @param Quote $quote
     * @param Address $address
     * @return bool
     */
    private function isRelevant(Quote $quote, Address $address): bool
    {
        return !($quote->getIsVirtual() xor $address->getAddressType() === Address::TYPE_BILLING);
    }
}
