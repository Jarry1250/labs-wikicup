<?php
	/*	
	 * Wikicup scoring bot code
	 * Originally the work of Soxred93
	 * Modified by Jarry1250 from December 2010
	 * Last edit December 2013
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
		array( 'name' => 'Featured Article', 'points' => 100, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'Good Article', 'points' => 30, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'Featured List', 'points' => 45, 'lineStart' => '\#', 'hasMultipliers' => false ),
		array( 'name' => 'Featured Picture', 'points' => 35, 'lineStart' => '\#', 'hasMultipliers' => false ),
		array( 'name' => 'Featured Portal', 'points' => 45, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'Featured Topic article', 'points' => 10, 'lineStart' => '\#\n', 'hasMultipliers' => false ),
		array( 'name' => 'Good Topic article', 'points' => 3, 'lineStart' => '\#\n', 'hasMultipliers' => false ),
		array( 'name' => 'Did You Know', 'points' => 5, 'lineStart' => '\#', 'hasMultipliers' => true ),
		array( 'name' => 'In the News article', 'points' => 10, 'lineStart' => '\#', 'hasMultipliers' => false ),
		array( 'name' => 'Good Article Review', 'points' => 4, 'lineStart' => '\#', 'hasMultipliers' => false ),
	);
	$year = date( 'Y' );
	$apiBase = 'http://en.wikipedia.org/w/api.php?format=json&';

	// Other stuff
	ini_set( 'memory_limit', '16M' );
	ini_set( 'display_errors', 1 );
	error_reporting( E_ALL );

	echo "<!-- Begin output at " . date( 'j F Y \a\t H:i' ) . " -->\n";

	require_once( '/data/project/jarry-common/public_html/peachy/Init.php' );
	$site = Peachy::newWiki( "livingbot" );

	echo "<-- Peachy loaded, trying file -->";

	$contestantPoints = array();
	$pointsPageName = 'Wikipedia:WikiCup/History/' . $year;
	$pointsPage = initPage( $pointsPageName );
	$pointsPageText = $pointsPageTextOriginal = $pointsPage->get_text();

	preg_match_all( "/\{\{(Wikipedia:WikiCup\/Participant[0-9]*)\|(.*?)\}\}/i", $pointsPageText, $contestants, PREG_PATTERN_ORDER );
	$templatename = $contestants[1][0];
	$contestants = $contestants[2];

	$filename = __DIR__ . '/log.txt';
	$contents = file_get_contents( $filename );

	echo "<!-- File loaded, trying main process -->";

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
					'(Multiplier\|([0-9.]+|none)\|([0-9.]+|none)\|([0-9.]+|none)\}\})?(\w)*$/', $line, $bits
				)
				){
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
					if( strpos( $contents, $article ) === false ){
						$append .= "\n* $contestant ([[$contestantSubpageName|submissions]]) claimed $article as a {$categories[$i]['name']}";
						if( $multiplier > 1 ){
							$append .= " with a $multiplier-times multiplier";
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

		preg_match( '/\{\{' . str_replace( "/", "\/", preg_quote( $templatename ) ) . '\|' . str_replace( "/", "\/", preg_quote( $contestant ) ) . "}}[^']+'''\d+'''/i", $pointsPageText, $n );
		if( $n[0] == $scoreline ){
			echo "No change\n";
		} else {
			echo "\nReplacing {$n[0]} with $scoreline...\n";
			$pointsPageText = str_replace( $n[0], $scoreline, $pointsPageText );
		}
		if( $changed ){
			echo "Loading multiplier assessment diff...\n";
			echo Diff::load( 'unified', $contestantSubmissionsOriginal, $contestantSubmissions );
			$page->edit( $contestantSubmissions, "Bot: Assessing multipliers due. ([[WP:CUPSUGGEST|Stuck for what to work on?]])" );
		}
	}

	if( strlen( $append ) > 0 ){
		file_put_contents( $filename, $append, FILE_APPEND );
		$logPage = initPage( 'Wikipedia:WikiCup/History/' . $year . '/log' );
		$logPage->edit( "\n" . trim( $append ), "Bot: adding new claims to the list", true, true, false, "ap" );
	}

	echo "Finished,";
	if( $pointsPageTextOriginal !== $pointsPageText ){
		echo " loading diff...\n";
		echo Diff::load( 'unified', $pointsPageTextOriginal, $pointsPageText );
	} else {
		echo "no change.\n";
	}
	$pointsPage->edit( $pointsPageText, "Bot: Updating WikiCup table", true );

	echo "<!-- End output at " . date( 'j F Y \a\t H:i' ) . " -->";

	function get( $url ) {
		$ch = curl_init();
		curl_setopt( $ch, CURLOPT_URL, $url );
		curl_setopt( $ch, CURLOPT_RETURNTRANSFER, 1 );
		curl_setopt( $ch, CURLOPT_USERAGENT, 'LivingBot' );
		curl_setopt( $ch, CURLOPT_HTTPHEADER, array( 'Content-Type: application/x-www-form-urlencoded;charset=UTF-8' ) );
		curl_setopt( $ch, CURLOPT_HEADER, false );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYHOST, 0 );
		curl_setopt( $ch, CURLOPT_SSL_VERIFYPEER, 0 );
		$response = curl_exec( $ch );
		if( curl_errno( $ch ) ){
			return curl_error( $ch );
		}
		curl_close( $ch );
		return $response;
	}

	function getJSON( $url ) {
		return json_decode( get( $url ), true );
	}

	function getApplicableLength( $pagename, $dykname ) {
		global $apiBase;
		// Get timestamp of promotionplus 12 hours
		$json = getJSON( $apiBase . "action=query&titles=" . urlencode( $dykname ) . "&prop=revisions" );
		$page = array_shift( $json['query']['pages'] );
		$timestamp = date( 'YmdHis', strtotime( $page['revisions'][0]['timestamp'] ) + ( 12 * 60 * 60 ) );

		// Get revid or article at that time
		$json = getJSON( $apiBase . "action=query&prop=revisions&titles=$pagename&rvprop=ids&rvstart=$timestamp&rvlimit=1" );
		$page = array_shift( $json['query']['pages'] );
		$revId = $page['revisions'][0]['revid'];

		// Count prose size of article at that time
		$json = getJSON( $apiBase . "action=parse&oldid=$revId&prop=text&disablepp" );
		$text = str_replace( "\n", "", $json['parse']['text']['*'] );
		$tagsToStrip = array( 'div', 'ul', 'sub', 'sup' );
		foreach( $tagsToStrip as $tagToStrip ){
			$regex = "/[<]" . $tagToStrip . ".*?[<]\/" . $tagToStrip . "[>]/";
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

	function getCreationDate( $pagename ) {
		global $apiBase;

		// Find first revision
		$json = getJSON( $apiBase . "action=query&titles=$pagename&prop=revisions&rvdir=newer&rvlimit=1" );
		if( !isset( $json['query']['pages'] ) ){
			return false;
		}
		$page = array_shift( $json['query']['pages'] );
		if( !isset( $page['revisions'], $page['revisions'][0], $page['revisions'][0]['timestamp'] ) ){
			return false;
		}

		return strtotime( $page['revisions'][0]['timestamp'] );
	}

	function getApplicableMultiplier( $pageName, $section, $line ) {
		// TODO: combine API queries
		global $year;
		$pageName = urlencode( $pageName );

		// Find last Wikidata revision before 1 January
		$json = getJSON( "https://www.wikidata.org/w/api.php?format=json&action=wbgetentities&sites=enwiki&titles=$pageName&props=info" );
		$existsOn = 0; // Didn't even exist on Wikidata
		if( count( $json['entities'] ) > 0 ){
			$qID = false;
			foreach( $json['entities'] as $entity ){
				$qID = $entity['id'];
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
						$links = array_keys( $links['links'] );
						$links = array_filter( $links, 'isWikipedia' );
						$existsOn = count( $links );
					}
				}
			}
		}

		$multiplicative = 1 + ( 0.2 * floor( $existsOn / 5 ) );

		$preadditive = 0;
		$postadditive = 0;
		if( $section == 'Did You Know' ){
			preg_match( '/Template:Did you know nominations.*?(?=\])/', $line, $bits );
			if( isset( $bits[0] ) ){
				$dykNom = $bits[0];
			} else {
				$dykNom = "Template:Did you know nominations/" . urldecode( $pageName );
			}
			if( getApplicableLength( $pageName, $dykNom ) > 5100 ){
				$preadditive += 5;
			}
			$creation = getCreationDate( $pageName );
			if( $creation && $creation < strtotime( '1 January ' . ( intval( date( 'Y' ) ) - 5 ) ) ){
				$postadditive += 5;
			}
		}

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