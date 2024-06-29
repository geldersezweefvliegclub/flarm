<?php
const DEBUG = true;

const SERVER_URL = 'https://helios.mydomain.com';
const SERVER_USER_NAME = 'user';
const SERVER_PASSWORD = 'password';
$BYPASS_TOKEN = 'key';

const LOGIN_URL = '/Login/Login?token=$BYPASS_TOKEN';
const VLIEGTUIGEN_GET_ALL_OBJECTS_URL = '/Vliegtuigen/GetObjects?VELDEN=ID,FLARMCODE,REG_CALL';
const VLIEGTUIGEN_AANMELDEN = '/AanwezigVliegtuigen/Aanmelden';

const START_OPSLAAN = '/Startlijst/SaveObject';
const START_OPHALEN = '/Startlijst/GetObject';

const STARTS_GET_ALL_OPEN_URL = '/Startlijst/GetObjects?OPEN_STARTS=true';
//for connecting to OGN APRS
const SERVER = "aprs.glidernet.org";
const PORT = 14580;
const MYCALL = "OGN_Callsign";
const PASSCODE = -1;
/*
http://www.aprs-is.net/javAPRSFilter.aspx  for filter information.  This filter is the range filter.
r/lat/lon/dist
Pass posits and objects within dist km from lat/lon.
lat and lon are signed decimal degrees, i.e. negative for West/South and positive for East/North.
*/
const FILTER = "r/52.06/5.94/15";


const PROGRAM_START_TIME_HOUR = 9;
const PROGRAM_END_TIME_HOUR = 22;


const REFRESH_FROM_AIRCRAFT_DB_MINUTES = 60;
const REFRESH_FROM_STARTS_DB_MINUTES = 1;

const DELAYED_LANDING_MINUTES = 1;