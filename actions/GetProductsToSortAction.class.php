<?php
/**
 * catalog_GetProdcutsToSortAction
 * @package modules.catalog.actions
 */
class catalog_GetProductsToSortAction extends change_JSONAction
{
	/**
	 * @param change_Context $context
	 * @param change_Request $request
	 */
	public function _execute($context, $request)
	{
		$result = array();

		$shelf = DocumentHelper::getDocumentInstance($request->getParameter('shelfId'), 'modules_catalog/shelf');
		$shop = DocumentHelper::getDocumentInstance($request->getParameter('shopId'), 'modules_catalog/shop');
		$lang = $request->getParameter('lang');
		
		$result['nodes'] = catalog_CompiledproductService::getInstance()->getOrderInfosByShelfAndShopAndLang($shelf, $shop, $lang);
		
		return $this->sendJSON($result);
	}
}