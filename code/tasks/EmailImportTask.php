<?php

/**
 * EmailImportTask
 *
 * Finds all *Email.ss templates and imports them into the CMS
 * @author lekoala
 */
class EmailImportTask extends BuildTask
{
    protected $title = "Email import task";
    protected $description = "Finds all *Email.ss templates and imports them into the CMS, if they don't already exist.";

    public function run($request)
    {
        echo 'Run with ?clear=1 to clear empty database before running the task<br/>';
        echo 'Run with ?overwrite=1 to overwrite templates that exists in the cms<br/>';
        echo '<hr/>';

        $overwrite = $request->getVar('overwrite');
        $clear     = $request->getVar('clear');

        if ($clear == 1) {
            echo '<strong>Clear all email templates</strong><br/>';
            $emailTemplates = EmailTemplate::get();
            foreach ($emailTemplates as $emailTemplate) {
                $emailTemplate->delete();
            }
        }

        $o = singleton('EmailTemplate');

        $ignoredModules = self::config()->ignored_modules;
        if(!is_array($ignoredModules)) {
            $ignoredModules = array();
        }

        $locales = null;
        if(class_exists('Fluent') && Fluent::locale_names()) {
            if($o->hasExtension('FluentExtension')) {
                $locales = array_keys(Fluent::locale_names());
            }
        }

        $defaultLocale = i18n::get_locale();

        $templates = SS_TemplateLoader::instance()->getManifest()->getTemplates();
        foreach ($templates as $t) {
            $isOverwritten = false;

            // Should be in the /email folder
            if (!isset($t['email'])) continue;

            $filePath = $t['email'];
            $fileName = basename($filePath, '.ss');

            // Should end with *Email
            if (!preg_match('/Email$/', $fileName)) continue;

            $relativeFilePath      = str_replace(Director::baseFolder(), '',
                $filePath);
            $relativeFilePathParts = explode('/', trim($relativeFilePath, '/'));

            // Group by module
            $module = array_shift($relativeFilePathParts);

            // Ignore some modules
            if (in_array($module, $ignoredModules)) continue;

            array_shift($relativeFilePathParts); // remove /templates part
            $templateName = str_replace('.ss', '',
                implode('/', $relativeFilePathParts));

            $templateTitle = basename($templateName);

            // Create a default code from template name
            $code = strtolower(preg_replace('/([a-zA-Z])(?=[A-Z])/', '$1-',
                    $fileName));
            $code = preg_replace('/-email$/', '', $code);

            $emailTemplate = EmailTemplate::get()->filter('Code', $code)->first();
            if (!$overwrite && $emailTemplate) {
                echo "<div style='color:blue'>Template with code '$code' already exists.</div>";
                continue;
            }

            // Create a default title from code
            $title = explode('-', $code);
            $title = array_map(function($item) {
                return ucfirst($item);
            }, $title);
            $title = implode(' ', $title);

            // Get content of the email
            $content = file_get_contents($filePath);

            // Analyze content to find incompatibilities
            $errors = array();
            if(strpos($content, '<% with') !== false) {
                $errors[] = 'Replace "with" blocks by plain calls to the variable';
            }
            if(strpos($content, '<% if') !== false) {
                $errors[] = 'If/else logic is not supported. Please create one template by use case or abstract logic into the model';
            }
            if(strpos($content, '<% loop') !== false) {
                $errors[] = 'Loops are not supported. Please create a helper method on the model to render the loop';
            }
            if(!empty($errors)) {
                echo "<div style='color:red'>Invalid syntax was found in '$relativeFilePath'. Please fix these errors before importing the template<ul>";
                foreach($errors as $error) {
                    echo '<li>' . $error . '</li>';
                }
                echo '</ul></div>';
                continue;
            }

            // Parse language
            $collector = new i18nTextCollector;
            $entities = $collector->collectFromTemplate($content, $fileName, $module);

            $translationTable = array();
            foreach($entities as $entity => $data) {
                if($locales) {
                    foreach($locales as $locale) {
                        i18n::set_locale($locale);
                        if(!isset($translationTable[$entity])) {
                            $translationTable[$entity] = array();
                        }
                        $translationTable[$entity][$locale] = i18n::_t($entity);
                    }
                    i18n::set_locale($defaultLocale);
                }
                else {
                    $translationTable[$entity] = array($defaultLocale => i18n::_t($entity));
                }
            }

            $contentLocale = array();
            foreach($locales as $locale) {
                $contentLocale[$locale] = $content;
            }
            foreach($translationTable as $entity => $translationData) {
                $escapedEntity = str_replace('.', '\.', $entity);
                $baseTranslation = null;

                foreach($translationData as $locale => $translation) {
                    if(!$baseTranslation && $translation) {
                        $baseTranslation = $translation;
                    }
                    if(!$translation) {
                        $translation = $baseTranslation;
                    }
                    // This regex should match old and new style
                    $contentLocale[$locale] = preg_replace("/<%(t | _t\(')".$escapedEntity.".*?%>/ms", $translation, $contentLocale[$locale]);
                }
            }

            // Clean content
            $contentLocale = array_map(function($item) {
                return strip_tags($item,'p,br,div,img,a,span');
            }, $contentLocale);

            if (!$emailTemplate) {
                $emailTemplate = new EmailTemplate;
            } else {
                $isOverwritten = true;
            }

            $emailTemplate->Content  = $contentLocale[$defaultLocale];
            if(!empty($locales)) {
                foreach($locales as $locale) {
                    $field = 'Content_' . $locale;
                    $emailTemplate->$field = $contentLocale[$locale];
                }
            }
            $emailTemplate->Title    = $title;
            if(!empty($locales)) {
                // By convention, we store the translation under NameOfTheTemplateEmail.SUBJECT
                foreach($locales as $locale) {
                    i18n::set_locale($locale);
                    $field = 'Title_' . $locale;
                    $entity = $templateTitle . '.SUBJECT';
                    $translation = i18n::_t($entity);
                    if(!$translation) {
                        $translation = $title;
                    }
                    $emailTemplate->$field = $translation;
                    if($locale == $defaultLocale) {
                        $emailTemplate->Title = $translation;
                    }
                }
                 i18n::set_locale($defaultLocale);
            }
            $emailTemplate->Code     = $code;
            $emailTemplate->Category = $module;

            $emailTemplate->write();

            if ($isOverwritten) {
                echo "<div style='color:orange'>Overwrote {$emailTemplate->Title}</div>";
            } else {
                echo "<div style='color:green'>Imported {$emailTemplate->Title}</div>";
            }
        }
    }
}