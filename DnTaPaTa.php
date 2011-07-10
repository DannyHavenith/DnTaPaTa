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

/*!
 * Our option defaults.
 */
$dtptDefaults = array (
	'countsperbeat' => 4,
	'beatspermeasure' => 4,
	'djembestyle' => 'djembe',
	'dununstyle' => 'djembe',
	'ensemblestyle' => 'djembe',
	'times' => ''
);

/*!
 * Are we inside an ensemble tag.
 */
$dtptInEnsemble = false;
$dtptMarkupBuffer = '';

/*!
 * Hook into the mediawiki parser.
 * 
 * This function registers the tags <djembe>, <dunun> and <ensemble>.
 */
function dtptParserInit( Parser &$parser ) {
	$parser->setHook( 'djembe', 'dtptRenderDjembe' );
	$parser->setHook( 'dunun', 'dtptRenderDunun');
	$parser->setHook( 'ensemble', 'dtptRenderEnsemble');
	return true;
}

function dtptTokenToMarkup( $token)
{
	return "{{" . $token . "}}";	
}

function dtptTableStart( $class)
{
	global $dtptInEnsemble;
	if ($dtptInEnsemble)
	{
		return "|-\n";
	}
	else
	{
		return "{|class='$class'\n";
	}
}

function dtptTableEnd()
{
	global $dtptInEnsemble;
	if ($dtptInEnsemble)
	{
		return "|-\n";
	}
	else
	{
		return "|}\n";
	}
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
	global $dtptMarkupBuffer;
	
	$counter_style = ($dtptMarkupBuffer == "")?'counter_line':'repeat_counter_line';
	
	$beat_counter = 1;
	$countsPerMeasure = $countsPerBeat * $beatsPerMeasure;
	$wikitext = '';
	for ($i = 0; $i < $counts; ++$i)
	{
		if ($i % $countsPerMeasure == 0)
		{
			$style = "class='$counter_style measure_start'|";
			$beat_counter = 1;
		}
		elseif (($i + 1)% $countsPerMeasure == 0)
		{
			$style = "class='$counter_style measure_end'|";
		}
		else 
		{
			$style = "class='$counter_style'|";
		}
		
		if ( $i%$countsPerBeat == 0)
		{
			$wikitext .= "||$style<div class='$counter_style'>$beat_counter</div>";
			++$beat_counter;
		}
		else
		{
			$wikitext .= "||$style&nbsp;";
		}
	}
	
	return "|-\n|&nbsp;" . $wikitext ;
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

function dtptRecursiveTagParse( $wikitext, Parser $parser, PPFrame $frame)
{
	global $dtptInEnsemble;
	global $dtptMarkupBuffer;
	
	if ($dtptInEnsemble)
	{
		$dtptMarkupBuffer .= $wikitext;
		return '';
	}
	else 
	{
		return $parser->recursiveTagParse( $wikitext, $frame);
	}
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
	$wikitext = dtptTableStart($djembeStyle);
	
	$wikitext .= dtptCounterLine(count( $tokens), $options['countsperbeat'], $options['beatspermeasure']);
	$times = $options['times'];
	if ($times)
	{
		$wikitext .= "||rowspan='2' class='times'| X$times";
	}
	$wikitext .= "\n|-\n| class='bst_column' | {{BST}}";
	foreach ($tokens as $token) {
		$wikitext .= '||'. dtptTokenToMarkup($token);
	}
	$wikitext .= "\n".dtptTableEnd();
	
	return dtptRecursiveTagParse( $wikitext, $parser, $frame );
}

/*!
 * Examine an array of tokens and try to determine the intended instrument.
 * 
 * This function will look for characters like 'S', 'K' or 'D' to determine 
 * the instrument type ('Sangban', 'Kenkeni' or 'Dununba' respectively). 
 * The search is performed case-insensitive.
 */
function dtptLookupInstrument( array $tokens)
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

/*!
 * Render a dunun symbol.
 * 
 * This function takes a symbol and generate one of the following outputs:
 *  * {{Dun empty}} for the character '.'
 *  * {{Dun bell}} for 'x' or 'X'
 *  * {{Dun open}} for any lowercase character
 *  * {{Dun closed}} for any other character
 *  
 */
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

/*!
 * Render an ensemble.
 * 
 * When different drum notations need to be aligned, they can be wrapped in and <ensemble>-tag.
 * This renders all drums inside this tag in one table, making sure that columns are aligned.
 * 
 * This function does not much more than start a table and set the global dtptInEnsemble flag,
 * which suppresses the table start- and exit code of the other renderers.
 */
function dtptRenderEnsemble( $input, array $args, Parser $parser, PPFrame $frame )
{
	global $dtptInEnsemble;
	global $dtptMarkupBuffer;
	
	$previousFlag = $dtptInEnsemble;
	$dtptInEnsemble  = true;
	$options = dtptDetermineOptions( $args);
	$ensembleStyle = $options['ensemblestyle'];
	$prolog = "{|class='$ensembleStyle'\n";
	$epilog = "|}\n";
	
	// This will call embedded <djembe> or <dunun> tag handlers, but these will
	// not directly produce any output.
	$result = $parser->recursiveTagParse( $input, $frame );
	
	// the actual text to be rendered is in $dtptMarkupBuffer
	$result = $parser->recursiveTagParse( $prolog . $dtptMarkupBuffer . $epilog, $frame );
	
	// reset the ensemble flag.
	$dtptInEnsemble = $previousFlag;
	
	return $result;
}

/*!
 * Render a Dunun section.
 * 
 * This function parses lines of text (i.e. separated by newlines). The lines 
 * should contain whitespace-separated tokens. Dot (full stop) means pause, any other character
 * is interpreted as a dunun drum. See dtptLookupInstrument for a list of recognized drum tokens.
 * 
 * From the tokens used in a line, this function will guess the name of the drum and place that 
 * name in front of the rendered line.
 */
function dtptRenderDunun( $input, array $args, Parser $parser, PPFrame $frame ) 
{
	$options = dtptDetermineOptions( $args);
	$dununStyle = $options['dununstyle'];
	// split into lines
	$lines = preg_split('/\n/', $input, -1, PREG_SPLIT_NO_EMPTY);
	
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
	$counts = dtptCounterLine( $maxCount, $options['countsperbeat'], $options['beatspermeasure']) . "\n";
	
	$wikitext = dtptTableStart($dununStyle) . $counts . $wikitext . dtptTableEnd();
	
	return dtptRecursiveTagParse( $wikitext, $parser, $frame );
}
