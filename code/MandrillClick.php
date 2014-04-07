<?php

/**
 * MandrillClick
 *
 * @author lekoala
 */
class MandrillClick extends ViewableData {

	protected $ts;
	protected $ip;
	protected $location;
	protected $ua;
	protected $url;
	
	public function __construct($data = array()) {
		parent::__construct();
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$this->$k = $v;
			}
		}
	}
	
	public function getIpLink() {
		if(!$this->ip) {
			return '';
		}
		return '<a href="http://www.infosniper.net/index.php?ip_address='.$this->ip.'" target="_blank">' . $this->ip . '</a>';
	}
	
	/**
	 * A user formatted date
	 * @return string
	 */
	public function getDate() {
		require_once Director::baseFolder() . '/' . FRAMEWORK_DIR . "/thirdparty/Zend/Date.php";
		$format = Member::currentUser()->getDateFormat();
		$date = new Zend_Date($this->ts);
		return Convert::raw2xml($date->toString($format));
	}
	
	/**
	 * A user formatted date
	 * @return string
	 */
	public function getDateTime() {
		require_once Director::baseFolder() . '/' . FRAMEWORK_DIR . "/thirdparty/Zend/Date.php";
		$format = Member::currentUser()->getDateFormat();
		$format2 = Member::currentUser()->getTimeFormat();
		$date = new Zend_Date($this->ts);
		return Convert::raw2xml($date->toString($format . ' ' . $format2));
	}
	
	/**
	 * @param Member $member
	 * @return boolean
	 */
	public function canView($member = null) {
		if(!$member && $member !== FALSE) {
			$member = Member::currentUser();
		}
		
		return true;
	}
}
