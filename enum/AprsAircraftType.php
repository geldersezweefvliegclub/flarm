<?php

enum AprsAircraftType: string {
  case Glider = "1";
  case Tow_Plane = "2";
  case Helicopter = "3";
  case Parachute = "4";
  case Drop_Plane = "5";
  case Hang_Glider = "6";
  case Para_Glider = "7";
  case Powered_Aircraft = "8";
  case Jet_Aircraft = "9";
  case UFO = "a";
  case Balloon = "b";
  case Airship = "c";
  case UAV = "d";
  case Ground_Support = "e";
  case Static_Object = "f";
  case undefined = "0";
}
