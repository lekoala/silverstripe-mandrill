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
            new DropdownField('Theme', 'Theme', $themes));

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

        $fields->dataFieldByName('Code')->setAttribute('placeholder',
            _t('EmailTemplate.CODEPLACEHOLDER',
                'A unique code that will be used in code to retrieve the template, e.g.: my-email'));

        // Placeholder helper
        $fields->addFieldToTab('Root.Main', new HeaderField('PlaceholderHelperTitle', _t('EmailTemplate.AVAILABLEPLACEHOLDERSTITLE','Available placeholders')));
        $content = '';
        $models = $this->getAvailableModels();
        foreach($models as $name => $model) {
            $content .= '<strong>' . $name . ' (' . $model . ') :</strong><br/>';
            foreach(DataObject::database_fields($model) as $fieldName => $fieldType) {
                $content .= $fieldName . ', ';
            }
            $content = trim($content,', ') . '<br/>';
        }
        $fields->addFieldToTab('Root.Main', new LiteralField('PlaceholderHelper', $content));

        if ($this->ID) {
            $fields->addFieldToTab('Root.Preview', $this->previewTab());
        }

        return $fields;
    }

    public function getBaseModels() {
        return array(
            'Recipient' => 'Member',
            'Sender' => 'Member'
        );
    }

    public function getAvailableModels() {
        $extraModels = explode(',', $this->ExtraModels);
        $arr = $this->getBaseModels();
        foreach($extraModels as $extraModel) {
            $arr[$extraModel] = $extraModel;
        }
        return $arr;
    }

    public function onBeforeWrite()
    {
        if ($this->Code) {
            $filter     = new URLSegmentFilter;
            $this->Code = $filter->filter($this->Code);
        }

        parent::onBeforeWrite();
    }

    protected function previewTab()
    {
        $tab = new Tab('Preview');

        return $tab;
    }
}