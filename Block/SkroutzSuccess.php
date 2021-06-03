<?php

namespace MageGuide\Skroutz\Block;

/**
 * Block class for order success page
 * @package  MageGuide_Skroutz
 * @module   Skroutz
 * @author   MageGuide Developer
 */
class SkroutzSuccess extends \Magento\Framework\View\Element\Template
{
    /**
     * @var \Magento\Checkout\Model\Session
     */
    protected $_checkoutSession;

    /**
     * @var \Magento\Sales\Model\OrderFactory
     */
    protected $_orderFactory;

    /**
     * @var \Magento\Catalog\Model\Product
     */
    protected $_product;

    /**
     * @var \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable
     */
    protected $_catalogProductTypeConfigurable;

    /**
     * @var \Magento\Framework\App\Config\ScopeConfigInterface
     */
    protected $_scopeConfig;

    /**
     * @var \Magento\Sales\Model\Order
     */
    protected $_order;

    /**
     * @var string
     */
    protected $_orderInrementId;

    /**
     * constructor class
     *
     * @param \Magento\Checkout\Model\Session                                               $checkoutSession
     * @param \Magento\Sales\Model\OrderFactory                                             $orderFactory
     * @param \Magento\Catalog\Model\Product                                                $product
     * @param \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable    $catalogProductTypeConfigurable
     * @param \Magento\Framework\View\Element\Template\Context                              $context
     */
    public function __construct(
        \Magento\Checkout\Model\Session $checkoutSession,
        \Magento\Sales\Model\OrderFactory $orderFactory,
        \Magento\Catalog\Model\Product $product,
        \Magento\ConfigurableProduct\Model\ResourceModel\Product\Type\Configurable $catalogProductTypeConfigurable,
        \Magento\Framework\View\Element\Template\Context $context
		) {
        $this->_checkoutSession = $checkoutSession;
        $this->_orderFactory = $orderFactory;
        $this->_product = $product;
        $this->_catalogProductTypeConfigurable = $catalogProductTypeConfigurable;
        $this->_scopeConfig = $context->getScopeConfig();
        
        $this->_orderInrementId = $this->_checkoutSession->getLastRealOrderId();
        if ($this->_orderInrementId) {
            $this->_order = $this->_orderFactory->create()->loadByIncrementId($this->_orderInrementId);
        }

        parent::__construct($context);
    }

    /**
     * Returns the id of the last order
     *
     * @return string|boolean
     */
    public function getRealOrderId()
    {
        if ($this->_orderInrementId) {
            return $this->_orderInrementId;
        }
        return false;
    }

    /**
     * Returns the order subtotal with added tax and shipping fee
     *
     * @return float|boolean
     */
    public function getPrice()
    {
        $order = $this->_order;
        if ($order) {
            $price = $order->getSubtotalInclTax() + $order->getDiscountAmount() + $order->getShippingInclTax();
			
            return $price;
        }
        return false;
    }

    /**
     * Returns the order total discounts 
     *
     * @return float|boolean
     */
    public function getDiscountAmount()
    {
        $order = $this->_order;
        if ($order) {
            return $order->getDiscountAmount();
        }
        return false;
    }

    /**
     * Returns the order shipping fee
     *
     * @return float|boolean
     */
    public function getShippingCost()
    {
        $order = $this->_order;
        if ($order) {
            $shippingCost = $order->getShippingInclTax();
			
            return $shippingCost;
        }
        return false;
    }

    /**
     * Returns the order tax amount
     *
     * @return float|boolean
     */
    public function getTaxAmount()
    {
        $order = $this->_order;
        if ($order) {
            // $revenuefortax = $order->getSubtotalInclTax() + $order->getShippingInclTax();
            // $taxtotal = $revenuefortax / 1.24;
            // $taxAmountAlmost = $revenuefortax - $taxtotal;
            // $taxAmount = number_format($taxAmountAlmost, 2);
			
            $revenueInclTax = $order->getSubtotalInclTax() + $order->getShippingInclTax();
            $revenue = $order->getSubtotal() + $order->getShippingAmount();
            $taxAmount = $revenueInclTax - $revenue - $order->getDiscountTaxCompensationAmount();
            return $taxAmount;
        }
		
        return false;
    }

    /**
     * Returns all order items
     *
     * @return array|boolean
     */
    public function getAllOrderVisibleItems()
    {
        $order = $this->_order;
        if ($order) {
            $items = $order->getAllVisibleItems();
			
            return $items;
        }
        return false;
    }

    /**
     * Returns the id of a product given the sku
     *
     * @return integer|boolean
     */
    public function getChildId($sku)
    {
        return $this->_product->getIdBySku($sku);
    }

    /**
     * Returns the id of a parent configurable product given the id of a child simple product
     *
     * @return integer|boolean
     */

    public function getParentId($childId)
    {
        $parentByChild = $this->_catalogProductTypeConfigurable->getParentIdsByChild($childId);
        if (isset($parentByChild[0])) {
            $parentId = $parentByChild[0];
			
            return $parentId;      
        }
        return false;
    }

    /**
     * Returns the sku of a product given the id
     *
     * @return string|boolean
     */
    public function getSkuFromId($productId)
    {
        return $this->_product->load($productId)->getSku();
    }

    /**
     * Distribute cart discounts proportionally among items
     *
     * @param array $items
     * @return array
     */
    public function distributeCartDiscounts($items) 
    {
        $itemDiscounts = [];

        $total = $this->getPrice();
        $totalDiscount = $this->getDiscountAmount();
        $discountRemainder = $totalDiscount;
        // select item to receive discount remainder
        // find the first item with quantity = 1
        // if none found use the last item
        $remainderItemIndex = array_key_last($items); 
        foreach ($items as $index => $item) {
            if ($item->getQtyOrdered() == 1) {
                $remainderItemIndex = $index;
                break;
            }
        }

        // Distribute the discount among all other items 
        // except the one selected to receive the remainder
        foreach ($items as $index => $item) {
            if ($index != $remainderItemIndex) {
                $itemDiscount = round($item->getPriceInclTax() * $totalDiscount / $total, 2);
                $itemDiscounts[$index] = $itemDiscount;
                $discountRemainder -= $itemDiscount * $item->getQtyOrdered();
            }
        }
        // Apply the remainder of the discount to the selected item
        $itemDiscount = $discountRemainder / $items[$remainderItemIndex]->getQtyOrdered();
        $itemDiscounts[$remainderItemIndex] = $itemDiscount;

        return $itemDiscounts;
    }
}
