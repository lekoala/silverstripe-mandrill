<?php

/**
 * Sample SiteConfig ready to be integrated with Mandrill emails
 */
class MandrillSiteConfig extends DataExtension
{
    private static $db      = array(
        'EmailFooter' => 'HTMLText',
        'DefaultFromEmail' => 'Varchar(255)',
        'DefaultToEmail' => 'Varchar(255)',
    );
    private static $has_one = array(
        'Logo' => 'Image',
    );

    public function updateCMSFields(FieldList $fields)
    {

        $fields->addFieldToTab('Root.Email', new HtmlEditorField('EmailFooter'));
        $fields->addFieldToTab('Root.Email', new TextField('DefaultFromEmail'));
        $fields->addFieldToTab('Root.Email', new TextField('DefaultToEmail'));
        $fields->addFieldToTab('Root.Main', new ImageUploadField('Logo'));

        return $fields;
    }
}