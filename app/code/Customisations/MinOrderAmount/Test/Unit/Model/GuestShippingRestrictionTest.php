<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Test\Unit\Model;

use Customisations\MinOrderAmount\Model\GuestShippingRestriction;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Quote\Model\Quote\Address\RateResult\Method;
use Magento\Quote\Model\Quote\Item;
use Magento\Shipping\Model\Rate\Result;
use Magento\Store\Model\StoreManagerInterface;
use PHPUnit\Framework\TestCase;

class GuestShippingRestrictionTest extends TestCase
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var GuestShippingRestriction */
    private GuestShippingRestriction $model;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->model = new GuestShippingRestriction($this->scopeConfig);
    }

    /**
     * @param string $carrier
     * @param string $method
     * @return Method
     */
    private function rate(string $carrier, string $method): Method
    {
        $rate = $this->getMockBuilder(Method::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $rate->setData(['carrier' => $carrier, 'method' => $method]);

        return $rate;
    }

    /**
     * @return Result
     */
    private function rateResult(): Result
    {
        $result = new Result($this->createMock(StoreManagerInterface::class));
        $result->append($this->rate('flatrate', 'flatrate'));
        $result->append($this->rate('tablerate', 'bestway'));
        $result->append($this->rate('freeshipping', 'freeshipping'));

        return $result;
    }

    /**
     * @param int|null $customerId
     * @return RateRequest
     */
    private function request(?int $customerId): RateRequest
    {
        $quote = $this->getMockBuilder(Quote::class)->disableOriginalConstructor()->onlyMethods([])->getMock();
        $quote->setData('customer_id', $customerId);
        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote'])
            ->getMock();
        $item->method('getQuote')->willReturn($quote);
        $request = new RateRequest();
        $request->setStoreId(1);
        $request->setAllItems([$item]);

        return $request;
    }

    /**
     * @param Result $result
     * @return string[]
     */
    private function codes(Result $result): array
    {
        return array_map(
            static fn($rate) => $rate->getCarrier() . '_' . $rate->getMethod(),
            $result->getAllRates()
        );
    }

    public function testGuestLosesHiddenMethodsOnly(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('flatrate_flatrate,freeshipping_freeshipping');
        $result = $this->rateResult();

        $this->model->apply($result, $this->request(null));

        $this->assertSame(['tablerate_bestway'], $this->codes($result));
    }

    public function testArrayConfigValueIsSupported(): void
    {
        $this->scopeConfig->method('getValue')->willReturn(['flatrate_flatrate']);
        $result = $this->rateResult();

        $this->model->apply($result, $this->request(null));

        $this->assertSame(['tablerate_bestway', 'freeshipping_freeshipping'], $this->codes($result));
    }

    public function testLoggedInCustomerKeepsAllMethods(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('flatrate_flatrate');
        $result = $this->rateResult();

        $this->model->apply($result, $this->request(5));

        $this->assertCount(3, $result->getAllRates());
    }

    public function testNothingConfiguredKeepsAllMethods(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('');
        $result = $this->rateResult();

        $this->model->apply($result, $this->request(null));

        $this->assertCount(3, $result->getAllRates());
    }

    public function testRequestWithoutItemsIsLeftAlone(): void
    {
        $this->scopeConfig->method('getValue')->willReturn('flatrate_flatrate');
        $result = $this->rateResult();
        $request = new RateRequest();
        $request->setStoreId(1);

        $this->model->apply($result, $request);

        $this->assertCount(3, $result->getAllRates());
    }
}
