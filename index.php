<?php
/*
Wikicup statistics display © 2011 Harry Burt <jarry1250@gmail.com>

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

require_once( '/data/project/jarry-common/public_html/global.php' );

//These you might want to change
$categories = array( 'FA' => 'Featured Article', 'GA' => 'Good Article', 'FL' => 'Featured List', 'FP' => 'Featured Picture', 'FPO' => 'Featured Portal', 'FT' => 'Featured Topic article', 'GT' => 'Good Topic article', 'DYK' => 'Did You Know', 'ITN' => 'In the News article', 'GAR' => 'Good Article Review' );
$points = array('FA' => 100, 'GA' => 30, 'FL' => 45, 'FP' => 35, 'FPO' => 35, 'FT' => 10, 'GT' => 3, 'DYK' => 5, 'ITN' => 10, 'GAR' => 4);
$totals = array( 'FA' => 0, 'GA' => 0, 'FL' => 0, 'FP' => 0, 'FPO' => 0, 'FT' => 0, 'GT' => 0, 'DYK' => 0, 'ITN' => 0, 'GAR' => 0 );
$masspoolmonths = array( 'January', 'February' );
$masspoolqual = 64;

//Everything below this line you probably don't
$year = date( 'Y' );
$inmasspool = in_array( date('F'), $masspoolmonths );

function makepresentable( $assoc ){
	$string = "";
	foreach( $assoc as $key=>$value ){
		$string .= $key . " ($value), ";
	}
	$string = substr( $string, 0, -2 );
	return $string;
}

echo "<!--";
require_once( '/data/project/jarry-common/public_html/mw-peachy/Init.php' );
$site = Peachy::newWiki( 'anon' );

$year_page = initPage( 'Wikipedia:WikiCup/History/'.$year );
$year_text = $year_page->get_text();
$pools = preg_split( '/==+ *Pool/',$year_text );
if( count( $pools ) > 1 ) array_shift( $pools ); //clear header row if any

$log_page = initPage( 'Wikipedia:WikiCup/History/'.$year.'/Running totals' );
$existing_text = $log_page->get_text();
echo "-->";
	
$tables = explode( '{|',$existing_text );
array_shift( $tables );

echo get_html( 'header', 'Wikicup stats' );
?>
		<h3>Current status</h3>
		<p>Updated live. Unofficial. Subject to change; may not take into account ties properly.</p>
		<?php
		$poolwinners = array();
		$fastestlosers = array();
		foreach ( $pools as $pool ){
			preg_match_all( "/#AAFFAA[^{]+\{\{.*?\|(.*?)}}[^']+'''(\d+)'''/", $pool, $matches );
			$contestants = array();
			$count = count( $matches[0] );
			for( $i = 0; $i < $count; $i++ ){
				$contestants[$matches[1][$i]] = $matches[2][$i];
			}
			arsort( $contestants );
			if( $inmasspool ){
				$qualifying = array_slice( $contestants, 0, $masspoolqual );
			} else {
				$poolwinners = array_merge( $poolwinners, array_slice( $contestants, 0, 2 ) );
				$fastestlosers = array_merge( $fastestlosers,  array_slice( $contestants, 2 ) );
			}
		}
		arsort( $fastestlosers );
		$fastestlosers = array_slice( $fastestlosers, 0, count( $poolwinners ) );
		
		if( !$inmasspool ){
			if( count( $pools ) === 1 ){
				$poolwinnernames = array_keys( $poolwinners );
				echo "<p><strong>Champion:</strong> " . $poolwinnernames[0] . " (" . $poolwinners[ $poolwinnernames[0] ] ."pts)</p>
					<p><strong>Runner up:</strong> " . $poolwinnernames[1] . " (" . $poolwinners[ $poolwinnernames[1] ] ."pts)</p>";
			} else {
				echo "<p><strong>As pool leader:</strong> " . makepresentable( $poolwinners ) . "</p>
		<p><strong>As \"fastest loser\":</strong> " . makepresentable( $fastestlosers ) . "</p>";
			}
		} else {
			echo "<p><strong>Qualifying (in order):</strong> " . makepresentable( $qualifying ) . "</p>";
		}
	?>
		<h3>Articles created (method A)</h3>
		<table>
		<thead>
		<tr>
			<th class="nobg"></th>
			<?php
				foreach( $categories as $key => $value ){
					echo "<th><abbr title=\"$value\">$key</abbr></th>\n";
				}
			?>
			<!--<th><abbr title="Multipliers used">M</abbr></th>-->
		</tr>
		</thead>
		<tbody>
		<?php
			$i = 1;
			foreach( $tables as $table ){
				$counts = array();
				if( $i % 2 == 1 ){
					$class = "orig";
				} else {
					$class= "alt";
				}
				$round_page = initPage( 'Wikipedia:WikiCup/History/'.$year.'/Round '.$i );
				if( !$round_page->get_exists() ){
					$round_text = $round_page->get_text();
				} else {
					$round_text = $year_text;
				}
				echo "<tr>\n<th class='$class'>Round $i</th>\n";
				preg_match_all( "/(\|\|[0-9]+){11}\|\|'''/", $round_text, $matches );
				$lines = $matches[0];
				foreach( $lines as $line ) {
					$bits = explode( '||', $line );
					array_shift( $bits );
					foreach ( $categories as $key => $value ){
						if( !isset( $counts[$key] ) ) $counts[$key] = 0;
						$number = array_shift( $bits ) / $points[$key];
						$counts[$key] += $number;
						$totals[$key] += $number;
					}
				}
				foreach( $counts as $count ) echo "<td class='$class'>" . $count . "</td>\n";
				echo "</tr>\n";
				//$mcount = substr_count( $table, "||" );
				//echo "<td class='$class'>" . $mcount . "</td>\n";
				//$totals['m'] += $mcount;
				$i++;
			}
			if( $i % 2 == 1 ){
				$class = "orig";
			} else {
				$class= "alt";
			}
			echo "<tr>\n<th class='$class'>Total</th>\n";
			foreach ( $totals as $key=>$total ){
				echo "<td class='$class'>" . $total . "</td>\n";
			}
			echo "</tr>";
		?>
		</tbody>
		<tfoot>
		<tr>
		<td style="border:none; padding:none;" colspan="13"><p style="font-size:60%">Updated live. Key: Non-unique article/category combinations.</p></td>
		</tr>
		</tfoot>
		</table>
		<h3>Articles created (method B)</h3>
		<table>
		<thead>
		<tr>
			<th class="nobg"></th>
			<?php
				foreach( $categories as $key => $value ){
					echo "<th><abbr title=\"$value\">$key</abbr></th>\n";
				}
			?>
			<!--<th><abbr title="Multipliers used">M</abbr></th>-->
		</tr>
		</thead>
		<tbody>
		<?php
$totals = array( 'FA' => 0, 'GA' => 0, 'FL' => 0, 'FP' => 0, 'FPO' => 0, 'FT' => 0, 'GT' => 0, 'DYK' => 0, 'ITN' => 0, 'GAR' => 0 );
			$i = 1;
			foreach( $tables as $table ){
				if( $i % 2 == 1 ){
					$class = "orig";
				} else {
					$class= "alt";
				}
				if( $i==2 ){
					echo "<tr>\n<th class='$class'>Round 2 (estimated)</th>\n";
					foreach ( $categories as $key => $value ){
						preg_match_all( '/\|([^|]+)\|\|'.$value.'\|\|/', $table, $matches );
						$countall = round( count( $matches[1] ) * 5/4 );
						$countuni = round( count( array_unique( $matches[1] ) ) * 5/4 );
						$extra = ( $countuni !== $countall ) ? " ($countall)" : "";
						echo "<td class='$class'>" . $countuni . $extra . "</td>\n";
					}
				} else {
					echo "<tr>\n<th class='$class'>Round $i</th>\n";
					foreach ( $categories as $key => $value ){
						preg_match_all( '/\|([^|]+)\|\|'.$value.'\|\|/', $table, $matches );
						$countall = count( $matches[1] );
						$countuni = count( array_unique( $matches[1] ) );
						$extra = ( $countuni !== $countall ) ? " ($countall)" : "";
						echo "<td class='$class'>" . $countuni . $extra . "</td>\n";
						$totals[$key] += $countuni;
					}
				}
				echo "</tr>\n";
				$i++;
			}
			if( $i % 2 == 1 ){
				$class = "orig";
			} else {
				$class= "alt";
			}
			echo "<tr>\n<th class='$class'>Total</th>\n";
			foreach ( $totals as $key=>$total ){
				echo "<td class='$class'>" . $total . "</td>\n";
			}
			echo "</tr>";
		?>
		</tbody>
		<tfoot>
		<tr>
		<td style="border:none; padding:none;" colspan="13"><p style="font-size:60%">Not updated live. Key: Unique article/category cominations (number of claims, if different). Round 2 original sscores affected by data loss.</p></td>
		</tr>
		</tfoot>
		</table>
		
		<h3>Top scorers</h3>
		<p><strong>NOTE:</strong> Round 2's data (and hence the totals as well) is slightly wrong; ~20% of submissions have been lost. Treat with caution.</p>
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
			$i = 1;
			foreach( $tables as $table ){
				if( $i % 2 == 1 ){
					$class = "orig";
				} else {
					$class= "alt";
				}
				echo "<tr>\n<th class='$class'>Round $i</th>\n";
				foreach ( $categories as $key => $value ){
					preg_match_all( '/\|([^|]+)\|\|[^|]+\|\|'.$value.'\|\|/', $table, $matches );
					$contribs = array_count_values( $matches[1] );
					asort( $contribs );
					$top = array();

					$benchmark = ( count( $contribs ) == 0 ) ? 0 : max( $contribs );
					while ( count( $contribs ) != 0 ){
						$new = array_pop( array_values( $contribs ) );
						if( $new != $benchmark ){
							break;
						} else {
							array_push( $top, array_pop( array_keys( $contribs ) ) );
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
				$i++;
			}
			if( $i % 2 == 1 ){
				$class = "orig";
			} else {
				$class= "alt";
			}
			echo "<tr>\n<th class='$class'>Total</th>\n";
			foreach ( $categories as $key => $value ){
				preg_match_all( '/\|([^|]+)\|\|[^|]+\|\|'.$value.'\|\|/', $existing_text, $matches );
				$contribs = array_count_values( $matches[1] );
				asort( $contribs );
				$top = array();
				$benchmark = ( count( $contribs ) == 0 ) ? 0 : max( $contribs );
				while ( count( $contribs ) != 0 ){
					$new = array_pop( array_values( $contribs ) );
					if( $new != $benchmark ){
						break;
					} else {
						array_push( $top, array_pop( array_keys( $contribs ) ) );
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
		<td style="border:none; padding:none;" colspan="13"><p style="font-size:60%">Not updated live. Key: Top scorer/s (number of articles claimed)</p></td>
		</tr>
		</tfoot>
		</table>
<?php
	echo get_html( 'footer' );
?>