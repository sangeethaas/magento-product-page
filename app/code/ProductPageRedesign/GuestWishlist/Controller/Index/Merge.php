<?php

declare(strict_types=1);

namespace ProductPageRedesign\GuestWishlist\Controller\Index;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpPostActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Wishlist\Model\WishlistFactory;
use Psr\Log\LoggerInterface;

/**
 * Adds the products a guest hearted in the browser to the logged-in customer's wishlist.
 */
class Merge implements HttpPostActionInterface
{
    private const MAX_PRODUCTS = 50;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly CustomerSession $customerSession,
        private readonly WishlistFactory $wishlistFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly LoggerInterface $logger
    ) {
    }

    public function execute()
    {
        $result = $this->jsonFactory->create();

        if (!$this->customerSession->isLoggedIn()) {
            return $result->setHttpResponseCode(401)->setData(['added' => 0]);
        }

        $productIds = array_slice(
            array_unique(array_filter(array_map('intval', (array) $this->request->getParam('products', [])))),
            0,
            self::MAX_PRODUCTS
        );

        $wishlist = $this->wishlistFactory->create()
            ->loadByCustomerId((int) $this->customerSession->getCustomerId(), true);

        $existing = [];
        foreach ($wishlist->getItemCollection() as $item) {
            $existing[(int) $item->getProductId()] = true;
        }

        $added = 0;
        foreach ($productIds as $productId) {
            if (isset($existing[$productId])) {
                continue;
            }
            try {
                $wishlist->addNewItem($this->productRepository->getById($productId));
                $added++;
            } catch (\Exception $e) {
                $this->logger->warning('Guest wishlist merge skipped product ' . $productId . ': ' . $e->getMessage());
            }
        }

        if ($added > 0) {
            $wishlist->save();
        }

        return $result->setData(['added' => $added]);
    }
}
