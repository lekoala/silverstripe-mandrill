<?php

/**
 * Mandrill admin section
 * 
 * Allow you to see messages sent through the api key used to send messages
 *
 * @package Mandrill
 * @author LeKoala <thomas@lekoala.be>
 */
class MandrillAdmin extends LeftAndMain implements PermissionProvider
{
    const MESSAGE_TAG           = 'message';
    const MESSAGE_CACHE_MINUTES = 5;
    const WEBHOOK_TAG           = 'webhook';
    const WEBHOOK_CACHE_MINUTES = 1440; // 1 day

    private static $menu_title      = "Mandrill";
    private static $url_segment     = "mandrill";
    private static $menu_icon       = "mandrill/images/icon.png";
    private static $url_rule        = '/$Action/$ID';
    private static $allowed_actions = array(
        "view",
        'view_message',
        "ListForm",
        "SearchForm",
        "doSearch",
        "InstallHookForm",
        "doInstallHook",
        "UninstallHookForm",
        "doUninstallHook"
    );
    private static $cache_enabled   = true;

    /**
     * @var MandrilMessage
     */
    protected $currentMessage;

    /**
     * @var string
     */
    protected $view = '_Content';

    public function init()
    {
        parent::init();
    }

    public function Content()
    {
        return $this->renderWith($this->getTemplatesWithSuffix($this->view));
    }

    public function index($request)
    {
        if (!MandrillMailer::getInstance()) {
            $this->view = '_NotConfigured';
        }
        return parent::index($request);
    }

    /**
     * @return MandrillMailer
     * @throws Exception
     */
    public function getMailer()
    {
        $mailer = Email::mailer();
        if (get_class($mailer) != 'MandrillMailer') {
            throw new Exception('This class require to use MandrillMailer');
        }
        return $mailer;
    }

    /**
     * @return Mandrill
     */
    public function getMandrill()
    {
        return $this->getMailer()->getMandrill();
    }

    /**
     * @return MandrillMessage
     */
    public function CurrentMessage()
    {
        return $this->currentMessage;
    }

    public function view($request)
    {
        $id = $this->getRequest()->param('ID');
        if (!$id) {
            return $this->httpError(404);
        }
        $this->currentMessage = $this->MessageInfo($id);
        return $this->getResponseNegotiator()->respond($request);
    }

    public function view_message()
    {
        $id = $this->getRequest()->param('ID');
        if (!$id) {
            return $this->httpError(404);
        }
        $this->currentMessage = $this->MessageInfo($id);
        echo $this->currentMessage->html;
        die();
    }

    /**
     * Returns a GridField of messages
     * @return CMSForm
     */
    public function ListForm()
    {
        $fields          = new FieldList();
        $gridFieldConfig = GridFieldConfig::create()->addComponents(
            new GridFieldToolbarHeader(), new GridFieldSortableHeader(),
            new GridFieldDataColumns(), new GridFieldFooter()
        );
        $gridField       = new GridField('SearchResults',
            _t('MandrillAdmin.SearchResults', 'Search Results'),
            $this->Messages(), $gridFieldConfig);
        $columns         = $gridField->getConfig()->getComponentByType('GridFieldDataColumns');
        $columns->setDisplayFields(array(
            'date' => _t('MandrillAdmin.MessageDate', 'Date'),
            'state' => _t('MandrillAdmin.MessageStatus', 'Status'),
            'sender' => _t('MandrillAdmin.MessageSender', 'Sender'),
            'email' => _t('MandrillAdmin.MessageEmail', 'Email'),
            'subject' => _t('MandrillAdmin.MessageSubject', 'Subject'),
            'opens' => _t('MandrillAdmin.MessageOpens', 'Opens'),
            'clicks' => _t('MandrillAdmin.MessageClicks', 'Clicks'),
        ));
        $columns->setFieldFormatting(array(
            'subject' => function($value, &$item) {
                return sprintf(
                    '<a href="%s" class="cms-panel-link" data-pjax-target="Content">%s</a>',
                    Convert::raw2xml($item->Link), $value
                );
            },
            'state' => function($value, &$item) {
                $color = MandrillMessage::getColorForState($value);
                return sprintf('<span style="color:%s">%s</span>', $color,
                    $value);
            }
        ));
        $gridField->addExtraClass('all-messages-gridfield');
        $fields->push($gridField);

        $actions = new FieldList();
        $form    = CMSForm::create(
                $this, "ListForm", $fields, $actions
            )->setHTMLID('Form_ListForm');
        $form->setResponseNegotiator($this->getResponseNegotiator());

        return $form;
    }

    /**
     * @return Zend_Cache_Frontend
     */
    public function getCache()
    {
        return SS_Cache::factory('MandrillAdmin');
    }

    /**
     * @return boolean
     */
    public function getCacheEnabled()
    {
        $v = $this->config()->cache_enabled;
        if ($v === null) {
            $v = self::$cache_enabled;
        }
        return $v;
    }

    /**
     * List of MandrillMessage
     *
     * Messages are cached to avoid hammering the api
     *
     * @return \ArrayList
     */
    public function Messages()
    {
        $data = $this->getRequest()->postVars();
        if (isset($data['SecurityID'])) {
            unset($data['SecurityID']);
        }
        $cache_enabled = $this->getCacheEnabled();
        if ($cache_enabled) {
            $cache        = $this->getCache();
            $cache_key    = md5(serialize($data));
            $cache_result = $cache->load($cache_key);
        }
        if ($cache_enabled && $cache_result) {
            $list = unserialize($cache_result);
        } else {
            $defaultQuery = '*';
            // If we have a subaccount defined, we need to restrict the query to this subaccount
            if($subaccount = MandrillMailer::getSubaccount()) {
                $defaultQuery = 'subaccount:' . $subaccount;
            }

            //search(string key, string query, string date_from, string date_to, array tags, array senders, array api_keys, integer limit)
            $messages = $this->getMandrill()->messages->search(
                $this->getParam('Query', $defaultQuery),
                $this->getParam('DateFrom'),
                $this->getParam('DateTo'),
                null,
                null,
                array($this->getMandrill()->apikey),
                $this->getParam('Limit', 100)
            );

            $list = new ArrayList();
            foreach ($messages as $message) {
                $m = new MandrillMessage($message);
                $list->push($m);
            }
            //5 minutes cache
            if ($cache_enabled) {
                $cache->save(serialize($list), $cache_key,
                    array(self::MESSAGE_TAG), 60 * self::MESSAGE_CACHE_MINUTES);
            }
        }
        return $list;
    }

    /**
     * Get the detail of one message
     *
     * @param int $id
     * @return MandrillMessage
     */
    public function MessageInfo($id)
    {
        $cache_enabled = $this->getCacheEnabled();
        if ($cache_enabled) {
            $cache        = $this->getCache();
            $cache_key    = 'message_'.$id;
            $cache_result = $cache->load($cache_key);
        }
        if ($cache_enabled && $cache_result) {
            $message = unserialize($cache_result);
        } else {
            try {
                $info    = $this->getMandrill()->messages->info($id);
                $content = $this->MessageContent($id);
                $info    = array_merge($content, $info);
                $message = new MandrillMessage($info);
                //the detail is not going to change very often
                if ($cache_enabled) {
                    $cache->save(serialize($message), $cache_key,
                        array('message'), 60 * 60);
                }
            } catch (Exception $ex) {
                $message = new MandrillMessage();
                $this->getCache()->clean('matchingTag', array(self::MESSAGE_TAG));
                SS_Log::log(get_class($ex).': '.$ex->getMessage());
            }
        }
        return $message;
    }

    /**
     * Get the contnet of one message
     *
     * @param int $id
     * @return array
     */
    public function MessageContent($id)
    {
        $cache_enabled = $this->getCacheEnabled();
        if ($cache_enabled) {
            $cache        = $this->getCache();
            $cache_key    = 'content_'.$id;
            $cache_result = $cache->load($cache_key);
        }
        if ($cache_enabled && $cache_result) {
            $content = unserialize($cache_result);
        } else {
            try {
                $content = $this->getMandrill()->messages->content($id);
            } catch (Mandrill_Unknown_Message $ex) {
                $content = array();
                //the content is not available anymore
            }
            //if we have the content, store it forever since it's not available forever in the api
            if ($cache_enabled) {
                $cache->save(serialize($content), $cache_key,
                    array(self::MESSAGE_TAG), 0);
            }
        }
        return $content;
    }

    public function doSearch($data, Form $form)
    {
        $values = array();
        foreach ($form->Fields() as $field) {
            $values[$field->getName()] = $field->datavalue();
        }
        // If we have a subaccount defined, we need to restrict the query to this subaccount
        if($subaccount = MandrillMailer::getSubaccount()) {
            if(empty($values['Query'])) {
                $values['Query'] = 'subaccount:' . $subaccount;
            }
            else {
                $values['Query'] = $values['Query'] . ' AND subaccount:' . $subaccount;
            }
        }
        Session::set('MandrilAdminSearch', $values);
        Session::save();
        return $this->redirectBack();
    }

    public function getParam($name, $default = null)
    {
        $data = Session::get('MandrilAdminSearch');
        if (!$data) {
            return $default;
        }
        return (isset($data[$name]) && strlen($data[$name])) ? $data[$name] : $default;
    }

    public function SearchForm()
    {
        $fields     = new FieldList();
        $fields->push(new DateField('DateFrom', _t('Mandrill.DATEFROM', 'From'),
            $this->getParam('DateFrom', date('Y-m-d', strtotime('-30 days')))));
        $fields->push(new DateField('DateTo', _t('Mandrill.DATETO', 'To'),
            $this->getParam('DateTo', date('Y-m-d'))));
        $fields->push($queryField = new TextField('Query',
            _t('Mandrill.QUERY', 'Query'), $this->getParam('Query')));
        $queryField->setDescription(_t('Mandrill.QUERYDESC',
                'For more information about query syntax, please visit <a target="_blank" href="http://help.mandrill.com/entries/22211902">Mandrill Support</a>'));
        $fields->push(new DropdownField('Limit', _t('Mandrill.LIMIT', 'Limit'),
            array(
            10 => 10,
            50 => 50,
            100 => 100,
            500 => 500,
            1000 => 1000
            ), $this->getParam('Limit', 100)));
        $actions    = new FieldList();
        $actions->push(new FormAction('doSearch',
            _t('Mandrill.DOSEARCH', 'Search')));
        $form       = new Form($this, 'SearchForm', $fields, $actions);
        return $form;
    }

    /**
     * Provides custom permissions to the Security section
     *
     * @return array
     */
    public function providePermissions()
    {
        $title = _t("Mandrill.MENUTITLE",
            LeftAndMain::menu_title_for_class('Mandrill'));
        return array(
            "CMS_ACCESS_Mandrill" => array(
                'name' => _t('Mandrill.ACCESS', "Access to '{title}' section",
                    array('title' => $title)),
                'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => _t(
                    'Mandrill.ACCESS_HELP',
                    'Allow use of Mandrill admin section'
                )
            ),
        );
    }

    /**
     * A template accessor to check the ADMIN permission
     *
     * @return bool
     */
    public function IsAdmin()
    {
        return Permission::check("ADMIN");
    }

    /**
     * Check the permission to make sure the current user has a mandrill
     *
     * @return bool
     */
    public function canView($member = null)
    {
        return Permission::check("CMS_ACCESS_Mandrill");
    }

    /**
     * Check if webhook is installed
     *
     * @return array
     */
    public function WebhookInstalled()
    {
        $mandrill = $this->getMandrill();

        $cache_enabled = $this->getCacheEnabled();
        if ($cache_enabled) {
            $cache        = $this->getCache();
            $cache_key    = 'webooks';
            $cache_result = $cache->load($cache_key);
        }
        if ($cache_enabled && $cache_result) {
            $list = unserialize($cache_result);
        } else {
            try {
                $list = $mandrill->webhooks->getList();
                if ($cache_enabled) {
                    $cache->save(serialize($list), $cache_key, array(self::WEBHOOK_TAG),
                        60 * self::WEBHOOK_CACHE_MINUTES);
                }
            } catch (Exception $ex) {
                $list = array();
                SS_Log::log($ex->getMessage(), SS_Log::DEBUG);
            }
        }
        if (empty($list)) {
            return false;
        }
        $url = $this->WebhookUrl();
        foreach ($list as $el) {
            if ($el['url'] === $url) {
                return $el;
            }
        }
        return false;
    }

    /**
     * Hook details for template
     * @return \ArrayData
     */
    public function WebhookDetails()
    {
        $el = $this->WebhookInstalled();
        if ($el) {
            return new ArrayData($el);
        }
    }

    /**
     * @return string
     */
    public function WebhookUrl()
    {
        return Director::absoluteURL('/mandrill/incoming');
    }

    /**
     *
     * @return bool
     */
    public function CanConfigureWebhooks() {
        return Permission::check('ADMIN') || Director::isDev();
    }

    /**
     * Install hook form
     * 
     * @return \Form
     */
    public function InstallHookForm()
    {
        $fields  = new FieldList();
        $fields->push(new LiteralField('Info',
            '<div class="message info">'._t('MandrillAdmin.HookNotInstalled',
                'Hook is not installed. Url of the webhook is: {url}. This url must be publicly visible to be used as a hook.',
                array('url' => $this->WebhookUrl())).'</div>'));
        $actions = new FieldList();
        $actions->push(new FormAction('doInstallHook',
            _t('Mandrill.DOINSTALL', 'Install hook')));
        $form    = new Form($this, 'InstallHookForm', $fields, $actions);
        return $form;
    }

    public function doInstallHook($data, Form $form)
    {
        $mandrill = $this->getMandrill();

        $url         = $this->WebhookUrl();
        $description = SiteConfig::current_site_config()->Title;

        try {
            $mandrill->webhooks->add($url, $description);
            $this->getCache()->clean('matchingTag', array(self::WEBHOOK_TAG));
        } catch (Exception $ex) {
            SS_Log::log($ex->getMessage(), SS_Log::DEBUG);
        }

        return $this->redirectBack();
    }

    /**
     * Uninstall hook form
     *
     * @return \Form
     */
    public function UninstallHookForm()
    {
        $fields  = new FieldList();
        $fields->push(new LiteralField('Info',
            '<div class="message info">'._t('MandrillAdmin.HookInstalled',
                'Hook is installed. Url of the webhook is: {url}.',
                array('url' => $this->WebhookUrl())).'</div>'));
        $actions = new FieldList();
        $actions->push(new FormAction('doUninstallHook',
            _t('Mandrill.DOUNINSTALL', 'Uninstall hook')));
        $form    = new Form($this, 'InstallHookForm', $fields, $actions);
        return $form;
    }

    public function doUninstallHook($data, Form $form)
    {
        $mandrill = $this->getMandrill();

        $url         = $this->WebhookUrl();
        $description = SiteConfig::current_site_config()->Title;

        try {
            $el = $this->WebhookInstalled();
            $mandrill->webhooks->delete($el['id']);
            $this->getCache()->clean('matchingTag', array(self::WEBHOOK_TAG));
        } catch (Exception $ex) {
            SS_Log::log($ex->getMessage(), SS_Log::DEBUG);
        }

        return $this->redirectBack();
    }
}