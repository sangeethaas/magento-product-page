<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Test\Unit\Model;

use Customisations\MinOrderAmount\Model\GuestMinimumAmount;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Address;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class GuestMinimumAmountTest extends TestCase
{
    /** @var ScopeConfigInterface&MockObject */
    private $scopeConfig;

    /** @var GuestMinimumAmount */
    private GuestMinimumAmount $model;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->model = new GuestMinimumAmount($this->scopeConfig);
    }

    /**
     * @param array $config
     */
    private function configure(array $config): void
    {
        $this->scopeConfig->method('isSetFlag')->willReturnCallback(
            static fn(string $path) => (bool)($config[$path] ?? false)
        );
        $this->scopeConfig->method('getValue')->willReturnCallback(
            static fn(string $path) => $config[$path] ?? null
        );
    }

    /**
     * @param int|null $customerId
     * @param bool $virtual
     * @param Address[] $addresses
     * @return Quote&MockObject
     */
    private function quote(?int $customerId, bool $virtual, array $addresses = []): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllAddresses', 'getIsVirtual'])
            ->getMock();
        $quote->setData(['customer_id' => $customerId, 'store_id' => 1]);
        $quote->method('getIsVirtual')->willReturn($virtual);
        $quote->method('getAllAddresses')->willReturn($addresses);

        return $quote;
    }

    /**
     * @param string $type
     * @param float $subtotal
     * @param float $withDiscount
     * @param float $tax
     * @return Address&MockObject
     */
    private function address(string $type, float $subtotal, float $withDiscount = 0.0, float $tax = 0.0): Address
    {
        $address = $this->getMockBuilder(Address::class)
            ->disableOriginalConstructor()
            ->onlyMethods([])
            ->getMock();
        $address->setData([
            'address_type' => $type,
            'base_subtotal' => $subtotal,
            'base_subtotal_with_discount' => $withDiscount,
            'base_tax_amount' => $tax,
            'base_discount_tax_compensation_amount' => 0.0,
        ]);

        return $address;
    }

    public static function applicabilityProvider(): array
    {
        $on = [GuestMinimumAmount::XML_PATH_ACTIVE => 1, GuestMinimumAmount::XML_PATH_GUEST_AMOUNT => '50'];
        return [
            'guest, enabled, amount set' => [$on, null, true],
            'logged in customer' => [$on, 5, false],
            'core minimum disabled' => [[GuestMinimumAmount::XML_PATH_GUEST_AMOUNT => '50'], null, false],
            'guest amount empty' => [[GuestMinimumAmount::XML_PATH_ACTIVE => 1], null, false],
            'guest amount zero' => [[GuestMinimumAmount::XML_PATH_ACTIVE => 1,
                GuestMinimumAmount::XML_PATH_GUEST_AMOUNT => '0'], null, false],
        ];
    }

    #[DataProvider('applicabilityProvider')]
    public function testIsApplicable(array $config, ?int $customerId, bool $expected): void
    {
        $this->configure($config);

        $this->assertSame($expected, $this->model->isApplicable($this->quote($customerId, false)));
    }

    public function testShortfallUsesSubtotalOnlyByDefault(): void
    {
        $this->configure([GuestMinimumAmount::XML_PATH_GUEST_AMOUNT => '50']);
        $quote = $this->quote(null, false, [
            $this->address(Address::TYPE_BILLING, 0.0),
            $this->address(Address::TYPE_SHIPPING, 37.5, 20.0),
        ]);

        $this->assertEqualsWithDelta(12.5, $this->model->getShortfall($quote), 0.00001);
        $this->assertFalse($this->model->isMet($quote));
    }

    public function testDiscountAndTaxFlagsAreRespected(): void
    {
        $this->configure([
            GuestMinimumAmount::XML_PATH_GUEST_AMOUNT => '50',
            'sales/minimum_order/include_discount_amount' => 1,
            'sales/minimum_order/tax_including' => 1,
        ]);
        $quote = $this->quote(null, false, [$this->address(Address::TYPE_SHIPPING, 60.0, 45.0, 5.0)]);

        $this->assertEqualsWithDelta(0.0, $this->model->getShortfall($quote), 0.00001);
        $this->assertTrue($this->model->isMet($quote));
    }

    public function testExactMinimumIsMet(): void
    {
        $this->configure([GuestMinimumAmount::XML_PATH_GUEST_AMOUNT => '50']);
        $quote = $this->quote(null, false, [$this->address(Address::TYPE_SHIPPING, 49.99999)]);

        $this->assertTrue($this->model->isMet($quote));
    }

    public function testVirtualQuoteValidatesBillingAddress(): void
    {
        $this->configure([GuestMinimumAmount::XML_PATH_GUEST_AMOUNT => '50']);
        $quote = $this->quote(null, true, [
            $this->address(Address::TYPE_BILLING, 80.0),
            $this->address(Address::TYPE_SHIPPING, 0.0),
        ]);

        $this->assertTrue($this->model->isMet($quote));
    }
}
