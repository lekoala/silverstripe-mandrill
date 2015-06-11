<?php

/**
 * Sample SiteConfig ready to be integrated with Mandrill emails
 */
class MandrillSiteConfig extends DataExtension
{
    private static $db      = array(
        'EmailFooter' => 'HTMLText',
        'EmailTheme' => 'Varchar',
        'DefaultFromEmail' => 'Varchar(255)',
        'DefaultToEmail' => 'Varchar(255)',
    );
    private static $has_one = array(
        'EmailLogo' => 'Image',
    );

    public function updateCMSFields(FieldList $fields)
    {
        $themes = MandrillEmail::getAvailableThemes();

        $fields->addFieldToTab('Root.Email',
            $html       = new HtmlEditorField('EmailFooter',
            _t('MandrillSiteConfig.EmailFooter', 'Email Footer')));
        $html->setRows(5);
        $fields->addFieldToTab('Root.Email',
            $emailTheme = new DropdownField('EmailTheme',
            _t('MandrillSiteConfig.EmailTheme', 'Email Theme'),
            array_combine($themes, $themes)));
        $emailTheme->setEmptyString('');

        $fields->addFieldToTab('Root.Email',
            new TextField('DefaultFromEmail',
            _t('MandrillSiteConfig.DefaultFromEmail', 'Default From Email')));
        $fields->addFieldToTab('Root.Email',
            new TextField('DefaultToEmail',
            _t('MandrillSiteConfig.DefaultToEmail', 'Default To Email')));


        // form-extras integration
        $uploadClass = 'UploadField';
        if(class_exists('ImageUploadField')) {
            $uploadClass = 'ImageUploadField';
        }
        $fields->addFieldToTab('Root.Email',
            $emailLogo = new $uploadClass('EmailLogo',
            _t('MandrillSiteConfig.EmailLogo', 'Email Logo')));
        $emailLogo->setDescription(_t('MandrillSiteConfig.EmailLogoDesc',
                'Will default to Logo if none defined'));
        
        return $fields;
    }

    public function EmailLogoTemplate()
    {
        if ($this->owner->EmailLogoID) {
            return $this->owner->EmailLogo();
        }
        if ($this->owner->LogoID) {
            return $this->owner->Logo();
        }
    }
}