<?php
/*! Do the extension credits
 * 
 */
$wgExtensionCredits['validextensionclass'][] = array(
       'name' => 'DnTaPaTa',
       'author' =>'Danny Havenith', 
       'url' => 'http://rurandom.org/DnTaPaTa', 
       'description' => 'Djembe notation system for MediaWiki'
);

$wgHooks['ParserFirstCallInit'][] = 'dtptParserInit';

$dtptDefaults = array (
	'countsperbeat' => 4,
	'beatspermeasure' => 4,
	'djembestyle' => 'djembe',
	'dununstyle' => 'djembe',
'times' => ''
);

// Hook our callback function into the parser
function dtptParserInit( Parser &$parser ) {
	// When the parser sees the <sample> tag, it executes
	// the efSampleRender function (see below)
	$parser->setHook( 'djembe', 'dtptRenderDjembe' );
	$parser->setHook( 'dunun', 'dtptRenderDunun');
	return true;
}

function dtptTokenToMarkup( $token)
{
	return "{{" . $token . "}}";	
}


/*!
 * Create a line with counts for the top of each bar.
 * 
 * This will generate a line that looks something like this:
 * |1   2   3   4   |1   2   3   4   |
 * Such a line is added to the top of a measure.
 */
function dtptCounterLine( $counts, $countsPerBeat, $beatsPerMeasure)
{
	$beat_counter = 1;
	$countsPerMeasure = $countsPerBeat * $beatsPerMeasure;
	$wikitext = '';
	for ($i = 0; $i < $counts; ++$i)
	{
		if ($i % $countsPerMeasure == 0)
		{
			$style = "class='measure_start'|";
			$beat_counter = 1;
		}
		elseif (($i + 1)% $countsPerMeasure == 0)
		{
			$style = "class='measure_end'|";
		}
		else 
		{
			$style = '';
		}
		
		if ( $i%$countsPerBeat == 0)
		{
			$wikitext .= "||$style$beat_counter";
			++$beat_counter;
		}
		else
		{
			$wikitext .= "||$style&nbsp;";
		}
	}
	
	return $wikitext;
}

/*!
 * Adapt the type of a given value to become the same as that of a default value.
 * 
 * If the adaptation does not succeed (for instance when the default is of integer type
 * and the given value can't be converted to integer), this function will return the default
 * value.
 */
function dtptAdaptType( $value, $default)
{
	if (settype($value, gettype($default)))
	{
		return $value;
	}
	else
	{
		return $default;
	}
}

/*!
 * Create a full array of options given an array with a subset of options.
 * 
 * This function will check all possible options and if the corresponding option is 
 * present in array $args, will add that option to the result.
 * 
 * If the given option is not found in $args, or if its type is not the same as the default
 * option, this function will add the default option to the resulting array.
 */
function dtptDetermineOptions( array $args)
{
	global $dtptDefaults;
	$result = array();
	foreach ($dtptDefaults as $key => $value) 
	{
		if (isset( $args[$key]))
		{
			$result[$key] = dtptAdaptType( $args[$key], $value);
		}
		else 
		{
			$result[$key] = $value;	
		}
	}
	return $result;	
}

/*!
 *  Render markup for a djembe notation line.
 *  
 *  This function splits the input on whitespace, turns every token (e.g. "Pa") into a template
 *  invocation (e.g. "{{Pa}}") and renders the result in a table cell.
 */
 function dtptRenderDjembe( $input, array $args, Parser $parser, PPFrame $frame ) 
 {
 	$options = dtptDetermineOptions( $args);
 	$djembeStyle = $options['djembestyle'];
	$tokens = preg_split('/\s/', $input, -1 , PREG_SPLIT_NO_EMPTY);
	$wikitext = "{|class='$djembeStyle'\n|-\n|&nbsp;";
	
	$wikitext .= dtptCounterLine(count( $tokens), $options['countsperbeat'], $options['beatspermeasure']);
	$times = $options['times'];
	if ($times)
	{
		$wikitext .= "||rowspan='2' class='times'| X$times";
	}
	$wikitext .= "\n|-\n|{{djembe}}";
	foreach ($tokens as $token) {
		$wikitext .= '||'. dtptTokenToMarkup($token);
	}
	$wikitext .= "\n|}";
	
	return $parser->recursiveTagParse( $wikitext, $frame );
}

/*!
 * Examine an array of tokens and try to determine the intended instrument.
 * 
 * This function will look for characters like 'S', 'K' or 'D' to determine 
 * the instrument type ('Sangban', 'Kenkeni' or 'Dununba' respectively).
 */
function dtptLookupInstrument( $tokens)
{
	$instruments = array(
		's' => 'Sangban',
		'k' => 'Kenkeni',
		'd' => 'Dununba',
		'x' => 'Bell'
	);

	foreach ($tokens as $token) 
	{
		$tokenLower = strtolower($token);
		if (array_key_exists($tokenLower, $instruments))
		{
			return $instruments[ $tokenLower];
		}
	}
	
	return 'Unknown';
}

function dtptDununMapping( $symbol)
{
	if ($symbol == '.')
	{
		return "{{Dun empty}}";
	}
	elseif (strtolower( $symbol) == 'x')
	{
		return "{{Dun bell}}";
	}
	elseif ( ctype_lower( $symbol))
	{
		return "{{Dun open}}";
	}
	else 
	{
		return "{{Dun closed}}";	
	}
}

function dtptRenderDunun( $input, array $args, Parser $parser, PPFrame $frame ) 
{
	$options = dtptDetermineOptions( $args);
	$dununStyle = $options['dununstyle'];
	// split into lines
	$lines = preg_split('/\n/', $input, -1, PREG_SPLIT_NO_EMPTY);
	
//	$wikitext = "{|class='$dununStyle'\n";
	
	$wikitext = "";
	$maxCount = 0;
	// for each line, determine instrument and symbols
	foreach ($lines as $line) 
	{
		$tokens = preg_split('/\s/', $line, -1 , PREG_SPLIT_NO_EMPTY);
		$maxCount = max( array( count($tokens), $maxCount));
		$instrument = dtptLookupInstrument($tokens);
		$markup = array_map( 'dtptDununMapping', $tokens);
		$wikitext .= "|-\n|class='instrument_name'|$instrument||" . implode( '||', $markup) . "\n";
	}
	
	// create a counter line
	$counts = "|-\n|&nbsp;" . dtptCounterLine( $maxCount, $options['countsperbeat'], $options['beatspermeasure']) . "\n";
	
	$wikitext = "{|class='$dununStyle'\n" . $counts . $wikitext . "|}";
	
	return $parser->recursiveTagParse( $wikitext, $frame );
}
