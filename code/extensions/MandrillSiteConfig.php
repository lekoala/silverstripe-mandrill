<?php

/**
 * Sample SiteConfig ready to be integrated with Mandrill emails
 */
class MandrillSiteConfig extends DataExtension
{

    private static $db = array(
        'DefaultFromEmail' => 'Varchar(255)',
        'DefaultToEmail' => 'Varchar(255)',
    );

    public function updateCMSFields(FieldList $fields)
    {
        $fields->addFieldToTab('Root.Email', new TextField('DefaultFromEmail', _t('MandrillSiteConfig.DefaultFromEmail', 'Default From Email')));
        $fields->addFieldToTab('Root.Email', new TextField('DefaultToEmail', _t('MandrillSiteConfig.DefaultToEmail', 'Default To Email')));

        return $fields;
    }
}
