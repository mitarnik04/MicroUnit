<?php

namespace MicroUnit\Mock;

enum ReturnPlanType
{
    case Fixed;
    case Sequence;
    case Callback;
    case Map;
    case Throwable;
}
