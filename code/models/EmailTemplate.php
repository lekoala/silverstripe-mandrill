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
    private static $translate = array(
        'Title', 'Content', 'Callout'
    );

    public function getCMSFields()
    {
        $fields = parent::getCMSFields();
        $config = EmailTemplate::config();

        if ($config->allow_configure_template || Permission::check('ADMIN')) {
            $templates = MandrillEmail::getAvailablesTemplates();
            $fields->replaceField('Template',
                new DropdownField('Template', 'Template', $templates));
        } else {
            $fields->removeByName('Template');
        }

        if ($config->allow_configure_theme || Permission::check('ADMIN')) {
            $themes = MandrillEmail::getAvailableThemes();
            $fields->replaceField('Theme',
                new DropdownField('Theme', 'Theme',
                array_combine($themes, $themes)));
        } else {
            $fields->removeByName('Theme');
        }
        $objectsSource = array();
        $dataobjects   = ClassInfo::subclassesFor('DataObject');
        foreach ($dataobjects as $dataobject) {
            if ($dataobject == 'DataObject') {
                continue;
            }
            $objectsSource[$dataobject] = $dataobject;
        }

        // form-extras integration
        if (class_exists('TableField')) {
            $fields->replaceField('ExtraModels',
                $extraModels = new TableField('ExtraModels', 'Extra Models'));
            $extraModels->setHeaders(array('Name', 'Model'));
            $extraModels->setColumnValues('Model', $objectsSource);
        }


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

        $baseFields = array(
            'To', 'Cc', 'Bcc', 'From', 'Subject', 'Body', 'BaseURL', 'Controller'
        );
        foreach ($baseFields as $baseField) {
            $content .= $baseField.', ';
        }
        $content = trim($content, ', ').'<br/><br/>';

        $models = $this->getAvailableModels();
        foreach ($models as $name => $model) {
            $props = Config::inst()->get($model, 'db');
            if (!$props) {
                continue;
            }
            $content .= '<strong>'.$name.' ('.$model.') :</strong><br/>';
            foreach ($props as $fieldName => $fieldType) {
                $content .= $fieldName.', ';
            }
            $content = trim($content, ', ').'<br/>';
        }
		$content .= "<div class='message info'>" . _t('EmailTemplate.ENCLOSEFIELD','To escape a field from surrounding text, you can enclose it between brackets, eg: {$CurrentMember.FirstName}.') . '</div>';
		
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
     * These models are defined in MandrillEmail::templateData()
     * 
     * @return array
     */
    public function getBaseModels()
    {
        return array(
            'CurrentMember' => 'Member',
            'Recipient' => 'Member',
            'Sender' => 'Member',
            'Config' => 'SiteConfig'
        );
    }

    /**
     * A map of Name => Class
     * 
     * @return array
     */
    public function getExtraModelsAsArray()
    {
        $extraModels = $this->ExtraModels ? json_decode($this->ExtraModels) : array();
        $arr         = $this->getBaseModels();
        foreach ($extraModels as $extraModel) {
            if (!class_exists($extraModel->Model)) {
                continue;
            }
            $arr[$extraModel->Name] = $extraModel->Model;
        }
        return $arr;
    }

    /**
     * An map of Name => Class
     *
     * @return array
     */
    public function getAvailableModels()
    {
        $extraModels = $this->getExtraModelsAsArray();
        $arr         = $this->getBaseModels();
        return array_merge($arr, $extraModels);
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

        // Preview iframe
        $previewLink = '/admin/emails/EmailTemplate/PreviewEmail/?id='.$this->ID;
        $iframe      = new LiteralField('iframe',
            '<iframe src="'.$previewLink.'" style="width:600px;background:#fff;min-height:500px;vertical-align:top"></iframe>');
        $tab->push($iframe);

        if (class_exists('CmsInlineFormAction')) {
            // Test emails
            $compo  = new FieldGroup(
                $recipient = new TextField('SendTestEmail', ''),
                $action = new CmsInlineFormAction('doSendTestEmail', 'Send')
            );
			$recipient->setAttribute('placeholder', 'my@email.test');
            $tab->push(new HiddenField('EmailTemplateID', '', $this->ID));
            $tab->push(new HeaderField('SendTestEmailHeader', 'Send test email'));
            $tab->push($compo);
        }

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
        if ($this->Callout) {
            $email->setCallout($this->Callout);
        }
        if ($this->ExtraModels) {
            $models = $this->getExtraModelsAsArray();
            $email->setRequiredObjects($models);
        }
        $email->setParseBody(true);
        return $email;
    }

    /**
     * Get rendered body
     *
	 * @param bool $parse Should we parse variables or not?
     * @return string
     */
    public function renderTemplate($parse = false)
    {
        $email = $this->getEmail();
        $email->setParseBody($parse);
        $html  = $email->getRenderedBody();

        return (string) $html;
    }
}