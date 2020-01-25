<?php
	/*	
	 * Wikicup scoring bot code
	 * Originally the work of Soxred93
	 * Modified by Jarry1250 from December 2010
	 * Last edit January 2020
	 *
	 * This program is free software; you can redistribute it and/or modify
	 * it under the terms of the GNU General Public License as published by
	 * the Free Software Foundation; either version 2 of the License, or
	 * (at your option) any later version.
	 *
	 * This program is distributed in the hope that it will be useful,
	 * but WITHOUT ANY WARRANTY; without even the implied warranty of
	 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
	 * GNU General Public License for more details.
	 *
	 * You should have received a copy of the GNU General Public License
	 * along with this program; if not, write to the Free Software
	 * Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
	 */

	// Things you might actually want to change. For the first six, the
	// order is what is important, the names are only for readability. The
	// same order must be used here, on the Submissions pages and in the
	// column headings on the main page.

	$categories = array(
		array( 'name' => 'Featured Article', 'points' => 200, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'Featured List', 'points' => 45, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'Featured Picture', 'points' => 30, 'lineStart' => '\#', 'hasMultipliers' => false ),
		array( 'name' => 'Featured Topic article', 'points' => 15, 'lineStart' => '\#\#', 'hasMultipliers' => false ),
		array( 'name' => 'Featured Article Review', 'points' => 5, 'lineStart' => '\#', 'hasMultipliers' => false ),
		array( 'name' => 'Good Article', 'points' => 35, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'Good Topic article', 'points' => 5, 'lineStart' => '\#\#', 'hasMultipliers' => false ),
		array( 'name' => 'Good Article Review', 'points' => 5, 'lineStart' => '\#', 'hasMultipliers' => false ),
		array( 'name' => 'Did You Know', 'points' => 5, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'In the News article', 'points' => 12, 'lineStart' => '\#', 'hasMultipliers' => false ),
	);
	$year = date( 'Y' );
	$apiBase = 'https://en.wikipedia.org/w/api.php?format=json&';

	// Other stuff
	ini_set( 'memory_limit', '16M' );
	ini_set( 'display_errors', 1 );
	error_reporting( E_ALL );
	$debug = ( isset( $_GET['debug'] ) && $_GET['debug'] == 'y' );

	echo "\n<!-- Begin output at " . date( 'j F Y \a\t H:i' ) . " -->\n";

	require_once( '/data/project/jarry-common/public_html/peachy/Init.php' );
	require_once( '/data/project/jarry-common/public_html/libs/Diff.php' );
	$site = Peachy::newWiki( "livingbot" );
	$http = new HTTP();

	echo "\n<-- Peachy loaded, trying file -->";

	$contestantPoints = array();
	$pointsPageName = 'Wikipedia:WikiCup/History/' . $year;
	$pointsPage = initPage( $pointsPageName );
	$pointsPageText = $pointsPageTextOriginal = $pointsPage->get_text();

	preg_match_all( "/\{\{(Wikipedia:WikiCup\/Participant[0-9]*)\|(.*?)\}\}/i", $pointsPageText, $contestants, PREG_PATTERN_ORDER );
	$templatename = $contestants[1][0];
	$contestants = array_map( 'trim', $contestants[2] );

	$filename = __DIR__ . '/log.txt';
	$contents = file_get_contents( $filename );

	echo "\n<!-- File loaded, trying main process -->";

	$append = ''; //log
	foreach( $contestants as $contestant ){
		echo "Parsing user $contestant... ";
		$contestantSubpageName = "Wikipedia:WikiCup/History/$year/Submissions/" . $contestant;
		$page = initPage( $contestantSubpageName );
		if( !$page->get_exists() ){
			echo "ERROR: no submissions page for this user exists at $contestantSubpageName\n";
			continue;
		}
		$contestantSubmissions = $contestantSubmissionsOriginal = $page->get_text();
		$sections = preg_split( '/===(.*?)===/', $contestantSubmissions );
		array_shift( $sections );

		$scoreline = '{{' . $templatename . '|' . $contestant . '}}||';
		$totalPoints = 0;
		$totalBonusPoints = 0;
		$changed = false; //for updating multipliers
		for( $i = 0; $i < count( $categories ); $i++ ){
			$lines = explode( "\n", $sections[$i] );
			$alreadyCounted = array();
			$points = 0;
			$bonusPoints = 0;
			foreach( $lines as $line ){
				if( preg_match(
					'/^' . $categories[$i]['lineStart'] .
					" *'*\[\[(.*?)(\]|\|).*?" .
					'(Multiplier\|([0-9.]+|none)\|([0-9.]+|none)\|([0-9.]+|none)\}\})?$/', $line, $bits
				)
				){
					$bits = array_map( 'trim', $bits );

					$article = "[[" . $bits[1] . "]]";
					if( in_array( $bits[1], $alreadyCounted ) ){
						continue;
					}
					$alreadyCounted[] = $bits[1];
					$multiplier = 1;
					$preadditive = 0;
					$postadditive = 0;
					$basePoints = $categories[$i]['points'];
					if( $categories[$i]['hasMultipliers'] ){
						if( isset( $bits[4] ) && strlen( $bits[4] ) > 0 ){
							$multiplier = is_numeric( $bits[4] ) ? floatval( $bits[4] ) : 1;
							$preadditive = is_numeric( $bits[5] ) ? intval( $bits[5] ) : 0;
							$postadditive = is_numeric( $bits[6] ) ? intval( $bits[6] ) : 0;
						} else {
							list( $multiplier, $preadditive, $postadditive )
								= getApplicableMultiplier( $bits[1], $categories[$i]['name'], $line );
							$multiplierText = ( $multiplier == 1 ) ? 'none' : $multiplier;
							$preadditiveText = ( $preadditive == 0 ) ? 'none' : $preadditive;
							$postadditiveText = ( $postadditive == 0 ) ? 'none' : $postadditive;
							$contestantSubmissions = str_replace( $line, trim( $line ) . " {{Wikipedia:WikiCup/Multiplier|$multiplierText|$preadditiveText|$postadditiveText}}", $contestantSubmissions );
							$changed = true;
						}
					}
					$articleEscaped = str_replace( '[[File', '[[:File', $article );
					if( strpos( $contents, $articleEscaped ) === false ){
						$append .= "\n* $contestant ([[$contestantSubpageName|submissions]]) claimed $articleEscaped as a {$categories[$i]['name']}";
						if( $multiplier > 1 ){
							$append .= " with a $multiplier-times multiplier";
						}
						if( $categories[$i]['name'] == 'Did You Know' ){
							$likelyDYK = findDYKNom( $bits[1] );
							if( $likelyDYK !== false ){
								$append .= " ([[:" . $likelyDYK . "|likely DYKNom]])";
							}
						}
					}
					$points += ( $basePoints + $preadditive );
					$bonusPoints += ( ( $basePoints + $preadditive ) * ( $multiplier - 1 ) ) + $postadditive;
				}
			}
			$totalPoints += $points + $bonusPoints;
			$totalBonusPoints += $bonusPoints;
			$scoreline .= $points . '||';
		}
		$scoreline .= $totalBonusPoints . '||';
		$scoreline .= "'''$totalPoints'''";

		preg_match( '/\{\{' . str_replace( "/", "\/", preg_quote( $templatename ) ) . '\| *' . str_replace( "/", "\/", preg_quote( $contestant ) ) . " *}}[^']+'''\d+'''/i", $pointsPageText, $n );
		if( $n[0] == $scoreline ){
			echo "No change\n";
		} else {
			echo "\nReplacing {$n[0]} with $scoreline...\n";
			$pointsPageText = str_replace( $n[0], $scoreline, $pointsPageText );
		}
		if( $changed ){
			echo "Loading multiplier assessment diff...\n";
			Diff::load( $contestantSubmissionsOriginal, $contestantSubmissions )->printUnifiedDiff();
			if( !$debug ) $page->edit( $contestantSubmissions, "Bot: Assessing multipliers due. ([[WP:CUPSUGGEST|Stuck for what to work on?]])" );
		}
	}

	if( strlen( $append ) > 0 ){
		file_put_contents( $filename, $append, FILE_APPEND );
		$logPage = initPage( 'Wikipedia:WikiCup/History/' . $year . '/log' );
		if( !$debug ) $logPage->edit( "\n" . trim( $append ), "Bot: adding new claims to the list", true, true, false, "ap" );
	}

	echo "Finished,";
	if( $pointsPageTextOriginal !== $pointsPageText ){
		echo " loading diff...\n";
		Diff::load( $pointsPageTextOriginal, $pointsPageText )->printUnifiedDiff();
	} else {
		echo "no change.\n";
	}
	if( !$debug ) $pointsPage->edit( $pointsPageText, "Bot: Updating WikiCup table", true );

	$site->purge( 'Wikipedia:WikiCup' );

	echo "\n<!-- End output at " . date( 'j F Y \a\t H:i' ) . " -->";


	function getJSON( $url ) {
		global $http;
		return json_decode( $http->get( $url ), true );
	}

	function getApplicableLength( $pagename, $dykname ) {
		$encodedPagename = urlencode( $pagename );
		$encodedDykName = urlencode( $dykname );
		global $apiBase, $debug;

		if( $debug ) echo "Getting applicable length for $pageName...\n";

		// Working out when a DYK appeared on the mainpage is remarkably difficult...
		$json = getJSON( $apiBase . "action=query&list=backlinks&blnamespace=4&bltitle=" . $encodedPagename );
		$backlinks = $json['query']['backlinks'];
		$timestamp = false;
		foreach( $backlinks as $backlink ) {
			if( preg_match( '/^Wikipedia:Recent additions/', $backlink['title'] ) ) {
				$json = getJSON( $apiBase . "action=query&prop=revisions&titles=" . urlencode( $backlink['title'] ) . "&rvprop=content&rvlimit=1" );
				$page = array_shift( $json['query']['pages'] );
				$text = str_replace( '_', ' ', $page['revisions'][0]['*'] );
				$bits = explode( str_replace( '_', ' ', $pagename ), $text, 2 );

				// Wierdly we want the timestamp after and not before...
				if( count( $bits ) > 1 && preg_match("/'''''([^']+) \(UTC\)'''''/i", $bits[1], $matches ) ) {
					$timestamp = date( 'YmdHis', strtotime( $matches[1] ) );
					break;
				}
			}
		}
		if( $timestamp === false ) {
			// Fall back to assumption of promotion plus 12 hours
			$json = getJSON( $apiBase . "action=query&titles=$encodedDykName&prop=revisions" );
			$page = array_shift( $json['query']['pages'] );
			$timestamp = date( 'YmdHis', strtotime( $page['revisions'][0]['timestamp'] ) + ( 12 * 60 * 60 ) );
		}

		if( $debug ) echo "Found timestamp $timestamp...\n";

		// Get revid or article at that time
		$json = getJSON( $apiBase . "action=query&prop=revisions&titles=$encodedPagename&rvprop=ids&rvstart=$timestamp&rvlimit=1" );
		$page = array_shift( $json['query']['pages'] );
		$revId = $page['revisions'][0]['revid'];

		if( $debug ) echo "Using revid $revId...\n";

		// Count prose size of article at that time
		$json = getJSON( $apiBase . "action=parse&oldid=$revId&prop=text" );
		$text = str_replace( "\n", "", $json['parse']['text']['*'] );
		$text = html_entity_decode( $text );

		preg_match_all( '/<p( [^>]+)?>.*?<\/p>/', $text, $paras );
		$count = 0;
		foreach( $paras[0] as $para ){
			// <sub> and <sup> should be removed in their entirety
			$para = preg_replace( '/[<]su(b|p).*?[>].*?[<]\/su(b|p)[>]/', '', $para );

			// For everything else, retain innerHTML
			while( preg_match( '/[<].*?[>]/', $para ) ){
				$para = preg_replace( '/[<].*?[>]/', '', $para );
			}
			$count += mb_strlen( $para, 'UTF-8' );
		}
		if( $debug ) echo "Found length $count...\n";
		return $count;
	}

	// n.b. getApplicableAge returns 4 for all ages less than 5 for performance reasons
	function getApplicableAge( $pagename ) {
		global $apiBase;

		$year = ( intval( date( 'Y' ) ) - 4 );
		$lastQualifyingYear = $year;
		while( true ){
			$year--;

			// Find first revision before cutoff, if any
			$cutoff = $year . '0101000000';
			$json = getJSON( $apiBase . "action=query&prop=revisions&titles=" . urlencode( $pagename ) . "&rvprop=size&rvstart=$cutoff&rvlimit=1" );

			if( !isset( $json['query']['pages'] ) ){
				// No such page?
				break;
			}
			$page = array_shift( $json['query']['pages'] );
			if(	!isset( $page['revisions'], $page['revisions'][0], $page['revisions'][0]['size'] ) ){
				// No such revision or redirect, etc
				break;
			}
			if( intval( $page['revisions'][0]['size'] ) >= 100 ) {
				$lastQualifyingYear = $year;
			}
		}
		return ( intval( date( 'Y' ) ) - $lastQualifyingYear );
	}

	function getApplicableMultiplier( $pageName, $section, $line ) {
		// TODO: combine API queries
		global $year, $debug;
		$encodedPageName = urlencode( $pageName );
		$encodedPageName = str_replace( '%E2%80%8E', '', $encodedPageName ); // Strip Unicode control character (LTR)
		$pageName = urldecode( $encodedPageName );
		if( $debug ) echo "Getting applicable multipliers for $pageName...\n";

		// Find last Wikidata revision before 1 January
		$json = getJSON( "https://www.wikidata.org/w/api.php?format=json&action=wbgetentities&sites=enwiki&titles=$encodedPageName&props=info" );
		$existsOn = 0; // Didn't even exist on Wikidata
		if( count( $json['entities'] ) > 0 ){
			$qID = false;
			foreach( $json['entities'] as $entity ){
				if( isset( $entity['id'] ) ) $qID = $entity['id'];
			}
			if( $qID !== false ) {
				// Grab its content and count langlinks
				$existsOn = 0;
				$json = getJSON( "https://www.wikidata.org/w/api.php?format=json&action=query&prop=revisions&titles=" . $qID . "&rvprop=content&rvstart=" . $year . "0101000000&rvlimit=1" );
				foreach( $json['query']['pages'] as $page ){
					// Only one at the moment
					if( isset( $page['revisions'] ) ){
						$content = $page['revisions'][0]['*'];
						$links = json_decode( $content, true );
						$links = array_keys( $links['sitelinks'] );
						$links = array_filter( $links, 'isWikipedia' );
						$existsOn = count( $links );
					}
				}
			} else {
				echo "Warning: $pageName does not appear on Wikidata, so assuming it doesn't qualify for a multiplicative bonus.\n";
			}
		}

		// An $existsOn value of 0-4 should give a $multiplicative score of 1; 5-9 1.2; 10-14 1.4 and so on up to a maximum of 3.
		$multiplicative = 1 + min( 2, 0.2 * floor( $existsOn / 5 ) );

		$preadditive = 0;
		$postadditive = 0;
		if( $section == 'Did You Know' ){
			preg_match( '/Template:Did you know nominations.*?(?=\])/', $line, $bits );
			if( isset( $bits[0] ) ){
				$dykNom = $bits[0];
			} else {
				$dykNom = "Template:Did you know nominations/" . $pageName;
			}
			if( getApplicableLength( $pageName, $dykNom ) > 5100 ){
				$preadditive += 5;
			}
			$age = getApplicableAge( $pageName );
			if( $age >= 5 ){
				$postadditive += $age;
			}
		}

		if( $debug ) echo "Setting applicable multipliers for $pageName as ( $multiplicative, $preadditive, $postadditive )...\n";
		return array( $multiplicative, $preadditive, $postadditive );
	}

	function isWikipedia( $dbname ) {
		return substr( $dbname, -4 ) == 'wiki'
			&& substr( $dbname, 0, 9 ) != 'wikimania'
			&& !in_array( $dbname, array(
				'advisorywiki',
				'commonswiki',
				'donatewiki',
				'foundationwiki',
				'incubatorwiki',
				'loginwiki',
				'mediawikiwiki',
				'metawiki',
				'nostalgiawiki',
				'outreachwiki',
				'qualitywiki',
				'sourceswiki',
				'specieswiki',
				'strategywiki',
				'tenwiki',
				'test2wiki',
				'testwiki',
				'testwikidatawiki',
				'usabilitywiki',
				'votewiki',
				'wikidatawiki',
			));
	}

	function findDYKNom( $pageName ) {
		$firstGuess =  'Template:Did_you_know_nominations/' . $pageName;
		if( initPage( $firstGuess )->get_exists() ){
			return $firstGuess;
		}

		$page = initPage( $pageName );
		$backlinks = $page->get_backlinks( 10 );
		$backlinks = array_filter( $backlinks, function( $link ) {
			return preg_match( '/^Template:Did you know nominations/', $link['title'] );
		} );
		if( count( $backlinks ) == 1 ){
			$backlinks = array_values( $backlinks );
			return $backlinks[0]['title'];
		}
		return false;
	}
