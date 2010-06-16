<?php
class catalog_Setup extends object_InitDataSetup
{
	public function install()
	{
		try
		{
			$scriptReader = import_ScriptReader::getInstance();
       	 	$scriptReader->executeModuleScript('catalog', 'init.xml');
		}
		catch (Exception $e)
		{
			echo "ERROR: " . $e->getMessage() . "\n";
			Framework::exception($e);
		}		
		$this->addBackGroundCompileTask();
		$this->addAlertTasks();
	}
	
	/**
	 * @return void
	 */
	private function addBackGroundCompileTask()
	{
		$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
		$task->setSystemtaskclassname('catalog_BackgroundCompileTask');
		$task->setLabel('catalog_BackgroundCompileTask');
		$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'catalog'));
	}
	
	/**
	 * @return void
	 */
	private function addAlertTasks()
	{
		$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
		$task->setSystemtaskclassname('catalog_PurgeExpiredAlertsTask');
		$task->setLabel('catalog_PurgeExpiredAlertsTask');
		$task->setMinute(0);
		$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'catalog'));
		
		$task = task_PlannedtaskService::getInstance()->getNewDocumentInstance();
		$task->setSystemtaskclassname('catalog_SendAlertsTask');
		$task->setLabel('catalog_SendAlertsTask');
		$task->setMinute(0);
		$task->save(ModuleService::getInstance()->getSystemFolderId('task', 'catalog'));
	}
}