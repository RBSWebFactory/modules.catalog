<?php
/**
 * catalog_StockService
 * @package modules.catalog
 */
class catalog_StockService extends BaseService
{
	const LEVEL_AVAILABLE = 'A';
	const LEVEL_FEW_IN_STOCK = 'F';
	const LEVEL_UNAVAILABLE = 'U';

	const STOCK_ALERT_NOTIFICATION_CODENAME = 'modules_catalog/stockalert';

	/**
	 * Singleton
	 * @var catalog_StockService
	 */
	private static $instance = null;

	/**
	 * @return catalog_StockService
	 */
	public static function getInstance()
	{
		if (is_null(self::$instance))
		{
			self::$instance = self::getServiceClassInstance(get_class());
		}
		return self::$instance;
	}
	
	/**
	 * @param catalog_persistentdocument_product $document
	 * @return catalog_StockableDocument
	 */
	public function getStockableDocument($document)
	{
		if ($document instanceof catalog_StockableDocument)
		{
			return $document;
		}	
		return null;
	}
	
	/**
	 * @param catalog_persistentdocument_product $document
	 * @param Double $quantity
	 * @param catalog_persistentdocument_shop $shop
	 * @return Boolean
	 */
	public function isAvailable($document, $quantity = 1, $shop = null)
	{
		$stDoc = $this->getStockableDocument($document);
		if ($stDoc !== null)
		{
			$stock = $stDoc->getCurrentStockQuantity();
			return ($stock === null || ($stock - $quantity) >= 0);
		}
		return true;
	}
	
	
	
	/**	
	 * @param order_CartInfo $cart
	 * @param order_CartLineInfo $cartLine
	 * @param array $globalProductsArray
	 */
	public function buildCartProductList($cart, $cartLine, &$globalProductsArray)
	{
		
		$product = $cartLine->getProduct();
		$this->buildProductList($product, $cartLine->getQuantity(), $globalProductsArray);
	}
	
	/**
	 * 
	 * @param catalog_persistentdocument_product $product
	 * @param integer $quantity
	 * @param array $globalProductsArray
	 */
	protected function buildProductList($product, $quantity, &$globalProductsArray)
	{	
		$productId = $product->getId();
		if ($product instanceof catalog_BundleProduct)
		{
			foreach ($product->getBundledProducts() as $bundledProduct)
			{
				$product = $bundledProduct->getProduct();
				$productId = $product->getId();
				$productQty = $bundledProduct->getQuantity() * $quantity;
				if (! isset($globalProductsArray[$productId]))
				{
					$globalProductsArray[$productId] = array($product, $productQty);
				}
				else
				{
					$globalProductsArray[$productId][1] += $productQty;
				}
			}
		}
		else
		{
			$productQty = $quantity;
			if (! isset($globalProductsArray[$productId]))
			{
				$globalProductsArray[$productId] = array($product, $productQty);
			}
			else
			{
				$globalProductsArray[$productId][1] += $productQty;
			}
		}		
	}
	
	/**
	 * @param array $productArray
	 * @param order_CartInfo $cart
	 * @return array
	 */
	public function validCartQuantities($productArray, $cart)
	{
		$result = array();
		if ($cart->getShop()->getAllowOrderOutOfStock())
		{
			return $result;
		}
		foreach ($productArray as $productInfo) 
		{
			$stDoc = $this->getStockableDocument($productInfo[0]);
			if ($stDoc !== null)
			{
				$stock = $stDoc->getCurrentStockQuantity();
				if ($stock !== null && ($stock - $productInfo[1]) < 0)
				{
					$result[] = $productInfo;
				}
			}
		}
		return $result;
	}
	
	/**
	 * @param f_persistentdocument_PersistentDocument $document
	 * @param catalog_persistentdocument_shop $shop
	 * @return string | null
	 */
	public function getAvailability($document, $shop = null)
	{
		$stDoc = $this->getStockableDocument($document);
		if ($stDoc !== null)
		{
			return catalog_AvailabilityStrategy::getStrategy()->getAvailability($stDoc->getCurrentStockLevel());
		}
		return catalog_AvailabilityStrategy::getStrategy()->getAvailability(null);
	}
		
	/**
	 * @return catalog_StockableDocument
	 */
	public function getOutOfStockProducts()
	{
		return catalog_productService::getInstance()->createQuery()->add(Restrictions::le('stockQuantity', 0))->find();
	}
	
	/**
	 * @param order_CartInfo $cart
	 * @param order_persistentdocument_order $order
	 */
	public function orderInitializedFromCart($cart, $order)
	{
		
	}

	/**
	 * @param order_persistentdocument_order $order
	 * @param string $oldStatus
	 */
	public function orderStatusChanged($order, $oldStatus)
	{
		if (Framework::isInfoEnabled())
		{
			Framework::info(__METHOD__ . ' ' . $order->getId() .  ' ' . $oldStatus . ' -> ' . $order->getOrderStatus());
		}
		switch ($order->getOrderStatus()) 
		{
			case order_OrderService::IN_PROGRESS:
				try
				{
					$this->getTransactionManager()->beginTransaction();
					$productIdsToCompile = array();
					$globalProductsArray = array();
					
					foreach ($order->getLineArray() as $line) 
					{
						if ($line instanceof order_persistentdocument_orderline)
						{
							$product = $line->getProduct();
							if ($product !== null && $line->getQuantity() > 0)
							{
								$productIdsToCompile[$product->getId()] = true;
								$properties = $line->getGlobalPropertyArray();
								$product->getDocumentService()->updateProductFromCartProperties($product, $properties);
								$this->buildProductList($product, $line->getQuantity(), $globalProductsArray);
							}
						}
					}
					
					foreach ($globalProductsArray as $productInfo) 
					{
						list($product, $quantity) = $productInfo;
						$productId = $product->getId();
						$productIdsToCompile[$productId] = true;
						$this->increaseQuantity($product, -$quantity, $order);
					}
					
					if (count($productIdsToCompile))
					{
						catalog_ProductService::getInstance()->setNeedCompile(array_keys($productIdsToCompile));
					}				
					$this->getTransactionManager()->commit();
				}
				catch (Exception $e)
				{
					$this->getTransactionManager()->rollBack($e);
				}
				break;
			case order_OrderService::CANCELED:
				if ($oldStatus == order_OrderService::IN_PROGRESS || $oldStatus == order_OrderService::COMPLETE)
				{
					try
					{
						$this->getTransactionManager()->beginTransaction();
						$productIdsToCompile = array();
						$globalProductsArray = array();
						
						foreach ($order->getLineArray() as $line) 
						{
							if ($line instanceof order_persistentdocument_orderline)
							{
								$product = $line->getProduct();
								if ($product !== null && $line->getQuantity() > 0)
								{
									$productIdsToCompile[$product->getId()] = true;
									$properties = $line->getGlobalPropertyArray();
									$product->getDocumentService()->updateProductFromCartProperties($product, $properties);
									$this->buildProductList($product, $line->getQuantity(), $globalProductsArray);

								}
							}
						}	

						foreach ($globalProductsArray as $productInfo) 
						{
							list($product, $quantity) = $productInfo;
							$productId = $product->getId();
							$productIdsToCompile[$productId] = $quantity;
							$this->increaseQuantity($product, $quantity, $order);
						}
						
						if (count($productIdsToCompile))
						{
							catalog_ProductService::getInstance()->setNeedCompile(array_keys($productIdsToCompile));
						}
					
						$this->getTransactionManager()->commit();
					}
					catch (Exception $e)
					{
						$this->getTransactionManager()->rollBack($e);
					}
				}
				break;
		}		
	}
	
	/**
	 * @param catalog_persistentdocument_product $document
	 * @param double $nb
	 * @param order_persistentdocument_order $order
	 * @return double new quantity
	 */
	protected function increaseQuantity($document, $nb, $order)
	{
		$result = null;
		$stDoc = $this->getStockableDocument($document);
		if ($stDoc !== null)
		{
			$result = $stDoc->addStockQuantity($nb);
		}
		return $result; 
	}

	/**
	 * @param catalog_persistentdocument_product $document
	 */
	public function handleStockAlert($document)
	{
		$stDoc = $this->getStockableDocument($document);
		if ($stDoc !== null)
		{
			if ($stDoc->mustSendStockAlert())
			{
				$recipients = ModuleService::getInstance()->getPreferenceValue('catalog', 'stockAlertNotificationUser');
				if ($recipients !== null && count($recipients) > 0)
				{
					$this->sendStockAlertNotification($recipients, $document);
				}
			}			
		}
	}
	
	/**
	 * @param users_persistentdocument_backenduser[] $recipients
	 * @param catalog_persistentdocument_product $document
	 * @return boolean
	 */
	protected function sendStockAlertNotification($users, $document)
	{
		$emailAddressArray = array();
		foreach ($users as $user)
		{
			$emailAddressArray[] = $user->getEmail();
		}
		$recipients = new mail_MessageRecipients();
		$recipients->setTo($emailAddressArray);

		$parameters = array(
			'label' => $document->getLabel(),
			'threshold' => $this->getAlertThreshold($document)
		);
		$ns = notification_NotificationService::getInstance();
		return $ns->send($ns->getByCodeName(self::STOCK_ALERT_NOTIFICATION_CODENAME), 
			$recipients, $parameters, 'catalog');
	}

	/**
	 * @param catalog_persistentdocument_product $document
	 * @return Double
	 */
	protected function getAlertThreshold($document)
	{
		$threshold = $document->getStockAlertThreshold();
		if ($threshold === null)
		{
			return ModuleService::getInstance()->getPreferenceValue('catalog', 'stockAlertThreshold');
		}
		return $threshold;
	}
}
