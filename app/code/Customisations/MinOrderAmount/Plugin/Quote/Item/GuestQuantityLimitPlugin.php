<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Plugin\Quote\Item;

use Customisations\MinOrderAmount\Model\GuestQuantityLimit;
use Magento\Quote\Model\Quote\Item;

/**
 * Flags an item error before a guest's quantity is stored if it would exceed the guest cap.
 *
 * Reports through item error info, like core stock validation, so Quote::addProduct (which then removes the
 * item), Cart::updateItems and the REST cart item persister all surface the message without extra handling.
 * The quantity itself is passed through unchanged.
 */
class GuestQuantityLimitPlugin
{
    public const ERROR_ORIGIN = 'customisations_guest_quantity_limit';
    public const ERROR_CODE = 'guest_quantity_limit';

    /**
     * @param GuestQuantityLimit $guestQuantityLimit
     */
    public function __construct(private readonly GuestQuantityLimit $guestQuantityLimit)
    {
    }

    /**
     * Validate the incoming quantity against the guest cap.
     *
     * @param Item $subject
     * @param float|int|string $qty
     * @return array
     */
    public function beforeSetQty(Item $subject, $qty): array
    {
        // Drop our own earlier error so a corrected quantity is accepted.
        if ($subject->getHasError()) {
            $subject->removeErrorInfosByParams(['origin' => self::ERROR_ORIGIN]);
        }

        $violation = $this->guestQuantityLimit->getViolation($subject, (float)$qty);
        if ($violation !== null) {
            $subject->addErrorInfo(self::ERROR_ORIGIN, self::ERROR_CODE, (string)$violation);
        }

        return [$qty];
    }
}
