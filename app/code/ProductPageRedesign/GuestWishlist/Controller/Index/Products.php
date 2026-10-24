<?php

declare(strict_types=1);

namespace ProductPageRedesign\GuestWishlist\Controller\Index;

use Magento\Catalog\Api\ProductRepositoryInterface;
use Magento\Catalog\Helper\Image as ImageHelper;
use Magento\Catalog\Model\Product\Attribute\Source\Status;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\App\RequestInterface;
use Magento\Framework\Controller\Result\JsonFactory;
use Magento\Framework\Pricing\PriceCurrencyInterface;

/**
 * Returns display data for the product IDs a guest saved in the browser.
 */
class Products implements HttpGetActionInterface
{
    private const MAX_PRODUCTS = 50;

    public function __construct(
        private readonly RequestInterface $request,
        private readonly JsonFactory $jsonFactory,
        private readonly ProductRepositoryInterface $productRepository,
        private readonly ImageHelper $imageHelper,
        private readonly PriceCurrencyInterface $priceCurrency
    ) {
    }

    public function execute()
    {
        $ids = array_slice(
            array_unique(array_filter(array_map('intval', (array) $this->request->getParam('products', [])))),
            0,
            self::MAX_PRODUCTS
        );

        $items = [];
        foreach ($ids as $id) {
            try {
                $product = $this->productRepository->getById($id);
            } catch (\Exception $e) {
                continue;
            }

            if ((int) $product->getStatus() !== Status::STATUS_ENABLED || !$product->isVisibleInSiteVisibility()) {
                continue;
            }

            $final = (float) $product->getFinalPrice();
            $regular = (float) $product->getPrice();

            $items[] = [
                'id' => (int) $product->getId(),
                'name' => (string) $product->getName(),
                'url' => $product->getProductUrl(),
                'image' => $this->imageHelper->init($product, 'product_small_image')->getUrl(),
                'price' => $this->priceCurrency->format($final, false),
                'old_price' => $regular > $final ? $this->priceCurrency->format($regular, false) : '',
                'saleable' => (bool) $product->isSaleable(),
                'quick_add' => $product->getTypeId() === 'simple' && !$product->getRequiredOptions(),
            ];
        }

        return $this->jsonFactory->create()->setData(['items' => $items]);
    }
}
