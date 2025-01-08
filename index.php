<?php
    /*
    Wikicup statistics display � 2011, 2024 Harry Burt <jarry1250@gmail.com>

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

    // These you might want to change
    $categories = array( 'FA' => 'Featured Article', 'FL' => 'Featured List', 'FP' => 'Featured Picture', 'FT' => 'Featured Topic article', 'FAR' => 'Featured Article Review', 'GA' => 'Good Article', 'GT' => 'Good Topic article', 'GAR' => 'Good Article Review', 'DYK' => 'Did You Know', 'ITN' => 'In the News article' );
    $points = array( 'FA' => 200, 'FL' => 55, 'FP' => 30, 'FT' => 15, 'FAR' => 5, 'GA' => 35, 'GT' => 5, 'GAR' => 5, 'DYK' => 5, 'ITN' => 12 );
    $numToQualifyFromMassPool = array( 'January' => 64, 'February' => 64, 'March' => 32, 'April' => 32, 'May' => 16, 'June' => 16, 'July' => 8, 'August' => 8 );

    // Everything below this line you probably don't
    $thisYear = date( 'Y' );
    $year = ( isset( $_GET['year'] ) && preg_match( '/^20[12][0-9]$/', $_GET['year'] ) ) ? $_GET['year'] : $thisYear;
    $yearSupported = ( $year >= 2010 && $year <= $thisYear );

    // Adjust points for non-current years
    if( $year < 2025 ){
        // Categories are unchanged, FL points drop to 45
        $points = array( 'FA' => 200, 'FL' => 45, 'FP' => 30, 'FT' => 15, 'FAR' => 5, 'GA' => 35, 'GT' => 5, 'GAR' => 5, 'DYK' => 5, 'ITN' => 12 );
    }
    if( $year < 2020 ){
        $categories = array( 'FA' => 'Featured Article', 'GA' => 'Good Article', 'FL' => 'Featured List', 'FP' => 'Featured Picture', 'FPO' => 'Featured Portal', 'FT' => 'Featured Topic article', 'GT' => 'Good Topic article', 'DYK' => 'Did You Know', 'ITN' => 'In the News article', 'GAR' => 'Good Article Review' );
        $points = array('FA' => 200, 'GA' => 35, 'FL' => 45, 'FP' => 30, 'FPO' => 45, 'FT' => 10, 'GT' => 3, 'DYK' => 5, 'ITN' => 10, 'GAR' => 4);
    }
    if( $year < 2016 ){
        $points['FP'] = 20;
        $points['GA'] = 30;
    }
    if( $year < 2015 ){
        $points['FP'] = 35;
        $points['FA'] = 100;
    }
    if( $year < 2014 ){
        $points['FPO'] = 35;
    }
    if( $year < 2013 ){
        $points['DYK'] = 10;
        $points['GAR'] = 2;
    }
    if( $year < 2012 ){
        $points['DYK'] = 5;
        $points['FL'] = 40;
        $points['FS'] = 35;
        $points['FT'] = 15;
        $points['GT'] = 10;

        $categories = array( 'FA' => 'Featured Article', 'GA' => 'Good Article', 'FL' => 'Featured List', 'FP' => 'Featured Picture', 'FS' => 'Featured Sound', 'FPO' => 'Featured Portal', 'FT' => 'Featured Topic article', 'GT' => 'Good Topic article', 'DYK' => 'Did You Know', 'ITN' => 'In the News article', 'GAR' => 'Good Article Review' );
    }
    if( $year < 2011 ){
        $points['DYK'] = 10;
        $points['GA'] = 40;
        $points['FL'] = 40;
        unset( $categories['GAR'] );
        $categories['VP'] = 'Valued Pictures';
        $points['VP'] = 5;
    }

    require_once( '/data/project/jarry-common/public_html/global.php' );
    require_once( '/data/project/jarry-common/public_html/peachy/Init.php' );
    $site = Peachy::newWiki();

    $yearPage = initPage( 'Wikipedia:WikiCup/History/' . $year );
    if( !$yearPage->get_exists() ) die( "Bad year supplied" );
    $yearText = $yearPage->get_text();
    $pools = preg_split( '/==+ *Group/',$yearText );
    if( count( $pools ) > 1 ) array_shift( $pools ); // clear header row if any

    // Note: hardcoded maximum of 5 rounds
    $rounds = [];
    $totals = [];
    $roundScores = [];
    $roundReferenceScores = [];
    for( $i = 1; $i < 6; $i++ ) {
        $roundPage = initPage( 'Wikipedia:WikiCup/History/' . $year . '/Round '. $i );
        $isCurrentRound = !$roundPage->get_exists();
        $roundText = $isCurrentRound ? $yearText : getFromCache( $roundPage );

        // Save the scores from this round, sorted high to low, in order to calculate round points later.
		$roundScores[$i] = [];
		preg_match_all( "/\{\{Wikipedia:WikiCup\/Participant[0-9]*\|(.*?)}}[^']+'''(\d+)'''/", $roundText, $matches );
		$count = count( $matches[0] );
		for( $j = 0; $j < $count; $j++ ){
			$roundScores[$i][$matches[1][$j]] = $matches[2][$j];
		}
		arsort( $roundScores[$i] );
		$roundReferenceScores[$i] = array_values( $roundScores[$i] );
		$roundReferenceScores[$i] = array_slice( $roundReferenceScores[$i], 0, 16 );

		// Number of contributions per contestant, per category
        preg_match_all( "/\{\{.*?\|(.*?)}}.*?(\|\|[0-9]+){11}\|\|'''/", $roundText, $matches );
        $lines = $matches[0];
        $rounds[$i] = [];
        foreach( $lines as $index => $line ) {
            $bits = explode( '||', $line );
            array_shift( $bits );

            $contestantName = $matches[1][$index];
            foreach ( $categories as $key => $value ){
                if( !isset( $rounds[$i][$key] ) ) $rounds[$i][$key] = array();
                if( !isset( $rounds[$i][$key]['total'] ) ) $rounds[$i][$key]['total'] = 0;
                if( !isset( $totals[$key] ) ) $totals[$key] = array();
                if( !isset( $totals[$key]['total'] ) ) $totals[$key]['total'] = 0;
                $number = array_shift( $bits ) / $points[$key];
                if( $number > 0 ) {
                    $rounds[$i][$key][$contestantName] = $number;
                    $rounds[$i][$key]['total'] += $number;
                    $totals[$key]['total'] += $number;

                    if( !isset( $totals[$key][$contestantName] ) ) $totals[$key][$contestantName] = 0;
                    $totals[$key][$contestantName] += $number;
                }
            }
        }
        if( $isCurrentRound ) break;
    }

    // Calculate round and tournament points (only needed for 2025 onwards)
    $roundPoints = [];
    $tournamentPoints = [];
    foreach( $roundScores as $round => $scores ) {
		$roundPoints[$round] = [];
        foreach( $scores as $name => $score ) {
            $position = array_search($score, $roundReferenceScores[$round]); // In the event of a tie, everyone gets best position
            if( $position === false ) {
                continue;
            }
            if( !isset( $tournamentPoints[$name] ) ) {
				$tournamentPoints[$name] = 0;
            }
            if( !isset( $roundPoints[$name] ) ) {
                $roundPoints[$round][$name] = 0;
            }
            // echo "$round. $name => $score\n";
			$roundPoints[$round][$name] = pow(16 - $position, 2 ); // Square of inverse position
			$tournamentPoints[$name] += $roundPoints[$round][$name];
        }
		arsort( $roundPoints[$round] ); // Just to help debugging
    }
	arsort( $tournamentPoints );

    function getFromCache( Page &$page ){
        $filename = '/data/project/wikicup/public_html/cache/' . md5( $page->get_title() ) . '.txt';
        if( file_exists( $filename ) ) {
            // Cache HIT
            if( isset( $_GET['cache'] ) && $_GET['cache'] === 'purge' ) {
                // Delete the cache
                unlink( $filename );
            } else {
                return file_get_contents( $filename );
            }
        }
        // Cache MISS
        $text = $page->get_text();
        file_put_contents( $filename, $text );
        return $text;
    }

    echo get_html( 'header', 'Wikicup stats' );

    if( $year == $thisYear ){
        echo "\n\t\t<h3>Current status</h3>\n";
        echo "\t\t<p>Updated live (where appropriate). Unofficial. Subject to change; may not take into account ties properly. Something look wrong? Trying <a href=\"index.php?year=$year&cache=purge\">purging the cache</a>.</p>\n";
    } else {
        echo "\n<h3>Final result</h3>\n";
        echo "\t\t<p><strong>This is an archive.</strong>";
        if( !$yearSupported ) echo ' Since you are viewing a year prior to 2010, I\'m afraid I <strong>cannot guarantee that the data below makes sense</strong>.';
        echo "</p>\n";
    }


    if ($year >= 2025) {
?>
    <table>
        <thead>
        <tr>
            <th class="nobg"></th>
<?php
    foreach( $rounds as $i => $val ) {
        echo "\t\t\t<th>R$i</th>";
	}
?>
            <th>Total</th>
        </tr>
        </thead>
        <tbody>
<?php
    $i = 0;
	foreach( $tournamentPoints as $name => $tPoints ) {
		$class = ( $i++ % 2 == 0 ) ? "alt" : "orig";

		if( count($roundPoints) == 0 ) $roundPoints = [1 => []];

		echo "\t\t<tr>\n";
		echo "\t\t\t<th class='$class'>$name</th>\n";
		foreach( $roundPoints as $round => $rPoints ) {
			$rPointString = isset( $rPoints[$name] ) ? $rPoints[$name] : 0;
			echo "\t\t\t<td>$rPointString</td>\n";
		}
		echo "\t\t\t<td>$tPoints</td>\n";
		echo "\t\t</tr>\n";
	}
?>
        </tbody>
        </tbody>
        <tfoot>
        <tr>
            <td style="border:none; padding:0;" colspan="13"><p style="font-size:60%">Updated live. Total does not include bonus tournament points. Any participant not shown currently has 0 total (tournament) points.</p></td>
        </tr>
        </tfoot>
    </table>
		<?php
	} else {
        $currentRoundScores = isset($roundScores[count($roundScores)]) ? $roundScores[count($roundScores)] : [];
        if( count( $currentRoundScores ) >= 2 ) {
            $contestantScores = array_values( $currentRoundScores );
			$contestantNames = array_keys( $currentRoundScores );
			echo "\t\t<p><strong>Champion:</strong> " . $contestantNames[0] . " (" . $contestantScores[0] . "pts)</p>\n";
			echo "\t\t<p><strong>Runner up:</strong> " . $contestantNames[1] . " (" . $contestantScores[1] . "pts)</p>\n";
		}
    }
    $rounds[] = $totals;
?>
		<h3>Articles created</h3>
		<table>
		<thead>
		<tr>
			<th class="nobg"></th>
<?php
    foreach( $categories as $abbreviation => $name ){
        echo "\t\t\t<th><abbr title=\"$name\">$abbreviation</abbr></th>\n";
    }
?>
		</tr>
		</thead>
		<tbody>
<?php
    foreach( $rounds as $i => $round ) {
        $counts = array();
        $class = ( $i % 2 == 0 ) ? "alt" : "orig";
        $roundName = ($i < count($rounds)) ? "Round&nbsp;$i" : "Total";
        echo "\t\t<tr>\n\t\t\t<th class='$class'>$roundName</th>\n";
        foreach( $round as $key => $category ) {
            if( $key == 'DYK' && $year >= 2013 ) {
                // Needs a proper fix but will do for now
                echo "\t\t\t<td class='$class'>" . ceil( $category['total'] / 2 ) . "-" . $category['total'] . "</td>\n";
            } else {
                echo "\t\t\t<td class='$class'>" . $category['total'] . "</td>\n";
            }
        }
        echo "\t\t</tr>\n";
    }
?>
		</tbody>
		<tfoot>
		<tr>
		    <td style="border:none; padding:0;" colspan="13"><p style="font-size:60%">Updated live. Key: Non-unique article/category combinations.</p></td>
		</tr>
		</tfoot>
		</table>

		<h3>Top scorers</h3>
		<table>
		<thead>
		<tr>
			<th class="nobg"></th>
<?php
    foreach( $categories as $abbreviation => $name ){
        echo "\t\t\t<th><abbr title=\"$name\">$abbreviation</abbr></th>\n";
    }
?>
		</tr>
		</thead>
		<tbody>
<?php
    foreach( $rounds as $i => $round ){
        $class = ( $i % 2 == 0 ) ? "alt" : "orig";
        $roundName = ($i < count($rounds)) ? "Round&nbsp;$i" : "Total";
        echo "\t\t<tr>\n\t\t\t<th class='$class'>$roundName</th>\n";
        foreach ( $round as $category => $participants ) {
            unset( $participants['total'] );
            $top = array();

            // Sort high to low
            arsort( $participants );

            // Skim off only the top scorers
            $benchmark = 0;
            foreach( $participants as $name => $number ) {
                if( $benchmark > 0 && $number != $benchmark ) {
                    break;
                }
                $benchmark = $number;
                array_push( $top, $name );
            }
            $num = count( $top );

            if( $category == 'DYK' && $year >= 2013 ) {
                $benchmark = ceil( $benchmark / 2 ) . "-" . $benchmark;
            }

            if( $num > 0 && $num < 4 ) {
                echo "\t\t\t<td class='$class'>" . implode( ", ", $top ) . " ($benchmark)</td>\n";
            } else if( $num > 0 ) {
                echo "\t\t\t<td class='$class'><abbr title='" . implode( ", ", $top ) . "'>$num editors</abbr> ($benchmark)</td>\n";
            } else {
                echo "\t\t\t<td class='$class'>N/A</td>\n";
            }
        }
        echo "\t\t</tr>\n";
    }
?>
		</tbody>
		<tfoot>
		<tr>
		    <td style="border:none; padding:0;" colspan="13"><p style="font-size:60%">Updated live. Key: Top scorer/s (number of articles claimed)</p></td>
		</tr>
		</tfoot>
		</table>
	</div>
	<div class="additional">

		<p>Results by year: <?php
			$years = array();
			for( $i = 2010; $i <= intval( $thisYear ); $i++ ) {
				$years[] = "<a href=\"index.php?year=$i\">$i</a>";
			}
			echo implode( ' - ', $years ); ?></p>
<?php
	echo get_html( 'footer' );