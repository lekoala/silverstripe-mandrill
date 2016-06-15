<?php

/**
 * EmailImportTask
 *
 * Finds all *Email.ss templates and imports them into the CMS
 * @author lekoala
 */
class EmailImportTask extends BuildTask
{
    protected $title       = "Email import task";
    protected $description = "Finds all *Email.ss templates and imports them into the CMS, if they don't already exist.";

    public function run($request)
    {
        echo 'Run with ?clear=1 to clear empty database before running the task<br/>';
        echo 'Run with ?overwrite=soft|hard to overwrite templates that exists in the cms. Soft will replace template if not modified by the user, hard will replace template even if modified by user.<br/>';
        echo 'Run with ?templates=xxx,yyy to specify which template should be imported<br/>';
        echo 'Run with ?subsite=all|subsiteID to create email templates in all subsites (including main site) or only in the chosen subsite (if a subsite is active, it will be used by default).<br/>';
        echo 'Run with ?locales=fr,en to choose which locale to import.<br/>';
        echo '<strong>Remember to flush the templates/translations if needed</strong><br/>';
        echo '<hr/>';

        $overwrite         = $request->getVar('overwrite');
        $clear             = $request->getVar('clear');
        $templatesToImport = $request->getVar('templates');
        $importToSubsite   = $request->getVar('subsite');
        $chosenLocales     = $request->getVar('locales');

        // Normalize argument
        if ($overwrite && $overwrite != 'soft' && $overwrite != 'hard') {
            $overwrite = 'soft';
        }

        $subsites = array();
        if ($importToSubsite == 'all') {
            $subsites = Subsite::get()->map();
        } else if (is_numeric($importToSubsite)) {
            $subsites = array(
                $importToSubsite => Subsite::get()->byID($importToSubsite)->Title
            );
        }
        if (class_exists('Subsite') && Subsite::currentSubsiteID()) {
            DB::alteration_message("Importing to current subsite. Run from main site to import other subsites at once.",
                "created");
            $subsites = array();
        }
        if (!empty($subsites)) {
            DB::alteration_message("Importing to subsites : ".implode(',',
                    array_values($subsites)), "created");
        }

        if ($templatesToImport) {
            $templatesToImport = explode(',', $templatesToImport);
        }

        if ($clear == 1) {
            DB::alteration_message("Clear all email templates", "created");
            $emailTemplates = EmailTemplate::get();
            foreach ($emailTemplates as $emailTemplate) {
                $emailTemplate->delete();
            }
        }

        $emailTemplateSingl = singleton('EmailTemplate');

        $ignoredModules = self::config()->ignored_modules;
        if (!is_array($ignoredModules)) {
            $ignoredModules = array();
        }

        $locales = null;
        if (class_exists('Fluent') && Fluent::locale_names()) {
            if ($emailTemplateSingl->hasExtension('FluentExtension')) {
                $locales = array_keys(Fluent::locale_names());
                if ($chosenLocales) {
                    $arr     = explode(',', $chosenLocales);
                    $locales = array();
                    foreach ($arr as $a) {
                        if (strlen($a) == 2) {
                            $a = i18n::get_locale_from_lang($a);
                        }
                        $locales[] = $a;
                    }
                }
            }
        }

        $defaultLocale = i18n::get_locale();

        $templates = SS_TemplateLoader::instance()->getManifest()->getTemplates();
        foreach ($templates as $t) {
            $isOverwritten = false;

            // Emails in mysite/email are not properly marked as emails
            if (isset($t['mysite']) && isset($t['mysite']['email'])) {
                $t['email'] = $t['mysite']['email'];
            }

            // Should be in the /email folder
            if (!isset($t['email'])) {
                continue;
            }

            $filePath = $t['email'];
            $fileName = basename($filePath, '.ss');

            // Should end with *Email
            if (!preg_match('/Email$/', $fileName)) {
                continue;
            }

            $relativeFilePath      = str_replace(Director::baseFolder(), '',
                $filePath);
            $relativeFilePathParts = explode('/', trim($relativeFilePath, '/'));

            // Group by module
            $module = array_shift($relativeFilePathParts);

            // Ignore some modules
            if (in_array($module, $ignoredModules)) {
                continue;
            }

            array_shift($relativeFilePathParts); // remove /templates part
            $templateName = str_replace('.ss', '',
                implode('/', $relativeFilePathParts));

            $templateTitle = basename($templateName);

            // Create a default code from template name
            $code = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-',
                    $fileName));
            $code = preg_replace('/-email$/', '', $code);

            if (!empty($templatesToImport) && !in_array($code,
                    $templatesToImport)) {
                DB::alteration_message("Template with code <b>$code</b> was ignored.",
                    "repaired");
                continue;
            }

            $whereCode     = array(
                'Code' => $code
            );
            $emailTemplate = EmailTemplate::get()->filter($whereCode)->first();

            // Check if it has been modified or not
            $templateModified = false;
            if ($emailTemplate) {
                $templateModified = $emailTemplate->Created != $emailTemplate->LastEdited;
            }

            if (!$overwrite && $emailTemplate) {
                DB::alteration_message("Template with code <b>$code</b> already exists. Choose overwrite if you want to import again.",
                    "repaired");
                continue;
            }
            if ($overwrite == 'soft' && $templateModified) {
                DB::alteration_message("Template with code <b>$code</b> has been modified by the user. Choose overwrite=hard to change.",
                    "repaired");
                continue;
            }

            // Create a default title from code
            $title = explode('-', $code);
            $title = array_map(function ($item) {
                return ucfirst($item);
            }, $title);
            $title = implode(' ', $title);

            // Get content of the email
            $content = file_get_contents($filePath);

            // Analyze content to find incompatibilities
            $errors = array();
            if (strpos($content, '<% with') !== false) {
                $errors[] = 'Replace "with" blocks by plain calls to the variable';
            }
            if (strpos($content, '<% if') !== false) {
                $errors[] = 'If/else logic is not supported. Please create one template by use case or abstract logic into the model';
            }
            if (strpos($content, '<% loop') !== false) {
                $errors[] = 'Loops are not supported. Please create a helper method on the model to render the loop';
            }
            if (strpos($content, '<% sprintf') !== false) {
                $errors[] = 'You should not use sprintf to escape content, please use plain _t calls';
            }
            if (!empty($errors)) {
                echo "<div style='color:red'>Invalid syntax was found in '$relativeFilePath'. Please fix these errors before importing the template<ul>";
                foreach ($errors as $error) {
                    echo '<li>'.$error.'</li>';
                }
                echo '</ul></div>';
                continue;
            }

            // Parse language
            $collector = new i18nTextCollector;
            $entities  = $collector->collectFromTemplate($content, $fileName,
                $module);

            $translationTable = array();
            foreach ($entities as $entity => $data) {
                if ($locales) {
                    foreach ($locales as $locale) {
                        i18n::set_locale($locale);
                        if (!isset($translationTable[$entity])) {
                            $translationTable[$entity] = array();
                        }
                        $translationTable[$entity][$locale] = i18n::_t($entity);
                    }
                    i18n::set_locale($defaultLocale);
                } else {
                    $translationTable[$entity] = array($defaultLocale => i18n::_t($entity));
                }
            }

            $contentLocale = array();
            foreach ($locales as $locale) {
                $contentLocale[$locale] = $content;
            }
            foreach ($translationTable as $entity => $translationData) {
                $escapedEntity   = str_replace('.', '\.', $entity);
                $baseTranslation = null;

                foreach ($translationData as $locale => $translation) {
                    if (!$baseTranslation && $translation) {
                        $baseTranslation = $translation;
                    }
                    if (!$translation) {
                        $translation = $baseTranslation;
                    }
                    // This regex should match old and new style
                    $count                  = 0;
                    $contentLocale[$locale] = preg_replace("/<%(t | _t\(')".$escapedEntity."( |').*?%>/ums",
                        $translation, $contentLocale[$locale], -1, $count);
                    if (!$count) {
                        throw new Exception("Failed to replace $escapedEntity with translation $translation");
                    }
                }
            }

            if (!$emailTemplate) {
                $emailTemplate = new EmailTemplate;
            } else {
                $isOverwritten = true;
            }

            // Scan for extra models based on convention
            preg_match_all('/\$([a-zA-Z]+)\./ms',
                $contentLocale[$defaultLocale], $matches);
            $extraModels = array();
            if (!empty($matches) && !empty($matches[1])) {
                $arr = array_unique($matches[1]);
                foreach ($arr as $n) {
                    if (strtolower($n) === 'siteconfig') {
                        continue;
                    }
                    if (class_exists($n)) {
                        $extraModels[$n] = $n;
                    }
                }
            }

            // Apply content to email
            $this->assignContent($emailTemplate, $contentLocale[$defaultLocale]);

            if (!empty($locales)) {
                foreach ($locales as $locale) {
                    $this->assignContent($emailTemplate,
                        $contentLocale[$locale], $locale);
                }
            }

            // Title
            $emailTemplate->Title = $title;
            if (!empty($locales)) {
                // By convention, we store the translation under NameOfTheTemplateEmail.SUBJECT
                foreach ($locales as $locale) {
                    i18n::set_locale($locale);
                    $localeField = 'Title_'.$locale;
                    $entity      = $templateTitle.'.SUBJECT';
                    $translation = i18n::_t($entity);
                    if (!$translation) {
                        $translation = $title;
                        DB::alteration_message("No title found in $locale for $title. You should define $templateTitle.SUBJECT",
                            "error");
                    }
                    $emailTemplate->$localeField = $translation;

                    if (strpos($translation, '%s') !== false) {
                        echo '<div style="color:red">There is a %s in the title that should be replaced in locale '.$locale.'!</div>';
                    }

                    if ($locale == $defaultLocale) {
                        $emailTemplate->Title = $translation;
                    }
                }
                i18n::set_locale($defaultLocale);
            }

            // Other properties
            $emailTemplate->Code     = $code;
            $emailTemplate->Category = $module;
            if (class_exists('Subsite') && Subsite::currentSubsiteID()) {
                $emailTemplate->SubsiteID = Subsite::currentSubsiteID();
            }
            $emailTemplate->setExtraModelsAsArray($extraModels);
            // Write to main site or current subsite
            $emailTemplate->write();
            $this->resetLastEditedDate($emailTemplate->ID);

            // Loop through subsites
            if (!empty($importToSubsite)) {
                Subsite::$disable_subsite_filter = true;
                foreach ($subsites as $subsiteID => $subsiteTitle) {
                    $whereCode['SubsiteID'] = $subsiteID;

                    $subsiteEmailTemplate = EmailTemplate::get()->filter($whereCode)->first();

                    $emailTemplateCopy            = $emailTemplate;
                    $emailTemplateCopy->SubsiteID = $subsiteID;
                    if ($subsiteEmailTemplate) {
                        $emailTemplateCopy->ID = $subsiteEmailTemplate->ID;
                    } else {
                        $emailTemplateCopy->ID = 0; // New
                    }
                    $emailTemplateCopy->write();

                    $this->resetLastEditedDate($emailTemplateCopy->ID);
                }
            }

            if ($isOverwritten) {
                DB::alteration_message("Overwrote <b>{$emailTemplate->Code}</b>",
                    "created");
            } else {
                DB::alteration_message("Imported <b>{$emailTemplate->Code}</b>",
                    "created");
            }
        }
    }

    protected function resetLastEditedDate($ID)
    {
        return DB::query("UPDATE `EmailTemplate` SET LastEdited = Created WHERE ID = ".$ID);
    }

    protected function assignContent($emailTemplate, $content, $locale = null)
    {
        $baseField = 'Content';
        if ($locale) {
            $baseField .= '_'.$locale;
        }

        $cleanContent              = $this->cleanContent($content);
        $emailTemplate->$baseField = '';
        $emailTemplate->$baseField = $cleanContent;

        $dom = new DOMDocument;
        $dom->loadHTML(mb_convert_encoding('<div>'.$content.'</div>', 'HTML-ENTITIES', 'UTF-8'));

        // Look for nodes to assign to proper fields
        $fields = array('Content', 'Callout', 'SideBar');
        foreach ($fields as $field) {
            $localeField = $field;
            if ($locale) {
                $localeField .= '_'.$locale;
            }
            $node = $dom->getElementById($field);
            if ($node) {
                $cleanContent                = $this->cleanContent($this->getInnerHtml($node));
                $emailTemplate->$localeField = '';
                $emailTemplate->$localeField = $cleanContent;
            }
        }
    }

    protected function cleanContent($content)
    {
        $content = strip_tags($content,
            '<p><br><br/><div><img><a><span><ul><li><strong><em><b><i><blockquote><h1><h2><h3><h4><h5><h6>');

        $content = str_replace("’", "'", $content);

        if (class_exists('\\ForceUTF8\\Encoding')) {
            $content = \ForceUTF8\Encoding::fixUTF8($content);
        }

        return $content;
    }

    protected function getInnerHtml(DOMElement $node)
    {
        $innerHTML = '';
        $children  = $node->childNodes;
        foreach ($children as $child) {
            $innerHTML .= $child->ownerDocument->saveXML($child);
        }

        return $innerHTML;
    }
}