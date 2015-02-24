<?php

/**
 * MandrillClick
 *
 * @author lekoala
 */
class MandrillClick extends ViewableData
{
    protected $ts;
    protected $ip;
    protected $location;
    protected $ua;
    protected $url;

    public function __construct($data = array())
    {
        parent::__construct();
        if (is_array($data)) {
            foreach ($data as $k => $v) {
                $this->$k = $v;
            }
        }
    }

    public function getIpLink()
    {
        if (!$this->ip) {
            return '';
        }
        return '<a href="http://www.infosniper.net/index.php?ip_address='.$this->ip.'" target="_blank">'.$this->ip.'</a>';
    }

    /**
     * A user formatted date
     * @return string
     */
    public function getDate()
    {
        $date = new Date();
        $date->setValue($this->ts);
        return Convert::raw2xml($date->FormatFromSettings());
    }

    /**
     * A user formatted date
     * @return string
     */
    public function getDateTime()
    {
        $date = new SS_DateTime();
        $date->setValue($this->ts);
        return Convert::raw2xml($date->FormatFromSettings());
    }

    /**
     * @param Member $member
     * @return boolean
     */
    public function canView($member = null)
    {
        if (!$member && $member !== FALSE) {
            $member = Member::currentUser();
        }

        return true;
    }
}