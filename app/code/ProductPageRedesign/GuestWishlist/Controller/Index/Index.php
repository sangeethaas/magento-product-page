<?php

declare(strict_types=1);

namespace ProductPageRedesign\GuestWishlist\Controller\Index;

use Magento\Customer\Model\Session as CustomerSession;
use Magento\Framework\App\Action\HttpGetActionInterface;
use Magento\Framework\Controller\Result\RedirectFactory;
use Magento\Framework\View\Result\PageFactory;

/**
 * Guest wishlist page. Logged-in customers are sent to their real wishlist.
 */
class Index implements HttpGetActionInterface
{
    public function __construct(
        private readonly PageFactory $pageFactory,
        private readonly RedirectFactory $redirectFactory,
        private readonly CustomerSession $customerSession
    ) {
    }

    public function execute()
    {
        if ($this->customerSession->isLoggedIn()) {
            return $this->redirectFactory->create()->setPath('wishlist');
        }

        $page = $this->pageFactory->create();
        $page->getConfig()->getTitle()->set(__('My Wish List'));

        return $page;
    }
}
