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
 * Data Compliance plugin for Akeeba Ticket System User Data
 */
class plgDatacomplianceAts extends Joomla\CMS\Plugin\CMSPlugin
{
	protected $container;

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
	 * Performs the necessary actions for deleting a user. Returns an array of the information categories and any
	 * applicable IDs which were deleted in the process. This information is stored in the audit log. DO NOT include
	 * any personally identifiable information.
	 *
	 * This plugin takes the following actions:
	 * - Delete ATS tickets (and posts and attachments) related to the user
	 *
	 * @param   int    $userID The user ID we are asked to delete
	 * @param   string $type   The export type (user, admin, lifecycle)
	 *
	 * @return  array
	 */
	public function onDataComplianceDeleteUser(int $userID, string $type): array
	{
		$ret = [
			'ats' => [
				'tickets' => [],
			],
		];

		Log::add("Deleting user #$userID, type ‘{$type}’, Akeeba Ticket System data", Log::INFO, 'com_datacompliance');

		$container = Container::getInstance('com_ats', [], 'admin');
		/** @var \Akeeba\TicketSystem\Admin\Model\Tickets $tickets */
		$tickets = $container->factory->model('Tickets');
		$tickets->created_by($userID);

		// If we are doing a lifecycle deletion we are going to only delete PRIVATE tickets, not public tickets
		if ($type == 'lifecycle')
		{
			$tickets->public([
				'method' => 'exact',
				'value'  => 0,
			]);
		}

		// Loop through the tickets
		/** @var \Akeeba\TicketSystem\Admin\Model\Tickets $ticket */
		foreach ($tickets->getGenerator(0, 0, true) as $ticket)
		{
			if (is_null($ticket))
			{
				continue;
			}

			Log::add("Deleting ticket #{$ticket->getId()}", Log::DEBUG, 'com_datacompliance');

			$ret['ats']['tickets'][] = $ticket->getId();
			$ticket->delete();

			// TODO Delete #__ats_attempts entries
			// TODO Delete #__ats_buckets entries
			// TODO Delete #__ats_creditconsumptions entries
			// TODO Delete #__ats_credittrasations entries
		}

		// TODO Delete #__ats_users_usertags entries

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
			JText::_('PLG_DATACOMPLIANCE_ATS_ACTIONS_1'),
		];
	}


	/**
	 * Used for exporting the user information in XML format. The returned data is a SimpleXMLElement document with a
	 * data dump following the structure root > domain > item[...] > column[...].
	 *
	 * This plugin exports the following tables / models:
	 * - Tickets
	 * - Posts
	 * - Attachments
	 *
	 * @param $userID
	 *
	 * @return SimpleXMLElement
	 */
	public function onDataComplianceExportUser(int $userID): SimpleXMLElement
	{
		$export = new SimpleXMLElement("<root></root>");

		// Tickets
		$domainTickets = $export->addChild('domain');
		$domainTickets->addAttribute('name', 'ats_tickets');
		$domainTickets->addAttribute('description', 'Akeeba Ticket System tickets');

		$tickets = $this->getTickets($userID);
		$ticketIDs = [];

		array_map(function($ticket) use ($domainTickets, &$ticketIDs) {
			Export::adoptChild($domainTickets, Export::exportItemFromObject($ticket));
			$ticketIDs[] = $ticket->ats_ticket_id;
		}, $tickets);
		unset($tickets);

		// Posts
		$domainPosts = $export->addChild('domain');
		$domainPosts->addAttribute('name', 'ats_posts');
		$domainPosts->addAttribute('description', 'Akeeba Ticket System posts, linked to each ticket');

		$posts = $this->getPosts($ticketIDs);
		$postIDs = [];

		array_map(function($post) use ($domainPosts, &$postIDs) {
			Export::adoptChild($domainPosts, Export::exportItemFromObject($post));
			$postIDs[] = $post->ats_post_id;
		}, $posts);

		unset($ticketIDs);
		unset($posts);


		// Attachments
		$domainAttachments = $export->addChild('domain');
		$domainAttachments->addAttribute('name', 'ats_attachments');
		$domainAttachments->addAttribute('description', 'Akeeba Ticket System attachments, linked to each post');

		$attachments = $this->getAttachments($postIDs);

		array_map(function($attachment) use ($domainAttachments) {
			Export::adoptChild($domainAttachments, Export::exportItemFromObject($attachment));
			$postIDs[] = $attachment->ats_post_id;
		}, $attachments);

		// TODO Export #__ats_attempts entries
		// TODO Export #__ats_buckets entries
		// TODO Export #__ats_creditconsumptions entries
		// TODO Export #__ats_credittrasations entries

		// TODO Export #__ats_users_usertags entries

		return $export;
	}

	private function getTickets(int $user_id)
	{
		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('*')
			->from('#__ats_tickets')
			->where($db->qn('created_by') . ' = ' . $user_id);
		return $db->setQuery($query)->loadObjectList();
	}

	private function getPosts(array $ticketIDs)
	{
		if (empty($ticketIDs))
		{
			return [];
		}

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('*')
			->from('#__ats_posts')
			->where($db->qn('ats_ticket_id') . ' IN(' . implode(',', $ticketIDs) . ')');

		return $db->setQuery($query)->loadObjectList();
	}

	private function getAttachments(array $postIDs)
	{
		if (empty($postIDs))
		{
			return [];
		}

		$db    = $this->container->db;
		$query = $db->getQuery(true)
			->select('*')
			->from('#__ats_attachments')
			->where($db->qn('ats_post_id') . ' IN(' . implode(',', $postIDs) . ')');
		return $db->setQuery($query)->loadObjectList();
	}
}