<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Test\Unit\Plugin\Quote;

use Customisations\MinOrderAmount\Model\GuestMinimumAmount;
use Customisations\MinOrderAmount\Plugin\Quote\GuestMinimumAmountPlugin;
use Magento\Quote\Model\Quote;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GuestMinimumAmountPluginTest extends TestCase
{
    /** @var GuestMinimumAmount&MockObject */
    private $guestMinimumAmount;

    /** @var Quote&MockObject */
    private $quote;

    /** @var GuestMinimumAmountPlugin */
    private GuestMinimumAmountPlugin $plugin;

    protected function setUp(): void
    {
        $this->guestMinimumAmount = $this->createMock(GuestMinimumAmount::class);
        $this->quote = $this->createMock(Quote::class);
        $this->plugin = new GuestMinimumAmountPlugin($this->guestMinimumAmount);
    }

    public static function metProvider(): array
    {
        return ['minimum met' => [true], 'minimum not met' => [false]];
    }

    public function testNonGuestFallsThroughToCore(): void
    {
        $this->guestMinimumAmount->method('isApplicable')->willReturn(false);
        $this->guestMinimumAmount->expects($this->never())->method('isMet');

        $result = $this->plugin->aroundValidateMinimumAmount($this->quote, fn($multi) => 'core-result');

        $this->assertSame('core-result', $result);
    }

    public function testMultishippingFallsThroughToCore(): void
    {
        $this->guestMinimumAmount->method('isApplicable')->willReturn(true);
        $this->guestMinimumAmount->expects($this->never())->method('isMet');

        $result = $this->plugin->aroundValidateMinimumAmount($this->quote, fn($multi) => $multi, true);

        $this->assertTrue($result);
    }

    #[DataProvider('metProvider')]
    public function testGuestSkipsCoreAndUsesGuestDecision(bool $met): void
    {
        $this->guestMinimumAmount->method('isApplicable')->willReturn(true);
        $this->guestMinimumAmount->method('isMet')->willReturn($met);
        $proceed = function () {
            $this->fail('Core must not run for guests.');
        };

        $this->assertSame($met, $this->plugin->aroundValidateMinimumAmount($this->quote, $proceed));
    }
}
