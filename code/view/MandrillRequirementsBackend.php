<?php

/**
 * MandrillRequirementsBackend
 *
 * A requirements backend that does nothing for rendering templates without extra stuff
 * Yeah :-)
 *
 * @author lekoala
 */
class MandrillRequirementsBackend extends Requirements_Backend
{

    public function includeInHTML($templateFile, $content)
    {
        return $content;
    }
}
