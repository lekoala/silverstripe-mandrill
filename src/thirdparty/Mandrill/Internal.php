<?php

class Mandrill_Internal
{

    private $master;

    public function __construct(Mandrill $master)
    {
        $this->master = $master;
    }
}
