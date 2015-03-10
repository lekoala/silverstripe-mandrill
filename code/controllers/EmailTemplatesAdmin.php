<?php

/**
 * EmailTemplatesAdmin
 *
 * @author lekoala
 */
class EmailTemplatesAdmin extends ModelAdmin
{
    private static $managed_models = array(
        'EmailTemplate',
    );
    private static $url_segment    = 'emails';
    private static $menu_title     = 'Emails';
    private static $menu_icon      = 'mandrill/images/mail.png';
    private static $allowed_actions = array(
        'ImportForm',
        'SearchForm',
        'PreviewEmail'
    );

    public function getSearchContext()
    {
        $context = parent::getSearchContext();

        return $context;
    }

    public function getList()
    {
        $list = parent::getList();

        return $list;
    }
    
    public function PreviewEmail() {
        $id = (int) $this->getRequest()->getVar('id');

        /* @var $emailTemplate EmailTemplate */
        $emailTemplate = EmailTemplate::get()->byID($id);

        $html = $emailTemplate->renderTemplate();

        return $html;
    }
}