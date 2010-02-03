<?php
class catalog_PriceScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return catalog_persistentdocument_price
	 */
	protected function initPersistentDocument()
	{
		$price = $this->getDocumentService()->getNewDocumentInstance();

		if (isset($this->attributes['shop-refid']))
		{
			$price->setShopId($this->getComputedAttribute('shop')->getId());
			unset($this->attributes['shop-refid']);
		}
		 
		// Tax code must be set before setting values.
		if (isset($this->attributes['taxCode']))
		{
			$price->setTaxCode($this->attributes['taxCode']);
			unset($this->attributes['taxCode']);
		}
		else
		{
			$price->setTaxCode(1);
		}

		if (isset($this->attributes['value']))
		{
			$price->setPriceValue($this->attributes['value']);
			unset($this->attributes['value']);
		}
		if (isset($this->attributes['oldValue']))
		{
			$price->setPriceOldValue($this->attributes['oldValue']);
			unset($this->attributes['oldValue']);
		}
		 
		return $price;
	}

	/**
	 * @return catalog_PriceService
	 */
	protected function getDocumentService()
	{
		return catalog_PriceService::getInstance();
	}
}