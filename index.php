<?php
/*
Wikicup statistics display � 2011 Harry Burt <jarry1250@gmail.com>

@todo: caching
@todo: login problem / new peachy

This program is free software; you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation; either version 2 of the License, or
( at your option ) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program; if not, write to the Free Software
Foundation, Inc., 59 Temple Place - Suite 330, Boston, MA 02111-1307, USA.
*/

// These you might want to change
$categories = array( 'FA' => 'Featured Article', 'GA' => 'Good Article', 'FL' => 'Featured List', 'FP' => 'Featured Picture', 'FPO' => 'Featured Portal', 'FT' => 'Featured Topic article', 'GT' => 'Good Topic article', 'DYK' => 'Did You Know', 'ITN' => 'In the News article', 'GAR' => 'Good Article Review' );
$points = array('FA' => 100, 'GA' => 35, 'FL' => 45, 'FP' => 30, 'FPO' => 45, 'FT' => 10, 'GT' => 3, 'DYK' => 5, 'ITN' => 10, 'GAR' => 4);
$totals = array( 'FA' => 0, 'GA' => 0, 'FL' => 0, 'FP' => 0, 'FPO' => 0, 'FT' => 0, 'GT' => 0, 'DYK' => 0, 'ITN' => 0, 'GAR' => 0 );
$massPoolMonths = array( 'January', 'February' );
$numToQualifyFromMassPool = 64;

// Everything below this line you probably don't
$thisYear = date( 'Y' );
$year = ( isset( $_GET['year'] ) && preg_match( '/^20[12][0-9]$/', $_GET['year'] ) ) ? $_GET['year'] : $thisYear;
$yearSupported = ( $year >= 2010 && $year <= $thisYear );

// Adjust points for non-current years
if( $year < 2016 ){
	$points['FP'] = 20;
	$points['GA'] = 30;
}
if( $year < 2015 ){
	$points['FP'] = 35;
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
$isMassPoolMonth = ( $year === $thisYear ) && in_array( date('F'), $massPoolMonths );

require_once( '/data/project/jarry-common/public_html/global.php' );
require_once( '/data/project/jarry-common/public_html/peachy/Init.php' );
$site = Peachy::newWiki();

$yearPage = initPage( 'Wikipedia:WikiCup/History/' . $year );
if( !$yearPage->get_exists() ) die( "Bad year supplied" );
$yearText = $yearPage->get_text();
$pools = preg_split( '/==+ *Group/',$yearText );
if( count( $pools ) > 1 ) array_shift( $pools ); //clear header row if any

$rounds = array();
$totals = array();
$i = 0;
while( true ){
	$roundPage = initPage( 'Wikipedia:WikiCup/History/'.$year.'/Round '.++$i );
	$isCurrentRound = !$roundPage->get_exists();
	$roundText = $isCurrentRound ? $yearText : getFromCache( $roundPage );

	preg_match_all( "/\{\{.*?\|(.*?)}}.*?(\|\|[0-9]+){11}\|\|'''/", $roundText, $matches );
	$lines = $matches[0];
	$rounds[$i] = array();
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

function makePresentable( $assoc ){
	$string = "";
	foreach( $assoc as $key=>$value ){
		$string .= $key . " ($value), ";
	}
	$string = substr( $string, 0, -2 );
	return $string;
}

function getFromCache( Page &$page ){
	$filename = '/data/project/wikicup/public_html/cache/' . md5( $page->get_title() ) . '.txt';
	if( file_exists( $filename ) ) {
		// Cache HIT
		return file_get_contents( $filename );
	}
	// Cache MISS
	$text = $page->get_text();
	file_put_contents( $filename, $text );
	return $text;
}

echo get_html( 'header', 'Wikicup stats' );

if( $year == $thisYear ){
	echo '<h3>Current status</h3>';
	echo '<p>Updated live (where appropriate). Unofficial. Subject to change; may not take into account ties properly.</p>';
} else {
	echo '<h3>Final result</h3>';
	echo '<p><strong>This is an archive.</strong>';
	if( !$yearSupported ) echo ' Since you are viewing a year prior to 2010, I\'m afraid I <strong>cannot guarantee that the data below makes sense</strong>.';
	echo '</p>';
}
?>
		<?php
		$poolWinners = array();
		$fastestLosers = array();
		foreach ( $pools as $pool ){
			preg_match_all( "/\{\{Wikipedia:WikiCup\/Participant[0-9]*\|(.*?)}}[^']+'''(\d+)'''/", $pool, $matches );
			$contestants = array();
			$count = count( $matches[0] );
			for( $i = 0; $i < $count; $i++ ){
				$contestants[$matches[1][$i]] = $matches[2][$i];
			}
			arsort( $contestants );
			if( $isMassPoolMonth ){
				$qualifying = array_slice( $contestants, 0, $numToQualifyFromMassPool );
			} else {
				$poolWinners = array_merge( $poolWinners, array_slice( $contestants, 0, 2 ) );
				$fastestLosers = array_merge( $fastestLosers,  array_slice( $contestants, 2 ) );
			}
		}
		arsort( $fastestLosers );
		$fastestLosers = array_slice( $fastestLosers, 0, count( $poolWinners ) );
		if( $isMassPoolMonth ){
			echo "<p><strong>Qualifying (in order):</strong> " . makePresentable( $qualifying ) . "</p>";
		} else {
			if( count( $pools ) === 1 ){
				$poolWinnerNames = array_keys( $poolWinners );
				echo "<p><strong>Champion:</strong> " . $poolWinnerNames[0] . " (" . $poolWinners[ $poolWinnerNames[0] ] ."pts)</p>
					<p><strong>Runner up:</strong> " . $poolWinnerNames[1] . " (" . $poolWinners[ $poolWinnerNames[1] ] ."pts)</p>";
			} else {
				echo "<p><strong>As group leader:</strong> " . makePresentable( $poolWinners ) . "</p>
		<p><strong>As \"fastest loser\":</strong> " . makePresentable( $fastestLosers ) . "</p>";
			}
		}
	?>
		<h3>Articles created</h3>
		<table>
		<thead>
		<tr>
			<th class="nobg"></th>
			<?php
				foreach( $categories as $key => $value ){
					echo "<th><abbr title=\"$value\">$key</abbr></th>\n";
				}
			?>
		</tr>
		</thead>
		<tbody>
		<?php
			$i = 0;
			foreach( $rounds as $round ) {
				$counts = array();
				if( ++$i % 2 == 1 ){
					$class = "orig";
				} else {
					$class= "alt";
				}
				echo "<tr>\n<th class='$class'>Round $i</th>\n";
				foreach( $round as $category ) echo "<td class='$class'>" . $category['total'] . "</td>\n";
				echo "</tr>\n";
			}
			if( ++$i % 2 == 1 ){
				$class = "orig";
			} else {
				$class= "alt";
			}
			echo "<tr>\n<th class='$class'>Total</th>\n";
			foreach ( $totals as $key => $total ){
				echo "<td class='$class'>" . $total['total'] . "</td>\n";
			}
			echo "</tr>";
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
				foreach( $categories as $key => $value ){
					echo "<th><abbr title=\"$value\">$key</abbr></th>\n";
				}
			?>
		</tr>
		</thead>
		<tbody>
		<?php
			$i = 0;
			foreach( $rounds as $round ){
				if( ++$i % 2 == 1 ){
					$class = "orig";
				} else {
					$class= "alt";
				}
				echo "<tr>\n<th class='$class'>Round $i</th>\n";
				foreach ( $round as $category => $contribs ){
					unset( $contribs['total'] );
					asort( $contribs );
					$top = array();

					$benchmark = ( count( $contribs ) == 0 ) ? 0 : max( $contribs );
					while ( count( $contribs ) != 0 ){
						$values = array_values( $contribs );
						$new = array_pop( $values );
						if( $new != $benchmark ){
							break;
						} else {
							$keys = array_keys( $contribs );
							array_push( $top, array_pop( $keys ) );
							array_pop( $contribs );
						}
					}
					$num = count( $top );
					if( $num > 0 && $num < 4 ) { 
						echo "<td class='$class'>" . implode( ", ", $top ) . " ($benchmark)</td>\n";
					} else if ( $num > 0 ){
						echo "<td class='$class'><abbr title='" . implode( ", ", $top ) . "'>$num editors</abbr> ($benchmark)</td>\n";
					} else {
						echo "<td class='$class'>N/A</td>\n";
					}
				}
				echo "</tr>\n";
			}
			if( $i % 2 == 1 ){
				$class = "orig";
			} else {
				$class= "alt";
			}
			echo "<tr>\n<th class='$class'>Total</th>\n";
			foreach ( $totals as $key => $contribs ){
				unset( $contribs['total'] );
				asort( $contribs );
				$top = array();
				$benchmark = ( count( $contribs ) == 0 ) ? 0 : max( $contribs );
				while ( count( $contribs ) != 0 ){
					$values = array_values( $contribs );
					$new = array_pop( $values );
					if( $new != $benchmark ){
						break;
					} else {
						$keys = array_keys( $contribs );
						array_push( $top, array_pop( $keys ) );
						array_pop( $contribs );
					}
				}
				
				$num = count( $top );
				if( $num > 0 && $num < 4 ) { 
					echo "<td class='$class'>" . implode( ", ", $top ) . " ($benchmark)</td>\n";
				} else if ( $num > 0 ){
					echo "<td class='$class'><abbr title='" . implode( ", ", $top ) . "'>$num editors</abbr> ($benchmark)</td>\n";
				} else {
					echo "<td class='$class'>N/A</td>\n";
				}
			}
			echo "</tr>";
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
		<p>Results by year: <a href="index.php?year=2010">2010</a> - <a href="index.php?year=2011">2011</a> - <a href="index.php?year=2012">2012</a> - <a href="index.php?year=2013">2013</a> - <a href="index.php?year=2014">2014</a> - <a href="index.php?year=2015">2015</a></p>
<?php
	echo get_html( 'footer' );
?>
