<?php
/**
 * @package   Akeeba Connection
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

namespace Akeeba\Connection\Admin\Toolbar;

defined('_JEXEC') or die;

use JFactory;
use JText;
use JToolbar;
use JToolbarHelper;

class Toolbar extends \FOF30\Toolbar\Toolbar
{
	/**
	 * Disable rendering a toolbar.
	 *
	 * @return array
	 */
	protected function getMyViews()
	{
		return array();
	}

	public function onControlPanelsBrowse()
	{
		JToolbarHelper::title(JText::_('COM_CONNECTION_TITLE_DASHBOARD') . ' <small>' . AKCONNECTION_DATE . '</small>', 'connection');

		JToolbarHelper::preferences('com_connection');
	}
}