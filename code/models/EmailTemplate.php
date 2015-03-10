<?php

/**
 * EmailTemplate
 *
 * @author lekoala
 */
class EmailTemplate extends DataObject
{
    private static $db                = array(
        'Title' => 'Varchar(255)',
        'Template' => 'Varchar(255)',
        'Theme' => 'Varchar(255)',
        'Category' => 'Varchar(255)',
        'ExtraModels' => 'Varchar(255)',
        'Code' => 'Varchar(255)',
        'Content' => 'HTMLText',
        'Callout' => 'HTMLText',
    );
    private static $summary_fields    = array(
        'Title',
        'Code',
        'Category'
    );
    private static $searchable_fields = array(
        'Title',
        'Code',
        'Category'
    );
    private static $indexes           = array(
        'Code' => array(
            'type' => 'unique',
            'value' => 'Code'
        )
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();

        $templates = MandrillEmail::getAvailablesTemplates();
        $fields->replaceField('Template',
            new DropdownField('Template', 'Template', $templates));

        $themes = MandrillEmail::getAvailableThemes();
        $fields->replaceField('Theme',
            new DropdownField('Theme', 'Theme', array_combine($themes, $themes)));

        $objectsSource = array();
        $dataobjects   = ClassInfo::subclassesFor('DataObject');
        foreach ($dataobjects as $dataobject) {
            if ($dataobject == 'DataObject') {
                continue;
            }
            $singl                      = singleton($dataobject);
            $objectsSource[$dataobject] = $dataobject;
        }
        $fields->replaceField('ExtraModels',
            $extraModels = new ListboxField('ExtraModels', 'Extra Models',
            $objectsSource));
        $extraModels->setMultiple(true);

        // form-extras integration
        if (class_exists('ComboField')) {
            $categories = EmailTemplate::get()->column('Category');
            $fields->replaceField('Category',
                new ComboField('Category', 'Category',
                array_combine($categories, $categories)));
        }

        $fields->dataFieldByName('Callout')->setRows(5);

        $fields->dataFieldByName('Code')->setAttribute('placeholder',
            _t('EmailTemplate.CODEPLACEHOLDER',
                'A unique code that will be used in code to retrieve the template, e.g.: my-email'));

        // Merge fields helper
        $fields->addFieldToTab('Root.Main',
            new HeaderField('MergeFieldsHelperTitle',
            _t('EmailTemplate.AVAILABLEMERGEFIELDSTITLE',
                'Available merge fields')));
        $content = '';
        $models  = $this->getAvailableModels();
        foreach ($models as $name => $model) {
            $content .= '<strong>'.$name.' ('.$model.') :</strong><br/>';
            $props = Config::inst()->get($model, 'db');
            foreach ($props as $fieldName => $fieldType) {
                $content .= $fieldName.', ';
            }
            $content = trim($content, ', ').'<br/>';
        }
        $fields->addFieldToTab('Root.Main',
            new LiteralField('MergeFieldsHelper', $content));

        if ($this->ID) {
            $fields->addFieldToTab('Root.Preview', $this->previewTab());
        }

        return $fields;
    }

    /**
     * Base models always available
     * 
     * @return array
     */
    public function getBaseModels()
    {
        return array(
            'Recipient' => 'Member',
            'Sender' => 'Member',
            // SiteConfig could be overidden is some context, so store config
            // under another name
            'Config' => 'SiteConfig'
        );
    }

    /**
     * An map of Name => Class
     *
     * @return array
     */
    public function getAvailableModels()
    {
        $extraModels = explode(',', $this->ExtraModels);
        $arr         = $this->getBaseModels();
        foreach ($extraModels as $extraModel) {
            $arr[$extraModel] = $extraModel;
        }
        return $arr;
    }

    /**
     * Get an email template by code
     * 
     * @param string $code
     * @return EmailTemplate
     */
    public static function getByCode($code)
    {
        return EmailTemplate::get()->filter('Code', $code)->first();
    }

    public function onBeforeWrite()
    {
        if ($this->Code) {
            $filter     = new URLSegmentFilter;
            $this->Code = $filter->filter($this->Code);
        }

        parent::onBeforeWrite();
    }

    /**
     * Provide content for the Preview tab
     * 
     * @return \Tab
     */
    protected function previewTab()
    {
        $tab = new Tab('Preview');

        $previewLink = '/admin/emails/EmailTemplate/PreviewEmail/?id='.$this->ID;
        $iframe      = new LiteralField('iframe',
            '<iframe src="'.$previewLink.'" style="width:600px;background:#fff;min-height:500px;vertical-align:top"></iframe>');
        $tab->push($iframe);

        return $tab;
    }

    /**
     * Returns an instance of MandrillEmail with the content of the template
     * 
     * @return \MandrillEmail
     */
    public function getEmail()
    {
        $email = new MandrillEmail();
        if ($this->Template) {
            $email->setTemplate($this->Template);
        }
        if ($this->Theme) {
            $email->setTheme($this->Theme);
        }
        $email->setSubject($this->Title);
        $email->setBody($this->Content);
        return $email;
    }

    /**
     * Get rendered body from Email
     *
     * @return string
     */
    public function renderTemplate()
    {
        $email = $this->getEmail();

        $html = $email->getRenderedBody();

        return (string) $html;
    }
}