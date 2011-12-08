<?php
/**
 * @package modules.catalog
 */
class catalog_persistentdocument_preferences extends catalog_persistentdocument_preferencesbase
{
	/**
	 * Define the label of the tree node of the document.
	 * By default, this method returns the label property value.
	 * @return string
	 */
	public function getTreeNodeLabel()
	{
		return LocaleService::getInstance()->trans('m.catalog.bo.general.module-name', array('ucf'));
	}
}