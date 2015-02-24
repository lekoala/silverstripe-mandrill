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
    private static $menu_icon = 'mandrill/images/mail.png';

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
}