<?php

namespace MicroUnit\Mock;

class Mock
{
    private object $mockInstance;

    public function __construct(object $mockInstance)
    {
        $this->mockInstance = $mockInstance;
    }

    /** @return object a new instance of the mocked class */
    public function getInstance(): object
    {
        return clone $this->mockInstance;
    }
}
