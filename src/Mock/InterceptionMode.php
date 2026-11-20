<?php

namespace MicroUnit\Mock;

/** How a generated mock method behaves. */
enum InterceptionMode
{
    /** Record the call and return the planned (or default) value. */
    case Intercept;
    /** Record the call and run the real implementation. */
    case Original;
    /** Record the call, run the real implementation, then return the planned value. */
    case OriginalThenPlan;
    /** Static abstract method that must exist for the class to be valid; always throws. */
    case StaticStub;
}
