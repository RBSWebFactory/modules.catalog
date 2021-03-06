<?php
/**
 * Class where to put your custom methods for document catalog_persistentdocument_kit
 * @package modules.catalog.persistentdocument
 */
class catalog_persistentdocument_kit extends catalog_persistentdocument_kitbase 
	implements catalog_StockableDocument, catalog_BundleProduct
{
	/**
	 * @return String
	 */
	public function getDetailBlockName()
	{
		return 'kitproduct';
	}
	
	//BO Functions
	
	/**
	 * @var integer
	 */
	private $newKitItemQtt = 1;
	
	/**
	 * @var string for example "4526,785445,2355"
	 */
	private $newKitItemProductIds = null;

	/**
	 * @var catalog_persistentdocument_kititem[]
	 */
	private $kitItemsToDelete;
	
	/**
	 * @return integer
	 */
	public function getNewKitItemQtt()
	{
		return $this->newKitItemQtt;
	}
	
	/**
	 * @param integer $newKitItemQtt
	 */
	public function setNewKitItemQtt($newKitItemQtt)
	{
		$this->newKitItemQtt = $newKitItemQtt;
	}

	/**
	 * @return string
	 */
	public function getNewKitItemProductIds()
	{
		return $this->newKitItemProductIds;
	}
	
	/**
	 * @param string $newKitItemProducts
	 */
	public function setNewKitItemProductIds($newKitItemProductIds)
	{
		$this->newKitItemProductIds = $newKitItemProductIds;
		$this->setModificationdate(null);
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocument[]
	 */
	public function getNewKitItemProductsDocument()
	{
		$result = array();
		if (f_util_StringUtils::isNotEmpty($this->newKitItemProductIds))
		{
			$ids = explode(',', $this->newKitItemProductIds);
			foreach ($ids as $id) 
			{
				$result[] = DocumentHelper::getDocumentInstance($id);
			}
		}
		return $result;
	}
	
	/**
	 * @return string JSON
	 */
	public function getKititemsJSON()
	{
		$result = array();
		foreach ($this->getKititemArray() as $kitItem) 
		{
			catalog_KititemService::getInstance()->transformToArray($kitItem, $result);
		}
		return JsonService::getInstance()->encode($result);
	}
	
	/**
	 * @param string $json
	 */
	public function setKititemsJSON($json)
	{
		if (!is_array($this->kitItemsToDelete))
		{
			$this->kitItemsToDelete = array();
		}
		
		foreach ($this->getKititemArray() as $kitItem)
		{
			$this->kitItemsToDelete[$kitItem->getId()] = $kitItem;
		}
		
		$this->removeAllKititem();
		$result = JsonService::getInstance()->decode($json);
		foreach ($result as $datas) 
		{
			$qtt = intval($datas['qtt']);	
			if ($qtt > 0)
			{
				$id = intval($datas['id']);				
				$kitItem = $this->kitItemsToDelete[$id];
				$kitItem->setQuantity($qtt);
				$this->addKititem($kitItem);
				unset($this->kitItemsToDelete[$id]);
			}
		}
		
		$this->setModificationdate(null);
	}	
	
	/**
	 * @return catalog_persistentdocument_kititem[]
	 */
	public function getKitItemsToDelete()
	{
		if (!is_array($this->kitItemsToDelete))
		{
			return array();
		}
		$result = $this->kitItemsToDelete;
		$this->kitItemsToDelete = null;
		return $result;
	}
		
	//TEMPLATING FUNCTION

	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @return media_persistentdocument_media[]
	 */
	public function getAllVisuals($shop)
	{
		$result = parent::getAllVisuals($shop);
		foreach ($this->getKititemArray() as $kitItem)
		{
			$media = $kitItem->getProduct()->getVisual();
			if ($media !== null)
			{
				$result[] = $media;
			}
		}
		return array_unique($result);
	}
	
	/**
	 * @return string HTMLFragment
	 */
	public function getOrderLabelAsHtml()
	{
		$html = array();
		$customParams = $this->getDocumentService()->getCustomItemsInfo($this);
		if (count($customParams) > 0)
		{
			$parameters = array('catalogParam' => array('customitems' => $customParams));
		}
		else
		{
			$parameters = array();
		}
		
		$url = LinkHelper::getDocumentUrl($this, null, $parameters);
		$html[] = '<a class="link" href="'.$url.'">' . $this->getLabelAsHtml() . '</a>';
		$html[] = '<br />';
		$html[] = '<ol>';
		foreach ($this->getKititemArray() as $kititem) 
		{
			$html[] = '<li>';
			$url = LinkHelper::getDocumentUrl($kititem, null, $parameters);
			if ($url)
			{
				$html[] = '<a class="link" href="'.$url.'">' . $kititem->getTitleAsHtml() . '</a>';
			}
			else
			{
				$html[] = $kititem->getTitleAsHtml();
			}
			$html[] = '</li>';
		}
		$html[] = '</ol>';
		return implode('', $html);
	}
	
	/**
	 * @return string
	 */
	public function getCartLineKey()
	{
		$result  = array($this->getId());
		foreach ($this->getKititemArray() as $kitItem) 
		{
			$kitProduct = $kitItem->getCurrentProduct();
			$result[] = $kitProduct != null ? $kitProduct->getCartLineKey() : $kitItem->getCurrentKey();
		}
		$key = implode(',', $result);
		return $key;
	}
	
	/**
	 * @var catalog_persistentdocument_price
	 */
	private $prices = array();
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param catalog_persistentdocument_billingarea $billingArea
	 * @param customer_persistentdocument_customer $customer nullable
	 * @param Double $quantity
	 * @return catalog_persistentdocument_price
	 */
	public function getPrice($shop, $billingArea, $customer, $quantity = 1)
	{
		$key = $shop->getId() . '.' . ($customer ? $customer->getId() : '0') . '.' . $quantity;
		if (!isset($this->prices[$key]))
		{
			$targetIds = catalog_PriceService::getInstance()->convertCustomerToTargetIds($customer);
			$this->prices[$key] = $this->getDocumentService()->getPriceByTargetIds($this, $shop, $billingArea, $targetIds, $quantity);
		}
		return $this->prices[$key];
	}
	
	/**
	 * @var catalog_persistentdocument_price
	 */
	private $itemsPrices = array();
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param catalog_persistentdocument_billingarea $billingArea
	 * @param customer_persistentdocument_customer $customer nullable
	 * @param Double $quantity
	 * @return catalog_persistentdocument_price
	 */
	public function getItemsPrice($shop, $billingArea, $customer, $quantity = 1)
	{
		$key = $shop->getId() . '.' . ($customer ? $customer->getId() : '0') . '.' . $quantity;
		if (!isset($this->itemsPrices[$key]))
		{
			$targetIds = catalog_PriceService::getInstance()->convertCustomerToTargetIds($customer);
			$this->itemsPrices[$key] = $this->getDocumentService()->getItemsPriceByTargetIds($this, $shop, $billingArea, $targetIds, $quantity);
		}
		return $this->itemsPrices[$key];
	}
	
	/**
	 * @param catalog_persistentdocument_shop $shop
	 * @param catalog_persistentdocument_billingarea $billingArea
	 * @param customer_persistentdocument_customer $customer nullable
	 * @param Double $quantity
	 * @return catalog_persistentdocument_price
	 */
	public function getPriceDifference($shop, $billingArea, $customer, $quantity = 1)
	{
		$price1 = $this->getItemsPrice($shop, $billingArea, $customer, $quantity);
		$price2 = $this->getPrice($shop, $billingArea, $customer, $quantity);
		return catalog_PriceService::getInstance()->getPriceDifference($price1, $price2);
	}
	
	/**
	 * catalog_BundledProduct[]
	 */
	public function getBundledProducts()
	{
		$bundledProducts = array();
		foreach ($this->getKititemArray() as $kitItem)
		{
			$kitProduct = $kitItem->getDefaultProduct();
			$bundledProducts[] = new catalog_BundledProductImpl($kitProduct, $kitItem->getQuantity());
		}
		return $bundledProducts;
	}
}