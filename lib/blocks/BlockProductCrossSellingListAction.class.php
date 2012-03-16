<?php
/**
 * catalog_BlockProductCrossSellingListAction
 * @package modules.catalog
 */
class catalog_BlockProductCrossSellingListAction extends catalog_BlockProductlistBaseAction
{
	/**
	 * @param f_mvc_Response $response
	 * @return catalog_persistentdocument_product[]
	 */
	protected function getProductArray($request)
	{
		$shop = catalog_ShopService::getInstance()->getCurrentShop();
		$product = $this->getDocumentParameter();
		if (!($product instanceof catalog_persistentdocument_product))
		{
			return array();
		}
		return $product->getDisplayableCrossSelling($shop, $request->getParameter('relationType'));
	}
	
	/**
	 * @return string
	 */
	protected function getBlockTitle()
	{
		$product = $this->getDocumentParameter();
		if (!($product instanceof catalog_persistentdocument_product))
		{
			return null;
		}
		$productUrl = LinkHelper::getDocumentUrl($product);
		$replacements = array('productLink' => '<a class="link" href="' . $productUrl . '">' . $product->getLabelAsHtml() . '</a>');
		$key = 'm.catalog.frontoffice.cross-selling-' . $this->getRequest()->getParameter('relationType');
		return LocaleService::getInstance()->transFO($key, array('ucf'), $replacements);
	}
}