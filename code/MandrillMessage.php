<?php
/*
 * Mandrill message to be displayed in a gridfield
 * 
 * @link https://mandrillapp.com/api/docs/messages.JSON.html#method=search
 * @package Mandrill
 * @author LeKoala <thomas@lekoala.be>
 */
class MandrillMessage extends ViewableData {
	
	protected $ts;
	protected $_id;
	protected $sender;
	protected $template;
	protected $subject;
	protected $email;
	protected $tags = array();
	protected $opens;
	protected $opens_detail = array();
	protected $clicks;
	protected $clicks_detail = array();
	protected $state;
	protected $metadata;
	//through info api
	protected $smtp_events;
	//through content api
	protected $from_email;
	protected $from_name;
	protected $to;
	protected $headers;
	protected $text;
	protected $html;
	protected $attachements = array();
	
	public function __construct($data = array()) {
		parent::__construct();
		if(is_array($data)) {
			foreach($data as $k => $v) {
				$this->$k = $v;
			}
		}
	}
	
	/**
	 * @return \ArrayList
	 */
	public function getTagsList() {
		$tags = $this->tags;
		$list = new ArrayList();
		foreach($tags as $t) {
			$list->push(new ArrayObject(array(
				'Title' => $t
			)));
		}
		return $list;
	}
	
	/**
	 * @return \ArrayList
	 */
	public function getAttachmentsList() {
		$attachments = $this->attachments;
		$list = new ArrayList();
		foreach($attachments as $attachment) {
			$list->push(new ArrayObject($attachment));
		}
		return $list;
	}
	
	/**
	 * @return \ArrayList
	 */
	public function getClicksList() {
		$clicks = $this->clicks_detail;
		$list = new ArrayList();
		foreach($clicks as $click) {
			$list->push(new MandrillClick($click));
		}
		return $list;
	}
	
	/**
	 * @return \ArrayList
	 */
	public function getOpensList() {
		$opens = $this->opens_detail;
		$list = new ArrayList();
		
		foreach($opens as $open) {
			$list->push(new MandrillClick($open));
		}
		return $list;
	}
	
	public function getLink($action = null) {
		return Controller::join_links(
			'admin/mandrill/view',
			"$this->_id",
			'/', // trailing slash needed if $action is null!
			"$action"
		);
	}
	
	/**
	 * A user formatted date
	 * @return string
	 */
	public function getDate() {
		$format = Member::currentUser()->getDateFormat();
		$date = new Zend_Date($this->ts);
		return Convert::raw2xml($date->toString($format));
	}
	
	/**
	 * A user formatted date
	 * @return string
	 */
	public function getDateTime() {
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

