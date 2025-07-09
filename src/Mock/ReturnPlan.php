<?php

namespace MicroUnit\Mock;

use Exception;

class ReturnPlan
{
    private ReturnPlanType $type;
    private mixed $data;
    private array $sequence;

    public function __construct(ReturnPlanType $type, mixed $data)
    {
        $this->type = $type;
        $this->data = $data;
        if ($type === ReturnPlanType::SEQUENCE) {
            $this->sequence = $data;
        }
    }

    public function execute(array $args): mixed
    {
        return match ($this->type) {
            ReturnPlanType::FIXED => $this->data,
            ReturnPlanType::SEQUENCE => array_shift($this->sequence),
            ReturnPlanType::CALLBACK => ($this->data)(...$args),
            ReturnPlanType::THROWABLE => throw $this->data,
            default => null,
        };
    }
}
