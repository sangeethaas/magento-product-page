<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Plugin\Quote;

use Customisations\MinOrderAmount\Model\GuestMinimumAmount;
use Magento\Quote\Model\Quote;

/**
 * Replaces the minimum amount decision for guests; logged-in customers keep the core behaviour.
 *
 * Around (not after) so core is skipped entirely for guests instead of being computed and discarded.
 */
class GuestMinimumAmountPlugin
{
    /**
     * @param GuestMinimumAmount $guestMinimumAmount
     */
    public function __construct(private readonly GuestMinimumAmount $guestMinimumAmount)
    {
    }

    /**
     * Use the guest threshold for guests, core's decision for everyone else.
     *
     * @param Quote $subject
     * @param callable $proceed
     * @param bool $multishipping
     * @return bool
     */
    public function aroundValidateMinimumAmount(Quote $subject, callable $proceed, $multishipping = false)
    {
        // Multishipping requires an account, so it never concerns guests.
        if ($multishipping || !$this->guestMinimumAmount->isApplicable($subject)) {
            return $proceed($multishipping);
        }

        return $this->guestMinimumAmount->isMet($subject);
    }
}
