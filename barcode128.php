<?php
require 'tables.php';

class code128 {
    private $code = NULL;
    private $text = '';
    private $mode = 'Auto';
    private $len = 1;
    private $leafA = NULL, $leafB = NULL, $leafC = NULL, $parent = NULL, $minCode = NULL;

    public function __construct($text, $mode = 'Auto', $parent = NULL)
    {
	global $symCodeA, $symCodeB, $symCodeC;
	$this->parent = $parent;
	$this->text = $text;
	$this->mode = $mode;

	if($parent != NULL) {
	    $this->len = $this->parent->len + 1;
	    if($this->parent->mode != $mode) $this->len++;
	}

	if($mode === 'A' || $mode === 'B') list($this->code, $text) = sscanf($text, '%c%s');
	if($mode === 'C') list($this->code, $text) = sscanf($text, '%2d%s');

	if(strlen($text) > 0) {
	    if(array_key_exists(substr($text, 0, 1), $symCodeA)) $this->leafA = new code128($text, 'A', $this);
	    if(array_key_exists(substr($text, 0, 1), $symCodeB)) $this->leafB = new code128($text, 'B', $this);
	    if(strlen($text)>1)
		if(array_key_exists(substr($text, 0, 2), $symCodeC)) $this->leafC = new code128($text, 'C', $this);
	}

	// выборы минимального потомка
	$lA = ($this->leafA == NULL) ? PHP_INT_MAX : $this->leafA->minCode->len;
	$lB = ($this->leafB == NULL) ? PHP_INT_MAX : $this->leafB->minCode->len;
	$lC = ($this->leafC == NULL) ? PHP_INT_MAX : $this->leafC->minCode->len;

	if ($lA < $lB && $lA < $lC) $this->minCode = $this->leafA->minCode;
	else
	    if ($lB < $lC) $this->minCode = $this->leafB->minCode;
	    else
		if ($this->leafC != NULL) $this->minCode = $this->leafC->minCode;

	if($this->minCode == NULL)  $this->minCode = $this;

	return $this;
    }

    private function getCode()
    {
	$stack = array();
	$p = $this->minCode;

	while($p != NULL) {
	    array_push($stack, $p->code);

	    if($p->parent != NULL) {
		if($p->parent->mode == 'Auto') { array_push($stack, 'Start'.$p->mode); break;}
		if($p->mode != $p->parent->mode) array_push($stack, 'Code'.$p->mode);
	    }

	    $p = $p->parent;
	}

	return $stack;
    }

    private function printPattern($code, $posX, $res, $height)
    {
	for($i = 0; $i < strlen($code); $i++) {
	    $w = $res*intval($code[$i]);

	    if(!($i%2))
		echo "  <rect x='$posX' y='0' width='$w' height='$height' fill='#0'/>\n";

	    $posX += $w;
	}

	return $posX;
    }

    public function dump()
    {
	global $symCodeA, $symCodeB, $symCodeC;
	global $barPattern;

	$s = $this->getCode();

	$start = array_pop($s);
	echo $start . "\n";

	while(!empty($s)) {
	    $code = array_pop($s);
	    echo $code . "\n";
	}
	echo "CheckSum\n";
	echo "Stop\n";
    }

    public function printSVG($resolution=1, $height=50)
    {
	global $symCodeA, $symCodeB, $symCodeC;
	global $barPattern;
	
	$tbl = array('StartA' => $symCodeA, 'StartB' => $symCodeB, 'StartC' => $symCodeC,
	    'CodeA' => $symCodeA, 'CodeB' => $symCodeB, 'CodeC' => $symCodeC);

	$s = $this->getCode();

	$pos = 1;
	$offset = $resolution*11;
	$width = ((count($s) + 4)*11 + 2)*$resolution;

	echo "<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height'>\n";

	$c = array_pop($s);
	$symCode = $tbl[$c];
	$start = $symCode[$c]; // same start symbol in all tables
	$checksum = $start;

	$offset = $this->printPattern($barPattern[$start], $offset, $resolution, $height);

	while(!empty($s)) {
	    $c = array_pop($s);
	    $code = $symCode[$c];
	    $offset = $this->printPattern($barPattern[$code], $offset, $resolution, $height);
	    $checksum += $code*$pos;
	    $pos++;
	    if(array_key_exists($c, $tbl)) $symCode = $tbl[$c];
	}

	$offset = $this->printPattern($barPattern[$checksum%103], $offset, $resolution, $height);
	$offset = $this->printPattern($barPattern[$symCode['Stop']], $offset, $resolution, $height);

	echo "</svg>\n";
    }
}
    header('Content-Type: image/svg+xml');
    echo "<?xml version='1.0' encoding='UTF-8' standalone='no'?>\n\n";

    $n = new code128(html_entity_decode($_SERVER["QUERY_STRING"]));
    $n->printSVG();
?>
