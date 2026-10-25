<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Model;

use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Framework\Phrase;
use Magento\Quote\Model\Quote\Item;
use Magento\Store\Model\ScopeInterface;

/**
 * Per-product quantity cap for customers who are not logged in.
 *
 * Complements (does not replace) the standard Maximum Qty Allowed in Shopping Cart, which applies to everyone.
 */
class GuestQuantityLimit
{
    public const XML_PATH_GUEST_MAX_SALE_QTY = 'cataloginventory/item_options/guest_max_sale_qty';

    /**
     * @param ScopeConfigInterface $scopeConfig
     */
    public function __construct(private readonly ScopeConfigInterface $scopeConfig)
    {
    }

    /**
     * Describe the violation if setting the item to $qty would exceed the guest cap, null when allowed.
     *
     * The cap is per product across cart lines, so the same product with different options is summed.
     *
     * @param Item $item
     * @param float $qty New total quantity for $item
     * @return Phrase|null
     */
    public function getViolation(Item $item, float $qty): ?Phrase
    {
        $quote = $item->getQuote();
        // Child items (e.g. configurable simple) follow their parent's quantity.
        if (!$quote || $quote->getCustomerId() || $item->getParentItem()) {
            return null;
        }

        $limit = $this->getLimit($quote->getStoreId());
        if ($limit <= 0) {
            return null;
        }

        $total = $qty;
        foreach ($quote->getAllVisibleItems() as $other) {
            if ($other !== $item && (int)$other->getProductId() === (int)$item->getProductId()) {
                $total += (float)$other->getQty();
            }
        }

        if ($total <= $limit) {
            return null;
        }

        return __(
            'As a guest you can add at most %1 of "%2" to your cart. Log in to buy more.',
            $limit,
            $item->getName()
        );
    }

    /**
     * Guest cap for the store, 0 when none is configured.
     *
     * @param int|string|null $storeId
     * @return float
     */
    private function getLimit($storeId): float
    {
        return (float)$this->scopeConfig->getValue(
            self::XML_PATH_GUEST_MAX_SALE_QTY,
            ScopeInterface::SCOPE_STORE,
            $storeId
        );
    }
}
