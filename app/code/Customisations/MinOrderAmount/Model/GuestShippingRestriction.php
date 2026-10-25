<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Store\Model\ScopeInterface;

/**
 * Removes configured shipping methods from the rates offered to customers who are not logged in.
 */
class GuestShippingRestriction
{
    public const XML_PATH_HIDDEN_METHODS = 'shipping/guest_restrictions/hidden_methods';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Drop hidden methods from the collected rates when the request belongs to a guest quote.
     *
     * @param Result $result
     * @param RateRequest $request
     * @return void
     */
    public function apply(Result $result, RateRequest $request): void
    {
        $hidden = $this->getHiddenMethodCodes($request->getStoreId());
        if (!$hidden || !$this->isGuestRequest($request)) {
            return;
        }

        $rates = $result->getAllRates();
        $kept = [];
        foreach ($rates as $rate) {
            // Error results carry no method, so their code never matches a hidden method.
            if (!in_array($rate->getCarrier() . '_' . $rate->getMethod(), $hidden, true)) {
                $kept[] = $rate;
            }
        }

        if (count($kept) === count($rates)) {
            return;
        }

        $result->reset();
        foreach ($kept as $rate) {
            $result->append($rate);
        }
    }

    /**
     * Method codes (carrier_method) hidden from guests.
     *
     * @param int|string|null $storeId
     * @return string[]
     */
    private function getHiddenMethodCodes($storeId): array
    {
        $value = $this->scopeConfig->getValue(self::XML_PATH_HIDDEN_METHODS, ScopeInterface::SCOPE_STORE, $storeId);
        $codes = is_array($value) ? $value : explode(',', (string)$value);

        return array_values(array_filter(array_map('trim', $codes), 'strlen'));
    }

    /**
     * The rate request carries no quote, so read it from the items being shipped.
     *
     * @param RateRequest $request
     * @return bool
     */
    private function isGuestRequest(RateRequest $request): bool
    {
        foreach ((array)$request->getAllItems() as $item) {
            $quote = $item->getQuote();
            if ($quote) {
                return !$quote->getCustomerId();
            }
        }

        return false;
    }
}
