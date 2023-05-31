<?php

use LeKoala\Mandrill\MandrillHelper;

if (class_exists(MandrillHelper::class)) {
    MandrillHelper::init();
}
