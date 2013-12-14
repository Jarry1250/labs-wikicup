<?php
/*	
	Wikicup scoring bot code
	Originally the work of Soxred93
	Modified by Jarry1250 in December 2010

	This program is free software; you can redistribute it and/or modify
	it under the terms of the GNU General Public License as published by
	the Free Software Foundation; either version 2 of the License, or
	(at your option) any later version.

	This program is distributed in the hope that it will be useful,
	but WITHOUT ANY WARRANTY; without even the implied warranty of
	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	GNU General Public License for more details.

	You should have received a copy of the GNU General Public License
	along with this program; if not, write to the Free Software
	Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

// Things you might actually want to change. For the first three, the
// order is what is important, the names are only for readability. The
// same order must be used here, on the Submissions pages and in the
// column headings on the main page.

$linestarts = array_values(array('FA' => '\#', 'GA' => '\#', 'FL' => '\#', 'FP' => '\#', 'FPO' => '\#', 'FT' => '\#\#', 'GT' => '\#\#', 'DYK' => '\#', 'ITN' => '\#', 'GAR' => '\#'));
$names = array_values(array('FA' => 'Featured Article', 'GA' => 'Good Article', 'FL' => 'Featured List', 'FP' => 'Featured Picture', 'FPO' => 'Featured Portal', 'FT' => 'Featured Topic article', 'GT' => 'Good Topic article', 'DYK' => 'Did You Know', 'ITN' => 'In the News article', 'GAR' => 'Good Article Review'));
$year = 2013;

//Other stuff
ini_set('memory_limit','16M');
ini_set('display_errors', 1); 
error_reporting(E_NOTICE);

echo "\n<!-- Begin output at " . date('j F Y \a\t H:i') . " -->"; 
 
require_once('/home/jarry/public_html/mw-peachy/Init.php');
$site = Peachy::newWiki( "livingbot" );

$contestant_points = array();
$points_page_name = 'Wikipedia:WikiCup/History/'.$year;
$points_page = initPage($points_page_name);
$points_page_text = $points_page_text_original = $points_page->get_text();

preg_match_all("/\{\{(Wikipedia:WikiCup\/Participant[0-9]*)\|(.*?)\}\}/i", $points_page_text, $contestants, PREG_PATTERN_ORDER);
$templatename = $contestants[1][0];
$contestants = $contestants[2];

$log_page = initPage('Wikipedia:WikiCup/History/'.$year.'/Running totals');
$existing_text = $log_page->get_text();
	
$append = "";
foreach( $contestants as $contestant ) {
	echo "Parsing user $contestant... ";
			
	$contestant_sub_page_name = "Wikipedia:WikiCup/History/$year/Submissions/".$contestant;
	$page = initPage($contestant_sub_page_name);
	if(!$page->get_exists()){
		echo "ERROR: no submissions page for this user exists at $contestant_sub_page_name\n";
		continue;
	}
	
	//Post or pre-blanking?
	$history = $page->history();
	if( stripos( $history[0]['comment'], "blank" ) === false || stripos( $history[0]['comment'], "AWB" ) === false ){
		$contestant_submissions = $page->get_text();
	} else {
		$preblank = $page->history( 1, "older", true, $history[0]['parentid'] );
		$contestant_submissions = $preblank[0]['*'];
	}	
	$m = preg_split('/===(.*?)===/',$contestant_submissions);
	array_shift($m);
	
	for($i=0; $i<count($names); $i++){
		$lines = explode("\n",$m[$i]);
		$count = 0;
		foreach ($lines as $line){
			if( preg_match('/^' . $linestarts[$i] . 
				" *'*\[\[(.*?)(\]|\|).*?" . 
				'(Multiplier\|([0-9.]+|none)\|([0-9.]+|none)\|([0-9.]+|none)\}\})?(\w)*$/', $line, $bits) ){
				$article = "[[:" . $bits[1] . "]]";
				$multiplier = 1;
				$preadditive = 0;
				$postadditive = 0;
				if(isset($bits[4]) && strlen($bits[4]) > 0){
					$multiplier = is_numeric( $bits[4] ) ? floatval($bits[4]) : 1;
					$preadditive = is_numeric( $bits[5] ) ? intval($bits[5]) : 0;
					$postadditive = is_numeric( $bits[6] ) ? intval($bits[6]) : 0;
				}
				if (strpos($existing_text, "|$contestant||$article||{$names[$i]}") === false 
					&& strpos($append, "|$contestant||$article||{$names[$i]}") === false ){
					$append .= "\n|-\n|$contestant||$article||{$names[$i]}||$multiplier||$preadditive||$postadditive";
				}
			}
		}
	}
}

if(strlen($append) > 0){
	$content = substr($existing_text, 0, strrpos($existing_text, "\n|}"));
	$log_page->edit($content . $append . "\n|}", "Bot: adding new items to the list", true, true);
}

echo "<!-- End output at " . date('j F Y \a\t H:i') . " -->";
?>
