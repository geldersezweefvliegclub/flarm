<?php
const LOGIN_URL = 'Login/Login';
const VLIEGTUIGEN_GET_ALL_OBJECTS_URL = 'Vliegtuigen/GetObjects';
const VLIEGTUIGEN_AANMELDEN = 'AanwezigVliegtuigen/Aanmelden';

//for connecting to OGN APRS
const SERVER = "aprs.glidernet.org";
const PORT = 14580;
const MYCALL = "GEZC0";
const PASSCODE = -1;
/*
http://www.aprs-is.net/javAPRSFilter.aspx  for filter information.  This filter is the range filter.
r/lat/lon/dist
Pass posits and objects within dist km from lat/lon.
lat and lon are signed decimal degrees, i.e. negative for West/South and positive for East/North.
*/
const FILTER = "r/52.06/5.94/50";


