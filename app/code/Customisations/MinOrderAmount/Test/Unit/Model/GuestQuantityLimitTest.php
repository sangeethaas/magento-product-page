<?php
declare(strict_types=1);

namespace Customisations\MinOrderAmount\Test\Unit\Model;

use Customisations\MinOrderAmount\Model\GuestQuantityLimit;
use Magento\Framework\App\Config\ScopeConfigInterface;
use Magento\Quote\Model\Quote;
use Magento\Quote\Model\Quote\Item;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GuestQuantityLimitTest extends TestCase
{
    /** @var ScopeConfigInterface */
    private $scopeConfig;

    /** @var GuestQuantityLimit */
    private GuestQuantityLimit $model;

    protected function setUp(): void
    {
        $this->scopeConfig = $this->createMock(ScopeConfigInterface::class);
        $this->model = new GuestQuantityLimit($this->scopeConfig);
    }

    /**
     * @param mixed $limit
     */
    private function limit($limit): void
    {
        $this->scopeConfig->method('getValue')->willReturn($limit);
    }

    /**
     * @param int|null $customerId
     * @param Item[] $items
     * @return Quote
     */
    private function quote(?int $customerId, array &$items): Quote
    {
        $quote = $this->getMockBuilder(Quote::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getAllVisibleItems'])
            ->getMock();
        $quote->setData(['customer_id' => $customerId, 'store_id' => 1]);
        $quote->method('getAllVisibleItems')->willReturnCallback(static function () use (&$items) {
            return $items;
        });

        return $quote;
    }

    /**
     * @param Quote $quote
     * @param int $productId
     * @param float $qty
     * @param Item|null $parent
     * @return Item
     */
    private function item(Quote $quote, int $productId, float $qty, ?Item $parent = null): Item
    {
        $item = $this->getMockBuilder(Item::class)
            ->disableOriginalConstructor()
            ->onlyMethods(['getQuote', 'getParentItem'])
            ->getMock();
        $item->setData(['product_id' => $productId, 'qty' => $qty, 'name' => 'Chair']);
        $item->method('getQuote')->willReturn($quote);
        $item->method('getParentItem')->willReturn($parent);

        return $item;
    }

    public static function limitProvider(): array
    {
        return [
            'within limit' => ['3', 3.0, false],
            'over limit' => ['3', 3.5, true],
            'no limit configured' => [null, 99.0, false],
            'empty limit' => ['', 99.0, false],
        ];
    }

    #[DataProvider('limitProvider')]
    public function testGuestLimit($limit, float $qty, bool $violates): void
    {
        $this->limit($limit);
        $items = [];
        $item = $this->item($this->quote(null, $items), 10, 0.0);
        $items[] = $item;

        $violation = $this->model->getViolation($item, $qty);

        $this->assertSame($violates, $violation !== null);
    }

    public function testLoggedInCustomerIsNotLimited(): void
    {
        $this->limit('3');
        $items = [];
        $item = $this->item($this->quote(7, $items), 10, 0.0);
        $items[] = $item;

        $this->assertNull($this->model->getViolation($item, 50.0));
    }

    public function testChildItemIsNotChecked(): void
    {
        $this->limit('3');
        $items = [];
        $quote = $this->quote(null, $items);
        $parent = $this->item($quote, 10, 1.0);
        $child = $this->item($quote, 11, 0.0, $parent);

        $this->assertNull($this->model->getViolation($child, 50.0));
    }

    public function testSameProductInOtherLinesIsSummed(): void
    {
        $this->limit('5');
        $items = [];
        $quote = $this->quote(null, $items);
        $other = $this->item($quote, 10, 3.0);
        $item = $this->item($quote, 10, 0.0);
        $unrelated = $this->item($quote, 99, 20.0);
        $items = [$other, $item, $unrelated];

        $this->assertNotNull($this->model->getViolation($item, 3.0));
        $this->assertNull($this->model->getViolation($item, 2.0));
    }
}
