<?php
/*
 * b2evolution - http://b2evolution.net/
 *
 * Copyright (c) 2003-2004 by Francois PLANQUE - http://fplanque.net/
 * Released under GNU GPL License - http://b2evolution.net/about/license.html
 */

/**
 * TRANSLATE!
 *
 * Translate a text to the desired locale (b2evo localization only)
 * or to the current locale
 * 
 * {@internal T_(-)}}
 * 
 * @param string String to translate, '' to get language file info (as in gettext spec)
 * @param string locale to translate to, '' to use current locale (basic gettext does only support '')
 */
if( ($use_l10n == 1) && function_exists('_') )
{	// We are going to use GETTEXT

	function T_( $string, $req_locale = '' )
	{
		global $current_messages;
		
		if( empty( $req_locale ) || $req_locale == $current_messages )
		{	// We have not asked for a different locale than the currently active one:
			return _($string);
		}
		// We have asked for a funky locale... we'll get english instead:
		return $string;
	}

}
elseif( $use_l10n == 2 )
{	// We are going to use b2evo localization:

	function T_( $string, $req_locale = '' )
	{
		global $trans, $current_locale, $locales;

		// By default we use the current locale:
		if( empty($req_locale) ) $req_locale = $current_locale;
		
		$messages = $locales[$req_locale]['messages'];
		
		$search = str_replace( "\n", '\n', $string );
		$search = str_replace( "\r", '', $search );
		// echo "Translating ", $search, " to $messages<br />";
		
		#echo locale_messages($req_locale); exit;
		if( !isset($trans[ $messages ] ) )
		{	// Translations for current locale have not yet been loaded:
			// echo 'LOADING', dirname(__FILE__). '/../locales/'. $messages. '/_global.php';
			@include_once dirname(__FILE__). '/../locales/'. $messages. '/_global.php';
			if( !isset($trans[ $messages ] ) )
			{	// Still not loaded... file doesn't exist, memorize that no translation are available
				// echo 'file not found!'; 
				$trans[ $messages ] = array();
			}
		}
				
		if( isset( $trans[ $messages ][ $search ] ) )
		{	// If the string has been translated:
			return $trans[ $messages ][ $search ];
		}
		
		// echo "Not found!";
		
		// Return the English string:
		return $string;
	}

}
else
{	// We are not localizing at all:

	function T_( $string, $req_locale = '' )
	{	
		return $string;
	}

}

/**
 * Temporarily switch to another locale
 *
 * Calls can be nested.
 *
 * {@internal locale_temp_switch(-)}}
 *
 * @param string locale to activate
 */
function locale_temp_switch( $locale )
{
	global $saved_locale, $current_locale;
	if( !isset( $saved_locale ) ) $saved_locale = array();
	array_push( $saved_locale, $current_locale );
	locale_activate( $locale );
}

/**
 * Restore the locale in use before the switch
 *
 * {@internal locale_restore_previous(-)}}
 */
function locale_restore_previous()
{
	global $saved_locale;
	$locale = array_pop( $saved_locale );
	locale_activate( $locale );
}

/*
 * locale_activate(-)
 *
 * returns true if locale has been changed
 *
 * @param string locale to activate
 */
function locale_activate( $locale )
{
	global $use_l10n, $locales, $current_locale, $current_messages, $current_charset, $weekday, $month;

	if( $locale == $current_locale )
	{
		return false;
	}

	// Memorize new locale:
	$current_locale = $locale;
	// Memorize new charset:
	$current_charset = $locales[ $locale ][ 'charset' ];
	$current_messages = $locales[ $locale ][ 'messages' ];

	// Activate translations in gettext:
	if( ($use_l10n == 1) && function_exists( 'bindtextdomain' ) )
	{	// Only if we war eusing GETTEXT ( if not, look into T_(-) ...)
		# Activate the locale->language in gettext:
		putenv( 'LC_ALL='. $current_messages );
		// Note: default of safe_mode_allowed_env_vars is "PHP_ ", 
		// so you need to add "LC_" by editing php.ini. 

		
		# Activate the charset for conversions in gettext:
		if( function_exists( 'bind_textdomain_codeset' ) )
		{	// Only if this gettext supports code conversions
			bind_textdomain_codeset( 'messages', $current_charset );
		}
	}
	
	# Set locale for default language:
	# This will influence the way numbers are displayed, etc.
	// We are not using this right now, the default 'C' locale seems just fine
	// setlocale( LC_ALL, $locale );
	
	# Use this to check locale:
	// echo setlocale( LC_ALL, 0 );
	
	return true;
}


/*
 * locale_by_lang(-)
 *
 * Find first locale matching lang
 */
function locale_by_lang( $lang )
{
	global $locales, $default_locale;
	
	foreach( $locales as $locale => $locale_params )
	{
		if( substr( $locale, 0 ,2 ) == $lang )
		{	// found first matching locale
			return $locale;
		}
	}

	// Not found...
	return $default_locale;
}

/**
 * Displays/Returns the current locale. (for backward compatibility)
 *
 * {@internal locale_lang(-)}}
 *
 * @param boolean true (default) if we want it to be outputted
 * @return string current locale, if $disp = false
 */
function locale_lang( $disp = true )
{
	global $current_locale;

	if( $disp )
		echo $current_locale;
	else
		return $current_locale;
}

/**
 * Returns the charset of the current locale
 *
 * {@internal locale_charset(-)}}
 */
function locale_charset( $disp = true )
{
	global $current_charset;
	
	if( $disp )
		echo $current_charset;
	else
		return $current_charset;
}

/**
 * Returns the current locale's default date format 
 *
 * {@internal locale_datefmt(-)}}
 */
function locale_datefmt()
{
	global $locales, $current_locale;
	
	return $locales[$current_locale]['datefmt'];
}

/**
 * Returns the current locale's default time format 
 *
 * {@internal locale_timefmt(-)}}
 */
function locale_timefmt()
{
	global $locales, $current_locale;
	
	return $locales[$current_locale]['timefmt'];
}


/**
 * Template function: Display locale flag
 *
 * {@internal locale_flag(-)}}
 *
 * @param string locale to use, '' for current
 * @param string collection name (subdir of img/flags)
 * @param string name of class for IMG tag
 * @param string deprecated HTML align attribute
 */
function locale_flag( $locale = '', $collection = 'w16px', $class = 'flag', $align = '' )
{
	global $locales, $current_locale, $core_dirout, $img_subdir, $img_url;
	
	if( empty($locale) ) $locale = $current_locale; 

	// extract flag name:
	$country = substr( $locale, 3, 2 );
	
	if( ! is_file(dirname(__FILE__).'/'.$core_dirout.'/'.$img_subdir.'/flags/'.$collection.'/'.$country.'.gif') )
	{	// File does not exist
		$country = 'default';
	}
	echo '<img src="'.$img_url.'/flags/'.$collection.'/'.$country.'.gif" alt="'. $locales[$locale]['name']. '" border="1" ';
	if( !empty( $class ) ) echo ' class="', $class, '"';
	if( !empty( $align ) ) echo ' align="', $align, '"';
	echo '/> ';

}

/*
 * locale_options(-)
 *
 *	Outputs a <option> set with default locale selected
 *
 * was: lang_options(-)
 *
 */
function locale_options( $default = '' )
{
	global $locales, $default_locale;
	
	if( !isset( $default ) ) $default = $default_locale;
	
	foreach( $locales as $this_localekey => $this_locale )
		if( $this_locale['enabled'] || $this_localekey == $default )
		{
			echo '<option value="'. $this_localekey. '"';
			if( $this_localekey == $default ) echo ' selected="selected"';
			echo '>'. T_($this_locale['name']). '</option>';
		}
	
}


/**
 * Detect language from HTTP_ACCEPT_LANGUAGE
 *
 * {@internal locale_from_httpaccept(-)}}
 *
 * @author blueyed
 *
 * @return locale made out of HTTP_ACCEPT_LANGUAGE or $default_locale, if no match
 */
function locale_from_httpaccept()
{
	global $locales, $default_locale;
	if( isset($_SERVER['HTTP_ACCEPT_LANGUAGE']) )
	{
		// echo $_SERVER['HTTP_ACCEPT_LANGUAGE'];
		// pre_dump($_SERVER['HTTP_ACCEPT_LANGUAGE'], 'http_accept_language');
		$text = array();
		// look for each language in turn in the preferences, which we saved in $langs
		foreach( $locales as $localekey => $locale ) 
		{
			if( ! $locale['enabled'] )
			{	// We only want to use activated locales
				continue;
			}
			// echo '<br />checking ', $localekey, ' for HTTP_ACCEPT_LANGUAGE ';
			$checklang = substr($localekey, 0, 2);
			$pos = strpos( $_SERVER['HTTP_ACCEPT_LANGUAGE'], $checklang );
			if( $pos !== false )
			{
				// echo '*';
				$text[] = str_pad( $pos, 3, '0', STR_PAD_LEFT ). '-'. $checklang. '-'. $localekey;
			}
		}
		if( sizeof($text) != 0 )
		{
			sort( $text );
			// pre_dump( $text );
			// the preferred locale/language should be in $text[0]
			if( preg_match('/\d\d\d\-([a-z]{2})\-(.*)/', $text[0], $matches) )
			{
				return $matches[2];
			}
		}
	}
	return $default_locale;
}

/**
 * user sort function to sort locales by priority
 *
 * 1 is highest priority.
 *
 */
function locale_priosort( $a, $b )
{
	return $a['priority'] - $b['priority'];
}

/**
 * load locales from DB into $locales array. Also sets $default_locale.
 *
 * FP: I edited this because I got PHP error notices
 */
function locale_overwritefromDB()
{
	global $tablelocales, $DB, $locales, $default_locale;
	
	$priocounter = 0;
	// $usedprios = array(0);  // FP: what do we need this for?
	
	$query = 'SELECT
						loc_locale, loc_charset, loc_datefmt, loc_timefmt, loc_name, 
						loc_messages, loc_priority, loc_enabled
						FROM '. $tablelocales;
	$rows = $DB->get_results( $query, ARRAY_A );
	if( count( $rows ) ) foreach( $rows as $row )
	{	// Loop through loaded locales:
	
		while( in_array($row['loc_priority'], $usedprios) )
		{ // find next available priority
			$priocounter++;
			$row['loc_priority'] = $priocounter; // FP: Modifying the rowset is a BAD practice
			// FP: why change the saved priority here? (extra processing for what value?)
		}
		// $usedprios[] = $row['loc_priority'];
				
		$locales[ $row['loc_locale'] ] = array(
																			'charset'  => $row[ 'loc_charset' ],
																			'datefmt'  => $row[ 'loc_datefmt' ],
																			'timefmt'  => $row[ 'loc_timefmt' ],
																			'name'     => $row[ 'loc_name' ],
																			'messages' => $row[ 'loc_messages' ],
																			'priority' => $row[ 'loc_priority' ],
																			'enabled'  => $row[ 'loc_enabled' ],
																			'fromdb'   => 1
																		);
	}
	
	// set default priorities, if nothing was set in DB
	// newly added locales (in conf/_locale.php) will get prio 1,
	// equal priorities will be shifted down.
	if( count($rows) != count($locales) )
	{ // we have locales from conf file that need a priority
		ksort( $locales );
		foreach( $locales as $lkey => $lval ) 
		{	// Loop through memomry locales:
			if( !isset($lval['priority']) )
			{	// Found one that has no assigned priority

				// Priocounter already has max value
				// while( in_array( $locales[$lkey]['priority'], $usedprios ) )
				//{
					$priocounter++;
					$locales[$lkey]['priority'] = $priocounter;
				//}
				// $usedprios[] = $locales[$lkey]['priority'];
			}
		}
	}
	
	// sort by priority
	uasort( $locales, 'locale_priosort' );
	
	// overwrite default_locale from DB settings - if enabled.
	// Checks also if previous $default_locale is enabled. Defaults to en-EU, even if not enabled.
	$locale_fromdb = get_settings('default_locale');
	if( $locale_fromdb  )
	{
		if( $locales[$locale_fromdb]['enabled'] )
			$default_locale = $locale_fromdb;
		elseif( !$locales[$default_locale]['enabled'] )
			$default_locale = 'en-EU';
		
	}
}


/**
 * write $locales array to DB table
 *
 * @author blueyed
 */
function locale_updateDB()
{
	global $locales, $tablelocales, $DB;
	
	$templocales = $locales;
			
	$lnr = 0;
	foreach( $_POST as $pkey => $pval ) if( preg_match('/loc_(\d+)_(.*)/', $pkey, $matches) )
	{
		$lfield = $matches[2];
			
		if( $matches[1] != $lnr )
		{ // we have a new locale
			$lnr = $matches[1];
			$plocale = $pval;
			
			// checkboxes default to 0
			$templocales[ $plocale ]['enabled'] = 0;
		}
		elseif( $lnr != 0 )  // be sure to have catched a locale before
		{
			$templocales[ $plocale ][$lfield] = remove_magic_quotes( $pval );
		}
		
	}
	
	$locales = $templocales;
	
	$query = "REPLACE INTO $tablelocales ( loc_locale, loc_charset, loc_datefmt, loc_timefmt, loc_name, loc_messages, loc_priority, loc_enabled ) VALUES ";
	foreach( $locales as $localekey => $lval )
	{
		if( empty($lval['messages']) )
		{ // if not explicit messages file is given we'll translate the locale
			$lval['messages'] = strtr($localekey, '-', '_');
		}
		$query .= "(
		'$localekey',
		'{$lval['charset']}',
		'{$lval['datefmt']}',
		'{$lval['timefmt']}',
		'{$lval['name']}',
		'{$lval['messages']}',
		'{$lval['priority']}',
		'{$lval['enabled']}'
		), ";
	}
	$query = substr($query, 0, -2);
	$q = $DB->query($query);
	
	return (bool)$q;
}


?>