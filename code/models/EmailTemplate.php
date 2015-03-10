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
        $fields->replaceField('Template', new DropdownField('Template','Template',$templates));

        $themes = MandrillEmail::getAvailableThemes();
        $fields->replaceField('Theme', new DropdownField('Theme','Theme',$themes));

        // form-extras integration
        if(class_exists('ComboField')) {
            $categories = EmailTemplate::get()->column('Category');
            $fields->replaceField('Category', new ComboField('Category', 'Category', $categories));
        }

        if($this->ID) {
            $fields->addFieldToTab('Root.Preview', $this->previewTab());
        }

        return $fields;
    }

    protected function previewTab() {
        $tab = new Tab('Preview');

        return $tab;
    }
}