<?php
/**
 * commands_CleanGeneratedCrossitems
 * @package modules.catalog.command
 */
class commands_CleanGeneratedCrossitems extends c_ChangescriptCommand
{
	/**
	 * @return string
	 */
	public function getUsage()
	{
		return "";
	}

	/**
	 * @return string
	 */
	public function getDescription()
	{
		return "Clean all generated crossitems";
	}
	
	/**
	 * @param string[] $params
	 * @param array<String, String> $options where the option array key is the option name, the potential option value or true
	 * @see c_ChangescriptCommand::parseArgs($args)
	 */
	public function _execute($params, $options)
	{
		$this->message("== Clean generated crossitems ==");

		$batchPath = 'modules/catalog/lib/bin/batchCrossitemsCleanGenerated.php';
		$errors = array();
		$chunkSize = Framework::getConfigurationValue('modules/catalog/crossitemsCleanChunkSize', 100);
		$id = 0;
		$hasErrors = false;
		while ($id >= 0)
		{
			$result = f_util_System::execScript($batchPath, array($id, $chunkSize, 'false'));			
			$lines = array_reverse(explode(PHP_EOL, $result));
			foreach ($lines as $line)
			{
				if (preg_match('/^id:([0-9-]+)$/', $line, $matches))
				{
					$id = $matches[1];
					break;
				}
				else
				{
					$hasErrors = true;
					$this->errorMessage($line);
				}
			}
		}

		if (!$hasErrors)
		{
			$this->quitOk("Command successfully executed");
		}
		else
		{
			$this->quitError("Some errors occured");
		}
	}
}