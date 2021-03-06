<?php
/**
 * catalog_ProductdeclinationScriptDocumentElement
 * @package modules.catalog.persistentdocument.import
 */
class catalog_ProductdeclinationScriptDocumentElement extends import_ScriptDocumentElement
{
	/**
	 * @return catalog_persistentdocument_productdeclination
	 */
	protected function initPersistentDocument()
	{
		return catalog_ProductdeclinationService::getInstance()->getNewDocumentInstance();
	}
	
	/**
	 * @return f_persistentdocument_PersistentDocumentModel
	 */
	protected function getDocumentModel()
	{
		return f_persistentdocument_PersistentDocumentModel::getInstanceFromDocumentModelName('modules_catalog/productdeclination');
	}
	
	/**
	 * @return array
	 */
	protected function getDocumentProperties()
	{
		$properties = parent::getDocumentProperties();
		if (isset($properties['attributes']))
		{
			$attributesToSerialize = array();
			$attributes = explode(',', $this->replaceRefIdInString($properties['attributes']));
			foreach ($attributes as $attribute)
			{
				list ($key, $value) = explode(':', $attribute);
				$attributesToSerialize[$key] = $value;
			}
			$properties['attributesJSON'] = json_encode($attributesToSerialize);
			unset($properties['attributes']);
		}
		return $properties;
	}
	
	/**
	 * @param import_ScriptExecuteElement $scriptExecute
	 * @param array $attr
	 */
	public function compile($scriptExecute, $attr = null)
	{
		catalog_CompiledproductService::getInstance()->generateForProduct($this->getPersistentDocument());
	}
}