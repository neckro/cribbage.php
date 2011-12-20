<?php

/*
 	Cribbage Scorer v0.1 - 20 Dec 2011 - neckro@gmail.com
	Do whatever you want with this.  No warranty, no license, no problem.
	Likely contains questionable design decisions and even more questionable PHP code.
	Requires no PHP modules but should be PHP 5.3 or better.

	Usage: Enter cards by values: Ace=A, Ten=T, Jack=J, Queen=Q, King=K.
	Starter card(s) will be added to all hands.
	No restrictions are enforced on the number of cards entered.

	Note that runs and doubles are counted separately to simplify scoring.
	Thus, a "double run of three" will count for 6 points, with the 2 extra points
	being counted with the doubles.

	Since we're not taking suits into consideration, flushes and knobs are not counted.
	For the lazy and forgetful, a cheat sheet is provided on the page.
*/

$players = intval($_GET['players']);
if (empty($players)) $players = 4;		// default number of players

class hand {
	protected static $default_glue = '';
	protected $hand = '';		// array of card values
	protected $starter = '';	// string for starter card
	public $is_empty = true;

	public function __construct($starter, $hand) {
		// $starter = starter card as raw string
		// $hand = player's hand as raw string
		$this->starter = self::normalizeCards($starter);
		$this->hand = self::parseCards($hand.$this->starter);
		if (count($this->hand) > strlen($this->starter)) $this->is_empty = false;
	}

 	public function printHand($glue='') {
		$h = self::printCards($this->hand);
		if (empty($this->starter)) return $h;
		$p = strpos($h, $this->starter);
		// remove the starter card from the list, surely there must be a better way to do this
		return substr($h, 0, $p) . substr($h, $p+strlen($this->starter));
	}

	public function printScore() {
		if ($this->is_empty) return '';
		$score = 0;
		$scoreitems = array();

		$runs = $this->findRuns();
		foreach ($runs as $run) {
			$runcount = 1;
			$runlength = count($run);
			foreach ($run as $c) foreach ($this->hand as $p) if ($c==$p) $runcount++;
			$runcount -= $runlength;
			$scoreitems[] = $this::tuples($runcount) . " run of $runlength";
			$score += $runlength * $runcount;	// run score
		}
		$pairs = $this->findPairs();
		$paircount = count($pairs);
		if ($paircount > 0) $scoreitems[] = "$paircount pair".($paircount>1?'s':'');
		$score += $paircount * 2;			// pairs score
		$fifteenscount = $this->findCardTotal(15);
		if ($fifteenscount > 0) $scoreitems[] = "$fifteenscount fifteen".($fifteenscount>1?'s':'');
		$score += $fifteenscount * 2;		// fifteens score

		if ($score == 0) return "No score.";
		return implode(" + ", $scoreitems) . " = $score";
	}

	protected function findPairs() {
		// find all pairs in hand, return 1d array of paired cards
		$pairs = array();
		for($n=0; $n<=count($this->hand); $n++) {
			for($r=1; $r<count($this->hand)-$n; $r++) {
				if ($this->hand[$n] == $this->hand[$n+$r]) $pairs[] = $this->hand[$n];
			}
		}
		sort($pairs);
		return $pairs;
	}

	protected function findCardTotal($total = 15) {
		// return number of times hand can add up to $total
		$hand = array();
		foreach($this->hand as $h) $hand[] = ($h>10?10:$h);	// clamp hand values
		$count = 0;
		$bits = count($hand);
		$max = pow(2, $bits);
		// iterate through all binary combinations and count how many match
		for($n=1; $n<$max; $n++) {
			$r = $n;
			$t = 0;
			for($b=$bits-1; $b>=0; $b--) {
				$s = $r-pow(2, $b);
				if ($s<0) continue;
				$r = $s;
				$t += $hand[$b];
			}
			if ($t==$total) $count++;
		}
		return $count;
	}

	protected function findRuns() {
		// returns an array of all runs of run_min or more cards
		$run_min = 3;
		if ($this->is_empty) return array();
		$runs = array();
		$hand = $this->hand;
		$used = array();
		foreach($hand as $n) $used[] = false;

		// there's probably a more elegant way to do this; way inefficient with large hand sizes but manageable with normal ones
		for($n=0; $n<count($hand); $n++) {
			if($used[$n] === true) continue;
			$testrun = array($hand[$n]);
			$testused = $used;
			foreach(array(-1, 1) as $m) {
				for($spread=1; true; $spread++) {
					$test = $hand[$n] + ($spread * $m);
					if ($test > 13 || $test < 1) break;
					$valid = false;
					for($x=0; $x<count($hand); $x++) {
						if ($hand[$x] == $test && $testused[$x] === false) {
							$testrun[] = $test;
							$testused[$x] = true;
							$valid = true;
							break;
						}
					}
					if ($valid === false) break;
				}
			}
			if (count($testrun) >= $run_min) {	// got one
				sort($testrun);
				for($x=0; $x<count($testused); $x++) if ($testused[$x] === true) $used[$x] = true;
				$runs[] = $testrun;
			}
		}	// else try next card
		return $runs;
	}

	public static function parseCards($input) {		// parse string to array of card values
		$cards = self::cardValues();
		$output = array();
		foreach(str_split(strtoupper($input)) as $c) if (array_key_exists($c, $cards)) $output[] = $cards[$c];
		sort($output, SORT_NUMERIC);
		if ($output == false) return array();
		return $output;
	}
	public static function printCards($input, $glue='') {		// print array of card values to string
		if (empty($input) || !is_array($input)) return '';
		$cards = self::cardValues();
		$output = '';
		sort($input, SORT_NUMERIC);
		foreach($input as $c) {
			$n = array_search($c, $cards);
			if ($n) $output .= $n;
		}
		return strtoupper($output);
	}
	public static function normalizeCards($input, $glue='') {		// parse string to array and back again
		return self::printCards(self::parseCards($input), $glue);
	}
	public static function cardValues() {
		$cards = array('A'=>1, 'T'=>10, 'J'=>11, 'Q'=>12,'K'=>13);
		for($n=2;$n<10;$n++) $cards[(string)$n] = (int)$n;
		asort($cards);
		return $cards;
	}
	public static function tuples($n) {
		$n = intval($n);
		$tuples = array('Single','Double','Triple','Quadruple');
		if ($n>count($tuples)) return 'Impossible';
		return $tuples[$n-1];
	}
}

function printTable($starter, $hands) {
	global $players;

	$starter = hand::normalizeCards($starter);
	$out  = '<tr><td>Starter:</td><td><input class="resettable" value="'.$starter.'" id="starter" name="starter" type="text" size="1" />';
	$out .= "</td><td></td></tr>\n";

	for($p=0; $p<=$players; $p++) {
		$score = new hand($starter, $hands[$p]);
		$out .= "<tr><td>";
		$out .= ($p==0 ? 'Crib' : "Player $p") . ":</td><td>";
		$out .= '<input class="resettable" type="text" value="'.$score->printHand().'" name="hands['.$p.']" /></td><td>';
		$out .= $score->printScore() . "</td></tr>\n";
	}
	return $out;
}

?><!DOCTYPE HTML PUBLIC "-//W3C//DTD HTML 4.0 Transitional//EN"><html>
<head>
<style type="text/css">
table {
	display: block;
	margin: 1em auto;
}
table td {
	text-align: left;
}
table tr td:first-child {
	text-align: right;
}
</style>

<script type="text/javascript" src="http://ajax.googleapis.com/ajax/libs/jquery/1.7.1/jquery.min.js"></script>

</head>
<body>

<form action="" method="POST">

<h1 style="text-align: center; font-family: fantasy; margin: .5em auto;">Fancy Cribbage Scorer</h1>

<table>
	<?php echo printTable($_POST['starter'], $_POST['hands']); ?>
	<tr><td></td><td><input type="submit" /><input type="button" value="Reset" id="resetButton" /></td><td></td></tr>
</table>

</form>

<div style="text-align: center;"><p>

<b>Extra Scoring</b>
<br />4-Card Flush ... 5
<br />5-Card Flush ... 6
<br />(Crib flush must include Starter card)
<br />Jack starter card (Heels) ... 2 to dealer
<br />Jack of same suit as starter card (Knobs) ... 1

</p></div>

<script type="text/javascript">
	$('#starter').focus();
	$('#resetButton').click(function() { $('.resettable').val(''); });
</script>

</body>
</html>
