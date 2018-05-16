<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use Akeeba\DataCompliance\Admin\Helper\Export;
use FOF30\Container\Container;
use Joomla\CMS\Log\Log;
use Joomla\CMS\User\User;
use Joomla\CMS\User\UserHelper;

defined('_JEXEC') or die;

/**
 * Data Compliance plugin for Akeeba Release System User Data
 */
class plgDatacomplianceArs extends Joomla\CMS\Plugin\CMSPlugin
{
	protected $container;

	protected $releases = [];

	protected $items = [];

	/**
	 * Constructor. Intializes the object:
	 * - Load the plugin's language strings
	 * - Get the com_datacompliance container
	 *
	 * @param   object  $subject  Passed by Joomla
	 * @param   array   $config   Passed by Joomla
	 */
	public function __construct($subject, array $config = array())
	{
		$this->autoloadLanguage = true;
		$this->container = \FOF30\Container\Container::getInstance('com_datacompliance');

		parent::__construct($subject, $config);
	}

	/**
	 * Performs the necessary actions for deleting a user. Returns an array of the infomration categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Delete ARS log entries relevant to the user
	 *
	 * @param   int    $userID The user ID we are asked to delete
	 * @param   string $type   The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): array
	{
		$ret = [
			'ars' => [
				'log'  => [],
				'dlid' => [],
			],
		];

		Log::add("Deleting user #$userID, type ‘{$type}’, Akeeba Release System data", Log::INFO, 'com_datacompliance');

		$db = $this->container->db;

		// ======================================== Log entries ========================================

		$selectQuery = $db->getQuery(true)
			->select($db->qn('id'))
			->from($db->qn('#__ars_log'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__ars_log'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);

			Log::add(sprintf("Found %u ARS log entries", count($ids)), Log::DEBUG, 'com_datacompliance');

			$ids               = empty($ids) ? [] : implode(',', $ids);
			$ret['ars']['log'] = $ids;

			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ARS log data for user #$userID: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug backtrace: {$e->getTraceAsString()}", Log::DEBUG, 'com_datacompliance');

			// No problem if deleting fails.
		}

		// ======================================== Download IDs ========================================

		$selectQuery = $db->getQuery(true)
			->select($db->qn('ars_dlidlabel_id'))
			->from($db->qn('#__ars_dlidlabels'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));

		$deleteQuery = $db->getQuery(true)
			->delete($db->qn('#__ars_dlidlabels'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));

		try
		{
			$ids = $db->setQuery($selectQuery)->loadColumn(0);

			Log::add(sprintf("Found %u ARS Download IDs", count($ids)), Log::DEBUG, 'com_datacompliance');

			$ids                = empty($ids) ? [] : implode(',', $ids);
			$ret['ars']['dlid'] = $ids;

			$db->setQuery($deleteQuery)->execute();
		}
		catch (Exception $e)
		{
			Log::add("Could not delete ARS Download ID data for user #$userID: {$e->getMessage()}", Log::ERROR, 'com_datacompliance');
			Log::add("Debug backtrace: {$e->getTraceAsString()}", Log::DEBUG, 'com_datacompliance');

			// No problem if deleting fails.
		}

		return $ret;
	}

	/**
	 * Return a list of human readable actions which will be carried out by this plugin if the user proceeds with wiping
	 * their user account.
	 *
	 * @param   int     $userID  The user ID we are asked to delete
	 * @param   string  $type    The export type (user, admin, lifecycle)
	 *
	 * @return  string[]
	 */
	public function onDataComplianceGetWipeBulletpoints(int $userID, string $type)
	{
		return [
			JText::_('PLG_DATACOMPLIANCE_ARS_ACTIONS_1'),
		];
	}

	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - #__ars_log
	 *
	 * @param $userID
	 *
	 * @return SimpleXMLElement
	 */
	public function onDataComplianceExportUser($userID): SimpleXMLElement
	{
		$export = new SimpleXMLElement("<root></root>");

		$arsContainer = \FOF30\Container\Container::getInstance('com_ars');
		$db           = $arsContainer->db;

		// #__ars_log
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ars_log');
		$domain->addAttribute('description', 'Akeeba Release System download log');

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ars_log'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));


		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			Export::adoptChild($domain, Export::exportItemFromObject($record));

			unset($record);
		}

		// #__ars_dlidlables
		$domain = $export->addChild('domain');
		$domain->addAttribute('name', 'ars_dlidlables');
		$domain->addAttribute('description', 'Akeeba Release System download IDs (main and add-on)');

		$selectQuery = $db->getQuery(true)
			->select('*')
			->from($db->qn('#__ars_dlidlabels'))
			->where($db->qn('user_id') . ' = ' . $db->q($userID));

		foreach ($db->setQuery($selectQuery)->getIterator() as $record)
		{
			Export::adoptChild($domain, Export::exportItemFromObject($record));

			unset($record);
		}

		return $export;
	}

	protected function getItemTitle($item_id)
	{
		if (!isset($this->items[$item_id]))
		{
			/** @var \Akeeba\ReleaseSystem\Site\Model\Items $item */
			$item = Container::getInstance('com_ars')->factory->model('Items');
			$item->with([]);

			try
			{
				$item->findOrFail($item_id);
				$this->items[$item_id] = [
					'title'   => $item->title,
					'release' => $item->release_id,
				];
			}
			catch (Exception $e)
			{
				$this->items[$item_id] = [
					'title'   => "(deleted item)",
					'release' => 0,
				];
			}
		}

		return $this->items[$item_id];
	}

	protected function getReleaseInfo($release_id)
	{
		if (!isset($this->releases[$release_id]))
		{
			/** @var \Akeeba\ReleaseSystem\Site\Model\Releases $release */
			$release = Container::getInstance('com_ars')->factory->model('Releases');
			$release->with(['category']);

			try
			{
				if (empty($release_id))
				{
					throw new RuntimeException("Fall back to unknown data");
				}

				$release->findOrFail($release_id);
				$this->releases[$release_id] = [
					'version'  => $release->version,
					'software' => $release->category->title,
				];
			}
			catch (Exception $e)
			{
				$this->releases[$release_id] = [
					'version'  => "(deleted version)",
					'software' => "(unknown software)",
				];
			}
		}

		return $this->releases[$release_id];
	}
}