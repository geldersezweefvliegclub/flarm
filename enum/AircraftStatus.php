<?php

enum GliderStatus: string
{
    case NoIntrest = "NoIntrest";
    case Unknown = "Unknown";
    case Circuit = "Circuit";
    case Landing = "Landing";
    case On_Ground = "Grond";
    case TakeOff = "Starting";
    case Flying = "Vliegt";
}