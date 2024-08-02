<?php

enum GliderStatus: string
{
    case Unknown = "Unknown";
    case Landing = "Landing";
    case On_Ground = "Grond";
    case TakeOff = "Starting";
    case Flying = "Vliegt";
}