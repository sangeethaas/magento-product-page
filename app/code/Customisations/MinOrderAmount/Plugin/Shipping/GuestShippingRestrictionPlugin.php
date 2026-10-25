<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Plugin\Shipping;

use Customisations\MinOrderAmount\Model\GuestShippingRestriction;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Shipping;

/**
 * Filters the collected rates for guests.
 *
 * After (not before) because the rates only exist once every carrier has answered, and filtering at this source
 * is what core validates against: a method without a saved rate is rejected when the order is placed, so the
 * restriction holds for the storefront, REST and GraphQL alike.
 */
class GuestShippingRestrictionPlugin
{
    /**
     * @param GuestShippingRestriction $guestShippingRestriction
     */
    public function __construct(private readonly GuestShippingRestriction $guestShippingRestriction)
    {
    }

    /**
     * Remove guest-hidden methods from the rate result after collection.
     *
     * The collectRates() method returns the Shipping instance and keeps its outcome in getResult(), so that
     * result object is what gets filtered.
     *
     * @param Shipping $subject
     * @param Shipping $returnValue
     * @param RateRequest $request
     * @return Shipping
     */
    public function afterCollectRates(Shipping $subject, $returnValue, RateRequest $request)
    {
        $this->guestShippingRestriction->apply($subject->getResult(), $request);

        return $returnValue;
    }
}
