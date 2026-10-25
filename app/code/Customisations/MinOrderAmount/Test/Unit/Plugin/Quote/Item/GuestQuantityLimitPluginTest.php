<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Test\Unit\Plugin\Quote\Item;

use Customisations\MinOrderAmount\Model\GuestQuantityLimit;
use Customisations\MinOrderAmount\Plugin\Quote\Item\GuestQuantityLimitPlugin;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GuestQuantityLimitPluginTest extends TestCase
{
    /** @var GuestQuantityLimit&MockObject */
    private $guestQuantityLimit;

    /** @var Item&MockObject */
    private $item;

    /** @var GuestQuantityLimitPlugin */
    private GuestQuantityLimitPlugin $plugin;

    protected function setUp(): void
    {
        $this->guestQuantityLimit = $this->createMock(GuestQuantityLimit::class);
        $this->item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['addErrorInfo', 'removeErrorInfosByParams'])
            ->getMock();
        $this->plugin = new GuestQuantityLimitPlugin($this->guestQuantityLimit);
    }

    public function testAllowedQuantityAddsNoErrorAndIsPassedThrough(): void
    {
        $this->guestQuantityLimit->method('getViolation')->willReturn(null);
        $this->item->expects($this->never())->method('addErrorInfo');

        $this->assertSame([2], $this->plugin->beforeSetQty($this->item, 2));
    }

    public function testViolationFlagsItemErrorButKeepsQuantity(): void
    {
        $this->guestQuantityLimit->method('getViolation')->willReturn(__('Too many'));
        $this->item->expects($this->once())->method('addErrorInfo')->with(
            GuestQuantityLimitPlugin::ERROR_ORIGIN,
            GuestQuantityLimitPlugin::ERROR_CODE,
            'Too many'
        );

        $this->assertSame([9], $this->plugin->beforeSetQty($this->item, 9));
    }

    public function testEarlierOwnErrorIsClearedBeforeRevalidation(): void
    {
        $this->item->setData('has_error', true);
        $this->guestQuantityLimit->method('getViolation')->willReturn(null);
        $this->item->expects($this->once())->method('removeErrorInfosByParams')
            ->with(['origin' => GuestQuantityLimitPlugin::ERROR_ORIGIN]);

        $this->plugin->beforeSetQty($this->item, 1);
    }
}
