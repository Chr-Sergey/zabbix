<?php
/*
** Zabbix
** Copyright (C) 2001-2014 Zabbix SIA
**
** This program is free software; you can redistribute it and/or modify
** it under the terms of the GNU General Public License as published by
** the Free Software Foundation; either version 2 of the License, or
** (at your option) any later version.
**
** This program is distributed in the hope that it will be useful,
** but WITHOUT ANY WARRANTY; without even the implied warranty of
** MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE. See the
** GNU General Public License for more details.
**
** You should have received a copy of the GNU General Public License
** along with this program; if not, write to the Free Software
** Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301, USA.
**/

if ($data['uncheck']) {
	uncheckTableRows();
}

$proxyWidget = new CWidget();

// create new proxy button
$createForm = new CForm('get');
$createForm->cleanItems();
$createForm->addItem(new CRedirectButton(_('Create proxy'), 'zabbix.php?action=proxy.formcreate'));
$proxyWidget->addPageHeader(_('CONFIGURATION OF PROXIES'), $createForm);
$proxyWidget->addHeader(_('Proxies'));
$proxyWidget->addHeaderRowNumber();

// create form
$proxyForm = new CForm('get');
$proxyForm->setName('proxyForm');

// create table
$proxyTable = new CTableInfo(_('No proxies found.'));
$proxyTable->setHeader(array(
	new CCheckBox('all_hosts', null, "checkAll('".$proxyForm->getName()."', 'all_hosts', 'proxyids');"),
	make_sorting_header(_('Name'), 'host', $data['sort'], $data['sortorder']),
	_('Mode'),
	_('Last seen (age)'),
	_('Host count'),
	_('Item count'),
	_('Required performance (vps)'),
	_('Hosts')
));

foreach ($data['proxies'] as $proxy) {
	$hosts = array();
	$i = 0;

	foreach ($proxy['hosts'] as $host) {
		switch ($host['status']) {
			case HOST_STATUS_MONITORED:
				$style = 'off';
				break;
			case HOST_STATUS_TEMPLATE:
				$style = 'unknown';
				break;
			default:
				$style = 'on';
		}

		if ($hosts) {
			$hosts[] = ', ';
		}

		$hosts[] = new CLink($host['name'], 'hosts.php?form=update&hostid='.$host['hostid'], $style);

		if (++$i == $data['config']['max_in_table']) {
			$hosts[] = ' &hellip;';

			break;
		}
	}

	$proxyTable->addRow(array(
		new CCheckBox('proxyids['.$proxy['proxyid'].']', null, null, $proxy['proxyid']),
		new CLink($proxy['host'], 'zabbix.php?action=proxy.formedit&proxyid='.$proxy['proxyid']),
		$proxy['status'] == HOST_STATUS_PROXY_ACTIVE ? _('Active') : _('Passive'),
		$proxy['lastaccess'] == 0 ? '-' : zbx_date2age($proxy['lastaccess']),
		count($proxy['hosts']),
		array_key_exists('item_count', $proxy) ? $proxy['item_count'] : 0,
		array_key_exists('perf', $proxy) ? $proxy['perf'] : '-',
		new CCol($hosts ? $hosts : '-', 'wraptext')
	));
}

// create go buttons
$goComboBox = new CComboBox('action');

$goOption = new CComboItem('proxy.massenable', _('Enable selected'));
$goOption->setAttribute('confirm', _('Enable hosts monitored by selected proxies?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('proxy.massdisable', _('Disable selected'));
$goOption->setAttribute('confirm', _('Disable hosts monitored by selected proxies?'));
$goComboBox->addItem($goOption);

$goOption = new CComboItem('proxy.massdelete', _('Delete selected'));
$goOption->setAttribute('confirm', _('Delete selected proxies?'));
$goComboBox->addItem($goOption);

$goButton = new CSubmit('goButton', _('Go').' (0)');
$goButton->setAttribute('id', 'goButton');
$this->addPostJS('chkbxRange.pageGoName = "proxyids";');

// append table to form
$proxyForm->addItem(array($data['paging'], $proxyTable, $data['paging'], get_table_header(array($goComboBox, $goButton))));

// append form to widget
$proxyWidget->addItem($proxyForm);

$proxyWidget->show();
