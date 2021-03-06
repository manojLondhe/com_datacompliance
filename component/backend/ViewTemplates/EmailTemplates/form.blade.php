<?php
/**
 * @package   Akeeba Data Compliance
 * @copyright Copyright (c)2018 Nicholas K. Dionysopoulos / Akeeba Ltd
 * @license   GNU General Public License version 3, or later
 */

use FOF30\Utils\FEFHelper\BrowseView;

defined('_JEXEC') or die();

/** @var  FOF30\View\DataView\Html  $this */

?>
@extends('admin:com_datacompliance/Common/edit')

@section('edit-form-body')
	<div class="akeeba-form-group">
		<label for="key">
			@fieldtitle('key')
		</label>
		{{ BrowseView::genericSelect('key', \Akeeba\DataCompliance\Admin\Helper\Email::getEmailKeys(true), $this->getItem()->key, ['fof.autosubmit' => false, 'translate' => false]) }}
	</div>

	<div class="akeeba-form-group">
		<label for="language">
			@fieldtitle('language')
		</label>
		{{ BrowseView::genericSelect('language', \FOF30\Utils\SelectOptions::getOptions('languages', ['none' => 'COM_DATACOMPLIANCE_EMAILTEMPLATES_FIELD_LANGUAGE_ALL']), $this->getItem()->language, ['fof.autosubmit' => false, 'translate' => false]) }}
	</div>

	<div class="akeeba-form-group">
		<label for="enabled">
			@lang('JPUBLISHED')
		</label>
		@jhtml('FEFHelper.select.booleanswitch', 'enabled', $this->getItem()->enabled)
	</div>

	<div class="akeeba-form-group">
		<label for="subject">
			@fieldtitle('subject')
		</label>
		<input type="text" name="subject" id="subject" value="{{{ $this->getItem()->subject }}}" />
	</div>

	<div class="akeeba-form-group">
		<label for="body">
			@fieldtitle('body')
		</label>
		<div class="akeeba-nofef">
			@jhtml('FEFHelper.edit.editor', 'body', $this->getItem()->body)
		</div>
	</div>
@stop
