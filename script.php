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
	// Things you might actually want to change. For the first six, the
	// order is what is important, the names are only for readability. The
	// same order must be used here, on the Submissions pages and in the
	// column headings on the main page.

	$categories = array_values(array('FA' => 100, 'GA' => 30, 'FL' => 45, 'FP' => 35, 'FPO' => 35, 'FT' => 10, 'GT' => 3, 'DYK' => 5, 'ITN' => 10, 'GAR' => 4));
	$linestarts = array_values(array('FA' => '\#', 'GA' => '\#', 'FL' => '\#', 'FP' => '\#', 'FPO' => '\#', 'FT' => '\#\#', 'GT' => '\#\#', 'DYK' => '\#', 'ITN' => '\#', 'GAR' => '\#'));
	$names = array_values(array('FA' => 'Featured Article', 'GA' => 'Good Article', 'FL' => 'Featured List', 'FP' => 'Featured Picture', 'FPO' => 'Featured Portal', 'FT' => 'Featured Topic article', 'GT' => 'Good Topic article', 'DYK' => 'Did You Know', 'ITN' => 'In the News article', 'GAR' => 'Good Article Review'));
	$hasmultipliers = array_values(array('FA' => true, 'GA' => true, 'FL' => false, 'FP' => false, 'FPO' => true, 'FT' => false, 'GT' => false, 'DYK' => true, 'ITN' => false, 'GAR' => false));
	$year = 2013;
	$apibase = 'http://en.wikipedia.org/w/api.php?format=json&';

	//Other stuff
	ini_set('memory_limit','16M');
	ini_set('display_errors', 1); 
	error_reporting(E_ALL);

	echo "<!-- Begin output at " . date('j F Y \a\t H:i') . " -->\n"; 
	 
	require_once('/home/jarry/public_html/mw-peachy/Init.php');
	$site = Peachy::newWiki( "livingbot" );

	echo "<-- Peachy loaded, trying file -->";

	$contestant_points = array();
	$points_page_name = 'Wikipedia:WikiCup/History/'.$year;
	$points_page = initPage($points_page_name);
	$points_page_text = $points_page_text_original = $points_page->get_text();

	preg_match_all("/\{\{(Wikipedia:WikiCup\/Participant[0-9]*)\|(.*?)\}\}/i", $points_page_text, $contestants, PREG_PATTERN_ORDER);
	$templatename = $contestants[1][0];
	$contestants = $contestants[2];

	$filename = '/home/jarry/public_html/wikicup/log.txt';
	$contents = file_get_contents( $filename );

	echo "<!-- File loaded, trying main process -->";

	$append = ''; //log
	foreach( $contestants as $contestant ) {
		echo "Parsing user $contestant... ";
			
		$contestant_sub_page_name = "Wikipedia:WikiCup/History/$year/Submissions/".$contestant;
		$page = initPage($contestant_sub_page_name);
		if(!$page->get_exists()){
			echo "ERROR: no submissions page for this user exists at $contestant_sub_page_name\n";
			continue;
		}
		$contestant_submissions = $contestant_submissions_original = $page->get_text();
//$contestant_submissions = preg_replace( "/ \{\{Wikipedia:WikiCup\/Multiplier[^}]+\}\}/", '', $contestant_submissions );
		$m = preg_split('/===(.*?)===/',$contestant_submissions);
		array_shift($m);
		
		$scoreline = '{{'.$templatename.'|'.$contestant.'}}||';
		$total_points = 0;
		$total_bonus_points = 0;
		$changed = false; //for updating multipliers
		for($i=0; $i<count($categories); $i++){
			$lines = explode("\n",$m[$i]);
			$alreadycounted = array();
			$points = 0;
			$bonuspoints = 0;
			foreach ($lines as $line){
				if( preg_match('/^' . $linestarts[$i] . 
					" *'*\[\[(.*?)(\]|\|).*?" . 
					'(Multiplier\|([0-9.]+|none)\|([0-9.]+|none)\|([0-9.]+|none)\}\})?(\w)*$/', $line, $bits) ){
					$article = "[[" . $bits[1] . "]]";
					if(in_array($bits[1],$alreadycounted)) continue;
					$alreadycounted[] = $bits[1];
					$multiplier = 1;
					$preadditive = 0;
					$postadditive = 0;
					$basepoints = $categories[$i];
					if( $hasmultipliers[$i] ){
						if(isset($bits[4]) && strlen($bits[4]) > 0){
							$multiplier = is_numeric( $bits[4] ) ? floatval($bits[4]) : 1;
							$preadditive = is_numeric( $bits[5] ) ? intval($bits[5]) : 0;
							$postadditive = is_numeric( $bits[6] ) ? intval($bits[6]) : 0;
						} else {
							list( $multiplier, $preadditive, $postadditive ) = getApplicableMultiplier( $bits[1], $i, $line );
							$multipliertext = ($multiplier == 1) ? 'none' : $multiplier;
							$preadditivetext = ($preadditive == 0) ? 'none' : $preadditive;
							$postadditivetext = ($postadditive == 0) ? 'none' : $postadditive;
							$contestant_submissions = str_replace( $line, trim( $line ) . " {{Wikipedia:WikiCup/Multiplier|$multipliertext|$preadditivetext|$postadditivetext}}", $contestant_submissions );
							$changed = true;
						}
					}
					if (strpos($contents, $article) === false){
						$append .= "\n* $contestant ([[$contestant_sub_page_name|submissions]]) claimed $article as a {$names[$i]}";
						if($multiplier > 1){
							$append .= " with a $multiplier-times multiplier";
						}
					}
					$points += ( $basepoints + $preadditive );
					$bonuspoints += ( ( $basepoints + $preadditive ) * ( $multiplier - 1 ) ) + $postadditive;
				}
			}
			$total_points += $points + $bonuspoints;
			$total_bonus_points += $bonuspoints;
			$scoreline .= $points . '||';
		}
		$scoreline .= $total_bonus_points . '||';
		$scoreline .= "'''$total_points'''";
				
		preg_match( '/\{\{'.str_replace("/","\/",preg_quote($templatename)).'\|'.str_replace("/","\/",preg_quote($contestant))."}}[^']+'''\d+'''/i", $points_page_text, $n );
		if($n[0] == $scoreline){ 
			echo "No change\n";
		} else {
			echo "\nReplacing {$n[0]} with $scoreline...\n";
			$points_page_text = str_replace($n[0],$scoreline,$points_page_text);
		}
		if( $changed ){
			echo "Loading multiplier assessment diff...\n";
			echo Diff::load('unified', $contestant_submissions_original, $contestant_submissions);
			$page->edit( $contestant_submissions, "Bot: Assessing multipliers due. ([[WP:CUPSUGGEST|Stuck for what to work on?]])" );
		}
	}

	if(strlen($append) > 0){
		file_put_contents($filename, $append, FILE_APPEND);
		$log_page = initPage('Wikipedia:WikiCup/History/'.$year.'/log');
		$log_page->edit( "\n" . trim($append), "Bot: adding new claims to the list", true, true, false, "ap");
	}

	echo "Finished,";
	if( $points_page_text_original !== $points_page_text ){
		echo " loading diff...\n";
		echo Diff::load('unified', $points_page_text_original, $points_page_text);
	} else{
		echo "no change.\n";
	}
	$points_page->edit($points_page_text,"Bot: Updating WikiCup table",true);

	echo "<!-- End output at " . date('j F Y \a\t H:i') . " -->";

	function get($url) {
          $ch = curl_init();
          curl_setopt($ch, CURLOPT_URL, $url);
          curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
          curl_setopt($ch, CURLOPT_USERAGENT, 'LivingBot');
          curl_setopt($ch, CURLOPT_HTTPHEADER, array('Content-Type: application/x-www-form-urlencoded;charset=UTF-8'));
          curl_setopt($ch, CURLOPT_HEADER, false);
		  curl_setopt ($ch, CURLOPT_SSL_VERIFYHOST, 0);
		  curl_setopt ($ch, CURLOPT_SSL_VERIFYPEER, 0);
          $response = curl_exec($ch);
          if (curl_errno($ch)) {
              return curl_error($ch);
          }
          curl_close($ch);
          return $response;
    }
	function getJSON($url){
		return json_decode( get( $url ), true );
	}
	function getApplicableLength( $pagename, $dykname ){
		global $apibase;
		// Get timestamp of promotionplus 12 hours
		$json = getJSON( $apibase . "action=query&titles=" . urlencode( $dykname ) . "&prop=revisions" );
		$page = array_shift( $json['query']['pages']);
		$timestamp = date( 'YmdHis', strtotime( $page['revisions'][0]['timestamp'] ) + ( 12 * 60 * 60 ) );

		// Get revid or article at that time
		$json = getJSON( $apibase . "action=query&prop=revisions&titles=$pagename&rvprop=ids&rvstart=$timestamp&rvlimit=1" );
		$page = array_shift( $json['query']['pages']);
		$revid = $page['revisions'][0]['revid'];

		// Count prose size of article at that time
		$json = getJSON( $apibase . "action=parse&oldid=$revid&prop=text&disablepp" );
		$text = str_replace( "\n", "", $json['parse']['text']['*'] );
		$striptags = array( 'div', 'ul', 'sub', 'sup' );
		foreach( $striptags as $striptag ){
			$regex = "/[<]" . $striptag . ".*?[<]\/" . $striptag . "[>]/";
			while( preg_match( $regex, $text ) ){
				$text = preg_replace( $regex, '', $text );
			}
		}
		$paras = preg_split( '/<p[> ]/', $text );
		array_shift( $paras );
		foreach( $paras as &$para ){
			list( $para, ) = explode( '</p>', $para, 2 );
			while( preg_match( '/[<].*?[>]/', $para ) ){
				$para = preg_replace( '/[<].*?[>]/', '', $para );
			}
			$para = preg_replace( '/\[[0-9]{1,3}\]/', '', $para );
		}
		$prose = implode( '', $paras );
		echo mb_strlen( $prose, 'UTF-8' );
		return mb_strlen( $prose, 'UTF-8' );
	}
	function getCreationDate( $pagename ){
		global $apibase;

		// Find first revision
		$json = getJSON( $apibase . "action=query&titles=$pagename&prop=revisions&rvdir=newer&rvlimit=1" );
		if( !isset( $json['query']['pages'] ) ) return false;
		$page = array_shift( $json['query']['pages']);
		if( !isset( $page['revisions'], $page['revisions'][0], $page['revisions'][0]['timestamp'] ) ) return false;

		return strtotime( $page['revisions'][0]['timestamp'] );
	}
	function getApplicableMultiplier( $pagename, $i, $line ){
		// TODO: combine API queries
		global $apibase, $year, $names;
		$pagename = urlencode( $pagename );
		$section = $names[$i];

		// Find revision id of the last version before 1 January 2013
		$revid = false;
		$json = getJSON( $apibase . "action=query&prop=revisions&titles=$pagename&rvprop=ids&rvstart=" . $year . "0101000000&rvlimit=1" );
		foreach( $json['query']['pages'] as $page ){
			// Only one at the moment
			$revid = isset( $page['revisions'] ) ? $page['revisions'][0]['revid'] : false;
		}

		// Find langlinks for that revision
		if( $revid ){
			$json = getJSON( $apibase . "action=query&prop=langlinks&revids=$revid&lllimit=500" );
			foreach( $json['query']['pages'] as $page ){
				$langlinks = isset( $page['langlinks'] ) ? count( $page['langlinks'] ) : 0;
				// Also exists on the home wiki
				$existsOn = $langlinks + 1;
			}
		} else {
			$json = getJSON( "http://wikidata.org/w/api.php?format=json&action=wbgetentities&sites=enwiki&titles=$pagename&props=info" );
			if( count( $json['entities'] ) > 0 ) {
				foreach( $json['entities'] as $entity ){
					$pagename = $entity['id'];
				}
				// Find revision id of the last version before 1 January 2013
				$existsOn = 0;
				$json = getJSON( "http://wikidata.org/w/api.php?format=json&action=query&prop=revisions&titles=" . $pagename . "&rvprop=content&rvstart=" . $year . "0101000000&rvlimit=1" );
				foreach( $json['query']['pages'] as $page ){
					// Only one at the moment
					if( isset( $page['revisions'] ) ) {
						$content = $page['revisions'][0]['*'];
						$links = json_decode( $content, true );
						$links = $links['links'];
						$existsOn = count( $links );
					}
				}
			} else {		
				$existsOn = 0; // Didn't even exist on en.wp, nothing on Wikidata either
			}
		}

		$multiplicative = 1 + ( 0.2 * floor( $existsOn / 5 ) );

		$preadditive = 0;
		$postadditive = 0;
		if( $section == 'Did You Know' ){
			echo $line;
			preg_match( '/Template:Did you know nominations.*?(?=\])/', $line, $bits );
			if( isset( $bits[0] ) ) {
				$dyknom = $bits[0];
			} else {
				$dyknom = "Template:Did you know nominations/" . urldecode( $pagename );
			}
			$i = array_search( 'Did You Know', $names );
			if( getApplicableLength( $pagename, $dyknom ) > 5100 ){
				$preadditive += 5;
			}
			$creation = getCreationDate( $pagename );
			if( $creation && $creation < strtotime( '1 January 2008' ) ){
				$postadditive += 2;
			}
		}

		return array( $multiplicative, $preadditive, $postadditive );
	}