<?php

namespace MicroUnit\Mock;

enum ReturnPlanType
{
    case FIXED;
    case SEQUENCE;
    case CALLBACK;
    case EXCEPTION;
}
