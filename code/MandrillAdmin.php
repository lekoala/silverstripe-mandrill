<?php

/**
 * Mandrill admin section
 * 
 * Allow you to see messages sent through the api key used to send messages
 *
 * @package Mandrill
 * @author LeKoala <thomas@lekoala.be>
 */
class MandrillAdmin extends LeftAndMain implements PermissionProvider {

	private static $menu_title = "Mandrill";
	private static $url_segment = "mandrill";

	private static $menu_icon = "mandrill/images/icon.png";

	public function init() {
		parent::init();
//		Requirements::css("mandrill/css/mandrill.css");
//		Requirements::javascript("mandrill/javascript/mandrill.js");
	}

	private static $allowed_actions = array(
		"handlePanel",
		"SearchForm",
		"sort",
		"setdefault",
		"applytoall"
	);

	/**
	 * @return MandrillMailer
	 * @throws Exception
	 */
	public function getMailer() {
		$mailer = Email::mailer();
		if (get_class($mailer) != 'MandrillMailer') {
			throw new Exception('This class require to use MandrillMailer');
		}
		return $mailer;
	}

	/**
	 * @return Mandrill
	 */
	public function getMandrill() {
		return $this->getMailer()->getMandrill();
	}

	public function Messages() {
		//search(string key, string query, string date_from, string date_to, array tags, array senders, array api_keys, integer limit)
		$messages = $this->getMandrill()->messages->search(
		  $this->getParam('Query','*'), $this->getParam('DateFrom'), $this->getParam('DateTo'), null,null, array($this->getMandrill()->apikey), $this->getParam('Limit',100)
		);
		
		$list = new ArrayList();
		foreach ($messages as $message) {
			$m = new ArrayData(array(
				'Date' => date('Y-m-d', $message['ts']),
				'Sender' => $message['sender'],
				'Subject' => $message['subject'],
				'State' => $message['state'],
				'Clicks' => $message['clicks'],
				'Opens' => $message['opens'],
				'Recipient' => $message['email']
			));
			$list->push($m);
		}
		return $list;
	}

	public function getParam($name, $default = null) {
		$v = $this->getRequest()->postVar($name);
		if (!$v) {
			return $default;
		}
		return $v;
	}

	public function SearchForm() {
		$fields = new FieldList();
		$fields->push(new DateField('DateFrom', _t('Mandrill.DATEFROM', 'From'), $this->getParam('DateFrom', date('Y-m-d', strtotime('-30 days')))));
		$fields->push(new DateField('DateTo', _t('Mandrill.DATETO', 'To'), $this->getParam('DateTo', date('Y-m-d'))));
		$fields->push(new TextField('Query', _t('Mandrill.QUERY', 'Query'), $this->getParam('Query')));
		$fields->push(new DropdownField('Limit', _t('Mandrill.LIMIT', 'Limit'), array(
			10 => 10,
			50 => 50,
			100 => 100,
			500 => 500,
			1000 => 1000
		  ), $this->getParam('Limit', 100)));
		$actions = new FieldList();
		$actions->push(new FormAction('doSearch', _t('Mandrill.DOSEARCH', 'Search')));
		$form = new Form($this, 'SearchForm', $fields, $actions);
		$form->setFormAction($this->Link());
		$form->setFormMethod('POST');
		return $form;
	}

	/**
	 * Provides custom permissions to the Security section
	 *
	 * @return array
	 */
	public function providePermissions() {
		$title = _t("Mandrill.MENUTITLE", LeftAndMain::menu_title_for_class('Mandrill'));
		return array(
			"CMS_ACCESS_Mandrill" => array(
				'name' => _t('Mandrill.ACCESS', "Access to '{title}' section", array('title' => $title)),
				'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
				'help' => _t(
				  'Mandrill.ACCESS_HELP', 'Allow use of Mandrill admin section'
				)
			),
		);
	}

	/**
	 * A template accessor to check the ADMIN permission
	 *
	 * @return bool
	 */
	public function IsAdmin() {
		return Permission::check("ADMIN");
	}

	/**
	 * Check the permission to make sure the current user has a mandrill
	 *
	 * @return bool
	 */
	public function canView($member = null) {
		return Permission::check("CMS_ACCESS_Mandrill");
	}

}
