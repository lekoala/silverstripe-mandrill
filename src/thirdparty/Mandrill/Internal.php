<?php

class Mandrill_Internal
{
    protected $master;

    private $master;

    public function __construct(Mandrill $master)
    {
        $this->master = $master;
    }
}
