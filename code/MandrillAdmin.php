<?php
namespace LeKoala\Mandrill;

use Exception;
use Psr\Log\LoggerInterface;
use Psr\SimpleCache\CacheInterface;
use Psr\SimpleCache\InvalidArgumentException;
use SilverStripe\Admin\LeftAndMain;
use SilverStripe\Control\Director;
use SilverStripe\Control\Email\SwiftMailer;
use SilverStripe\Control\HTTPResponse;
use SilverStripe\Control\Session;
use SilverStripe\Forms\CompositeField;
use SilverStripe\Forms\DateField;
use SilverStripe\Forms\DropdownField;
use SilverStripe\Forms\FieldList;
use SilverStripe\Forms\Form;
use SilverStripe\Forms\FormAction;
use SilverStripe\Forms\FormField;
use SilverStripe\Forms\GridField\GridField;
use SilverStripe\Forms\GridField\GridFieldConfig;
use SilverStripe\Forms\GridField\GridFieldDataColumns;
use SilverStripe\Forms\GridField\GridFieldDetailForm;
use SilverStripe\Forms\GridField\GridFieldFooter;
use SilverStripe\Forms\GridField\GridFieldSortableHeader;
use SilverStripe\Forms\GridField\GridFieldToolbarHeader;
use SilverStripe\Forms\HiddenField;
use SilverStripe\Forms\LiteralField;
use SilverStripe\Forms\Tab;
use SilverStripe\Forms\TabSet;
use SilverStripe\Forms\TextField;
use SilverStripe\ORM\ArrayLib;
use SilverStripe\ORM\ArrayList;
use SilverStripe\Security\Permission;
use SilverStripe\Security\PermissionProvider;
use SilverStripe\Security\Security;
use SilverStripe\SiteConfig\SiteConfig;
use SilverStripe\View\ArrayData;
use Symbiote\GridFieldExtensions\GridFieldTitleHeader;

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

    const MESSAGE_CACHE_MINUTES = 5;
    const WEBHOOK_CACHE_MINUTES = 1440; // 1 day
    const SENDINGDOMAIN_CACHE_MINUTES = 1440; // 1 day

    private static $menu_title = "Mandrill";
    private static $url_segment = "mandrill";
    private static $menu_icon = "lekoala/silverstripe-mandrill:images/icon.png";
    private static $url_rule = '/$Action/$ID/$OtherID';
    private static $allowed_actions = array(
        "settings",
        "ListForm",
        "SearchForm",
        "doSearch",
        "InstallHookForm",
        "doInstallHook",
        "UninstallHookForm",
        "doUninstallHook"
    );
    private static $cache_enabled = true;

    /**
     * @var Exception
     */
    protected $lastException;

    /**
     * Inject public dependencies into the controller
     *
     * @var array
     */
    private static $dependencies = [
        'logger' => '%$Psr\Log\LoggerInterface',
        'cache' => '%$Psr\SimpleCache\CacheInterface.mandrill', // see _config/cache.yml
    ];

    /**
     * @var LoggerInterface
     */
    public $logger;

    /**
     * @var CacheInterface
     */
    public $cache;

    public function init()
    {
        parent::init();

        if (isset($_GET['refresh'])) {
            $this->getCache()->clear();
        }
    }

    public function index($request)
    {
        return parent::index($request);
    }

    public function settings($request)
    {
        return parent::index($request);
    }

    /**
     * @return Session
     */
    public function getSession()
    {
        return $this->getRequest()->getSession();
    }

    /**
     * Returns a GridField of messages
     *
     * @param int $id
     * @param FieldList $fields
     * @return Form
     * @throws InvalidArgumentException
     */
    public function getEditForm($id = null, $fields = null)
    {
        if (!$id) {
            $id = $this->currentPageID();
        }

        $record = $this->getRecord($id);

        // Check if this record is viewable
        if ($record && !$record->canView()) {
            $response = Security::permissionFailure($this);
            $this->setResponse($response);
            return null;
        }

        // Build gridfield
        $messageListConfig = GridFieldConfig::create()->addComponents(
            new GridFieldSortableHeader(),
            new GridFieldDataColumns(),
            new GridFieldFooter()
        );

        $messages = $this->Messages();
        if (is_string($messages)) {
            // The api returned an error
            $messagesList = new LiteralField("MessageAlert", $this->MessageHelper($messages, 'bad'));
        } else {
            $messagesList = GridField::create(
                'Messages',
                false,
                $messages,
                $messageListConfig
            )->addExtraClass("messages_grid");

            /** @var GridFieldDataColumns $columns */
            $columns = $messageListConfig->getComponentByType(GridFieldDataColumns::class);
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
                'state' => function ($value, &$item) {
                    switch ($value) {
                        case 'sent':
                            $color = 'green';
                            break;
                        default:
                            $color = '#333';
                            break;
                    }
                    return sprintf('<span style="color:%s">%s</span>', $color, $value);
                }
            ));

            // Validator setup
            $validator = null;
            if ($record && method_exists($record, 'getValidator')) {
                $validator = $record->getValidator();
            }

            if ($validator) {
                /** @var GridFieldDetailForm $detailForm */
                $detailForm = $messageListConfig->getComponentByType(GridFieldDetailForm::class);
                $detailForm->setValidator($validator);
            }
        }

        // Create tabs
        $messagesTab = new Tab(
            'Messages',
            _t('MandrillAdmin.Messages', 'Messages'),
            $this->SearchFields(),
            $messagesList,
            // necessary for tree node selection in LeftAndMain.EditForm.js
            new HiddenField('ID', false, 0)
        );

        $fields = new FieldList(
            $root = new TabSet('Root', $messagesTab)
        );

        if ($this->CanConfigureApi()) {
            $settingsTab = new Tab('Settings', _t('MandrillAdmin.Settings', 'Settings'));

            $domainTabData = $this->DomainTab();
            $settingsTab->push($domainTabData);

            $webhookTabData = $this->WebhookTab();
            $settingsTab->push($webhookTabData);

            // Add a refresh button
            $refreshButton = new LiteralField('RefreshButton', $this->ButtonHelper(
                $this->Link() . '?refresh=true',
                _t('MandrillAdmin.REFRESH', 'Force data refresh from the API')
            ));
            $settingsTab->push($refreshButton);

            $fields->addFieldToTab('Root', $settingsTab);
        }

        // Tab nav in CMS is rendered through separate template
        $root->setTemplate('SilverStripe\\Forms\\CMSTabSet');

        // Manage tabs state
        $actionParam = $this->getRequest()->param('Action');
        if ($actionParam == 'setting') {
            $settingsTab->addExtraClass('ui-state-active');
        } elseif ($actionParam == 'messages') {
            $messagesTab->addExtraClass('ui-state-active');
        }

        // Build replacement form
        $form = Form::create(
            $this,
            'EditForm',
            $fields,
            new FieldList()
        )->setHTMLID('Form_EditForm');
        $form->addExtraClass('cms-edit-form fill-height');
        $form->setTemplate($this->getTemplatesWithSuffix('_EditForm'));
        $form->addExtraClass('ss-tabset cms-tabset ' . $this->BaseCSSClasses());
        $form->setAttribute('data-pjax-fragment', 'CurrentForm');

        $this->extend('updateEditForm', $form);

        return $form;
    }

    /**
     * Get logger
     *
     * @return  LoggerInterface
     */
    public function getLogger()
    {
        return $this->logger;
    }

    /**
     * Get the cache
     *
     * @return CacheInterface
     */
    public function getCache()
    {
        return $this->cache;
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
     * A simple cache helper
     *
     * @param string $method
     * @param array $params Params must be set in the right order!
     * @param int $expireInSeconds
     * @return array
     * @throws InvalidArgumentException
     */
    protected function getCachedData($method, $params, $expireInSeconds = 60)
    {
        $enabled = $this->getCacheEnabled();
        if ($enabled) {
            $cache = $this->getCache();
            $key = md5(serialize($params));
            $cacheResult = $cache->get($key);
        }
        if ($enabled && $cacheResult) {
            $data = unserialize($cacheResult);
        } else {
            try {
                $client = MandrillHelper::getClient();
                $parts = explode('.', $method);
                $service = $parts[0];
                $func = $parts[1];
                $data = call_user_func_array([$client->$service, $func], $params);
            } catch (Exception $ex) {
                $this->lastException = $ex;
                $this->getLogger()->debug($ex);
                $data = false;
                $enabled = false;
            }

            //5 minutes cache
            if ($enabled) {
                $cache->set($key, serialize($data), $expireInSeconds);
            }
        }

        return $data;
    }

    public function getParams()
    {
        $params = $this->config()->default_search_params;
        if (!$params) {
            $params = [];
        }
        $data = $this->getSession()->get(__class__ . '.Search');
        if (!$data) {
            $data = [];
        }

        $params = array_merge($params, $data);

        // Remove empty params
        $params = array_filter($params);

        return $params;
    }

    public function getParam($name, $default = null)
    {
        $data = $this->getSession()->get(__class__ . '.Search');
        if (!$data) {
            return $default;
        }
        return (isset($data[$name]) && strlen($data[$name])) ? $data[$name] : $default;
    }

    public function SearchFields()
    {
        $fields = new CompositeField();
        $fields->push($from = new DateField('params[date_from]', _t('MandrillAdmin.DATEFROM', 'From'), $this->getParam('date_from', date('Y-m-d', strtotime('-30 days')))));
        $fields->push($to = new DateField('params[date_to]', _t('MandrillAdmin.DATETO', 'To'), $to = $this->getParam('date_to')));
        $fields->push($queryField = new TextField('params[query]', _t('Mandrill.QUERY', 'Query'), $this->getParam('query')));
        $queryField->setAttribute('placeholder', 'full_email:joe@domain.* AND sender:me@company.com OR subject:welcome');
        $queryField->setDescription(_t('Mandrill.QUERYDESC', 'For more information about query syntax, please visit <a target="_blank" href="https://mandrill.zendesk.com/hc/en-us/articles/205583137-How-do-I-search-my-outbound-activity-in-Mandrill-">Mandrill Support</a>'));
        $fields->push(new DropdownField('params[limit]', _t('MandrillAdmin.PERPAGE', 'Number of results'), array(
            100 => 100,
            500 => 500,
            1000 => 1000,
            10000 => 10000,
        ), $this->getParam('limit', 100)));

        foreach ($fields->FieldList() as $field) {
            $field->addExtraClass('no-change-track');
        }

        // This is a ugly hack to allow embedding a form into another form
        $fields->push($doSearch = new FormAction('doSearch', _t('MandrillAdmin.DOSEARCH', 'Search')));
        $doSearch->setAttribute('onclick', "jQuery('#Form_SearchForm').append(jQuery('#Form_EditForm input,#Form_EditForm select').clone()).submit();");

        return $fields;
    }

    public function SearchForm()
    {
        $SearchForm = new Form($this, 'SearchForm', new FieldList(), new FieldList(new FormAction('doSearch')));
        $SearchForm->setAttribute('style', 'display:none');
        return $SearchForm;
    }

    public function doSearch($data, Form $form)
    {
        $post = $this->getRequest()->postVar('params');
        if (!$post) {
            return $this->redirectBack();
        }
        $params = [];

        $validFields = [];
        foreach ($this->SearchFields()->FieldList()->dataFields() as $field) {
            $validFields[] = str_replace(['params[', ']'], '', $field->getName());
        }

        foreach ($post as $k => $v) {
            if (in_array($k, $validFields)) {
                $params[$k] = $v;
            }
        }

        $this->getSession()->set(__class__ . '.Search', $params);
        $this->getSession()->save($this->getRequest());

        return $this->redirectBack();
    }

    /**
     * List of messages events
     *
     * Messages are cached to avoid hammering the api
     *
     * @link https://mandrillapp.com/api/docs/messages.JSON.html#method=search
     * @return ArrayList|string
     * @throws InvalidArgumentException
     */
    public function Messages()
    {
        $params = $this->getParams();

        $orderedParams = [];
        $orderedParams['query'] = isset($params['query']) ? $params['query'] : null;
        $orderedParams['date_from'] = isset($params['date_from']) ? $params['date_from'] : null;
        $orderedParams['date_to'] = isset($params['date_to']) ? $params['date_to'] : null;
        $orderedParams['tags'] = isset($params['tags']) ? $params['tags'] : null;
        $orderedParams['senders'] = isset($params['senders']) ? $params['senders'] : null;
        $orderedParams['api_keys'] = isset($params['api_keys']) ? $params['api_keys'] : null;
        $orderedParams['limit'] = isset($params['limit']) ? $params['limit'] : null;

        $messages = $this->getCachedData('messages.search', $orderedParams, 60 * self::MESSAGE_CACHE_MINUTES);
        if ($messages === false) {
            if ($this->lastException) {
                return $this->lastException->getMessage();
            }
            return _t('MandrillAdmin.NO_MESSAGES', 'No messages');
        }

        $list = new ArrayList();
        if ($messages) {
            foreach ($messages as $message) {
                $message['date'] = date('Y-m-d H:i:s', $message['ts']);
                $m = new ArrayData($message);
                $list->push($m);
            }
        }

        return $list;
    }

    /**
     * Provides custom permissions to the Security section
     *
     * @return array
     */
    public function providePermissions()
    {
        $title = _t("MandrillAdmin.MENUTITLE", LeftAndMain::menu_title_for_class('Mandrill'));
        return [
            "CMS_ACCESS_Mandrill" => [
                'name' => _t('MandrillAdmin.ACCESS', "Access to '{title}' section", ['title' => $title]),
                'category' => _t('Permission.CMS_ACCESS_CATEGORY', 'CMS Access'),
                'help' => _t(
                    'MandrillAdmin.ACCESS_HELP',
                    'Allow use of Mandrill admin section'
                )
            ],
        ];
    }

    /**
     * Message helper
     *
     * @param string $message
     * @param string $status
     * @return string
     */
    protected function MessageHelper($message, $status = 'info')
    {
        return '<div class="message ' . $status . '">' . $message . '</div>';
    }

    /**
     * Button helper
     *
     * @param string $link
     * @param string $text
     * @param boolean $confirm
     * @return string
     */
    protected function ButtonHelper($link, $text, $confirm = false)
    {
        $link = '<a class="btn btn-primary" href="' . $link . '"';
        if ($confirm) {
            $link .= ' onclick="return confirm(\'' . _t('MandrillAdmin.CONFIRM_MSG', 'Are you sure?') . '\')"';
        }
        $link .= '>' . $text . '</a>';
        return $link;
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
     * Check the permission for current user
     *
     * @return bool
     */
    public function canView($member = null)
    {
        $mailer = MandrillHelper::getMailer();
        // Another custom mailer has been set
        if (!$mailer instanceof SwiftMailer) {
            return false;
        }
        // Doesn't use the proper transport
        if (!$mailer->getSwiftMailer()->getTransport() instanceof MandrillSwiftTransport) {
            return false;
        }
        return Permission::check("CMS_ACCESS_Mandrill", 'any', $member);
    }

    /**
     *
     * @return bool
     */
    public function CanConfigureApi()
    {
        return Permission::check('ADMIN') || Director::isDev();
    }

    /**
     * Check if webhook is installed. Returns the webhook details if installed.
     *
     * @return bool|array
     * @throws InvalidArgumentException
     */
    public function WebhookInstalled()
    {
        $list = $this->getCachedData('webhooks.getList', [], 60 * self::WEBHOOK_CACHE_MINUTES);

        if (empty($list)) {
            return false;
        }
        $url = $this->WebhookUrl();
        foreach ($list as $el) {
            if (!empty($el['url']) && $el['url'] === $url) {
                return $el;
            }
        }
        return false;
    }

    /**
     * Hook details for template
     * @return ArrayData|null
     * @throws InvalidArgumentException
     */
    public function WebhookDetails()
    {
        $el = $this->WebhookInstalled();
        if ($el) {
            return new ArrayData($el);
        }
        return null;
    }

    /**
     * Get content of the tab
     *
     * @return FormField
     * @throws InvalidArgumentException
     */
    public function WebhookTab()
    {
        if ($this->WebhookInstalled()) {
            return $this->UninstallHookForm();
        }
        return $this->InstallHookForm();
    }

    /**
     * @return string
     */
    public function WebhookUrl()
    {
        if (self::config()->webhook_base_url) {
            return rtrim(self::config()->webhook_base_url, '/') . '/__mandrill/incoming';
        }
        if (Director::isLive()) {
            return Director::absoluteURL('/__mandrill/incoming');
        }
        $protocol = Director::protocol();
        return $protocol . $this->getDomain() . '/__mandrill/incoming';
    }

    /**
     * Install hook form
     *
     * @return FormField
     */
    public function InstallHookForm()
    {
        $fields = new CompositeField();
        $fields->push(new LiteralField('Info', $this->MessageHelper(
            _t('MandrillAdmin.WebhookNotInstalled', 'Webhook is not installed. It should be configured using the following url {url}. This url must be publicly visible to be used as a hook.', ['url' => $this->WebhookUrl()]),
            'bad'
        )));
        $fields->push(new LiteralField('doInstallHook', $this->ButtonHelper(
            $this->Link('doInstallHook'),
            _t('MandrillAdmin.DOINSTALL_WEBHOOK', 'Install webhook')
        )));
        return $fields;
    }

    /**
     * @return HTTPResponse
     * @throws Exception
     */
    public function doInstallHook()
    {
        if (!$this->CanConfigureApi()) {
            return $this->redirectBack();
        }

        $client = MandrillHelper::getClient();

        $url = $this->WebhookUrl();
        $description = SiteConfig::current_site_config()->Title;

        try {
            if (defined('SS_DEFAULT_ADMIN_USERNAME') && SS_DEFAULT_ADMIN_USERNAME) {
                $client->createSimpleWebhook($description, $url, null, true, ['username' => SS_DEFAULT_ADMIN_USERNAME, 'password' => SS_DEFAULT_ADMIN_PASSWORD]);
            } else {
                $client->createSimpleWebhook($description, $url);
            }
            $this->getCache()->clear();
        } catch (Exception $ex) {
            $this->getLogger()->debug($ex);
        }

        return $this->redirectBack();
    }

    /**
     * Uninstall hook form
     *
     * @return FormField
     */
    public function UninstallHookForm()
    {
        $fields = new CompositeField();
        $fields->push(new LiteralField('Info', $this->MessageHelper(
            _t('MandrillAdmin.WebhookInstalled', 'Webhook is installed and accessible at the following url {url}.', ['url' => $this->WebhookUrl()]),
            'good'
        )));
        $fields->push(new LiteralField('doUninstallHook', $this->ButtonHelper(
            $this->Link('doUninstallHook'),
            _t('MandrillAdmin.DOUNINSTALL_WEBHOOK', 'Uninstall webhook'),
            true
        )));
        return $fields;
    }

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function doUninstallHook($data, Form $form)
    {
        if (!$this->CanConfigureApi()) {
            return $this->redirectBack();
        }

        $client = MandrillHelper::getClient();

        try {
            $el = $this->WebhookInstalled();
            if ($el && !empty($el['id'])) {
                $client->deleteWebhook($el['id']);
            }
            $this->getCache()->clear();
        } catch (Exception $ex) {
            $this->getLogger()->debug($ex);
        }

        return $this->redirectBack();
    }

    /**
     * Check if sending domain is installed
     *
     * @return array|bool
     * @throws InvalidArgumentException
     */
    public function SendingDomainInstalled()
    {
        // @todo - Use $client or remove?
        $client = MandrillHelper::getClient();

        $domains = $this->getCachedData('senders.domains', [$this->getDomain()], 60 * self::SENDINGDOMAIN_CACHE_MINUTES);

        if (empty($domains)) {
            return false;
        }

        // Filter
        $host = $this->getDomain();
        foreach ($domains as $domain) {
            if ($domain['domain'] == $host) {
                return $domain;
            }
        }
        return false;
    }

    /**
     * Trigger request to check if sending domain is verified
     *
     * @return array|bool
     * @throws Exception
     */
    public function VerifySendingDomain()
    {
        $client = MandrillHelper::getClient();

        $host = $this->getDomain();

        $verification = $client->verifySendingDomain($host);

        if (empty($verification)) {
            return false;
        }
        return $verification;
    }

    /**
     * Get content of the tab
     *
     * @return FormField
     * @throws InvalidArgumentException
     */
    public function DomainTab()
    {
        $defaultDomain = $this->getDomain();
        $defaultDomainInfos = null;

        $domains = $this->getCachedData('senders.domains', [], 60 * self::SENDINGDOMAIN_CACHE_MINUTES);

        $fields = new CompositeField();

        // @link https://mandrillapp.com/api/docs/senders.JSON.html#method=domains
        $list = new ArrayList();
        if ($domains) {
            foreach ($domains as $domain) {
                $list->push(new ArrayData([
                    'Domain' => $domain['domain'],
                    'SPF' => $domain['spf']['valid'],
                    'DKIM' => $domain['dkim']['valid'],
                    'Compliance' => $domain['valid_signing'],
                    'Verified' => $domain['verified_at'],
                ]));

                if ($domain['domain'] == $defaultDomain) {
                    $defaultDomainInfos = $domain;
                }
            }
        }

        $config = GridFieldConfig::create();
        $config->addComponent(new GridFieldToolbarHeader());
        $config->addComponent(new GridFieldTitleHeader());
        $config->addComponent($columns = new GridFieldDataColumns());
        $columns->setDisplayFields(ArrayLib::valuekey(['Domain', 'SPF', 'DKIM', 'Compliance', 'Verified']));
        $domainsList = new GridField('SendingDomains', _t('MandrillAdmin.ALL_SENDING_DOMAINS', 'Configured sending domains'), $list, $config);
        $domainsList->addExtraClass('mb-2');
        $fields->push($domainsList);

        if (!$defaultDomainInfos) {
            $this->InstallDomainForm($fields);
        } else {
            $this->UninstallDomainForm($fields);
        }

        return $fields;
    }

    /**
     * @return string
     */
    public function InboundUrl()
    {
        $subdomain = self::config()->inbound_subdomain;
        $domain = $this->getDomain();
        if ($domain) {
            return $subdomain . '.' . $domain;
        }
        return false;
    }

    /**
     * Get domain
     *
     * @return boolean|string
     */
    public function getDomain()
    {
        return MandrillHelper::getDomain();
    }

    /**
     * Install domain form
     *
     * @param CompositeField $fields
     */
    public function InstallDomainForm(CompositeField $fields)
    {
        $host = $this->getDomain();

        $fields->push(new LiteralField('Info', $this->MessageHelper(
            _t('MandrillAdmin.DomainNotInstalled', 'Default sending domain {domain} is not installed.', ['domain' => $host]),
            "bad"
        )));
        $fields->push(new LiteralField('doInstallDomain', $this->ButtonHelper(
            $this->Link('doInstallDomain'),
            _t('MandrillAdmin.DOINSTALLDOMAIN', 'Install domain')
        )));
    }

    /**
     * @return HTTPResponse
     * @throws Exception
     */
    public function doInstallDomain()
    {
        if (!$this->CanConfigureApi()) {
            return $this->redirectBack();
        }

        $client = MandrillHelper::getClient();

        $domain = $this->getDomain();

        if (!$domain) {
            return $this->redirectBack();
        }

        try {
            $client->createSimpleSendingDomain($domain);
            $this->getCache()->clear();
        } catch (Exception $ex) {
            $this->getLogger()->debug($ex);
        }

        return $this->redirectBack();
    }

    /**
     * Uninstall domain form
     *
     * @param CompositeField $fields
     * @throws InvalidArgumentException
     */
    public function UninstallDomainForm(CompositeField $fields)
    {
        $domainInfos = $this->SendingDomainInstalled();

        $domain = $this->getDomain();

        if ($domainInfos && $domainInfos['valid_signing']) {
            $fields->push(new LiteralField('Info', $this->MessageHelper(
                _t('MandrillAdmin.DomainInstalled', 'Default domain {domain} is installed.', ['domain' => $domain]),
                'good'
            )));
        } else {
            $fields->push(new LiteralField('Info', $this->MessageHelper(
                _t('MandrillAdmin.DomainInstalledBut', 'Default domain {domain} is installed, but is not properly configured.'),
                'warning'
            )));
        }
        $fields->push(new LiteralField('doUninstallHook', $this->ButtonHelper(
            $this->Link('doUninstallHook'),
            _t('MandrillAdmin.DOUNINSTALLDOMAIN', 'Uninstall domain'),
            true
        )));
    }

    /**
     * @param array $data
     * @param Form $form
     * @return HTTPResponse
     * @throws Exception
     * @throws InvalidArgumentException
     */
    public function doUninstallDomain($data, Form $form)
    {
        if (!$this->CanConfigureApi()) {
            return $this->redirectBack();
        }

        $client = MandrillHelper::getClient();

        $domain = $this->getDomain();

        if (!$domain) {
            return $this->redirectBack();
        }

        try {
            $el = $this->SendingDomainInstalled();
            if ($el) {
                $client->deleteSendingDomain($domain);
            }
            $this->getCache()->clear();
        } catch (Exception $ex) {
            $this->getLogger()->debug($ex);
        }

        return $this->redirectBack();
    }
}
