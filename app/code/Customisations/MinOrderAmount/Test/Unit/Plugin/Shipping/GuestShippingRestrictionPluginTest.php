<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Test\Unit\Plugin\Shipping;

use Customisations\MinOrderAmount\Model\GuestShippingRestriction;
use Customisations\MinOrderAmount\Plugin\Shipping\GuestShippingRestrictionPlugin;
use Magento\Quote\Model\Quote\Address\RateRequest;
use Magento\Shipping\Model\Rate\Result;
use Magento\Shipping\Model\Shipping;
use PHPUnit\Framework\TestCase;

class GuestShippingRestrictionPluginTest extends TestCase
{
    public function testFiltersTheCollectedResultAndReturnsTheSameShipping(): void
    {
        $result = $this->createMock(Result::class);
        $shipping = $this->createMock(Shipping::class);
        $shipping->method('getResult')->willReturn($result);
        $request = new RateRequest();
        $restriction = $this->createMock(GuestShippingRestriction::class);
        $restriction->expects($this->once())->method('apply')->with($result, $request);

        $plugin = new GuestShippingRestrictionPlugin($restriction);

        $this->assertSame($shipping, $plugin->afterCollectRates($shipping, $shipping, $request));
    }
}
