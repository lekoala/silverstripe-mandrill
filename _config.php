<?php

use LeKoala\Mandrill\MandrillHelper;

if (!class_exists('SS_Object')) class_alias('Object', 'SS_Object');

MandrillHelper::init();
