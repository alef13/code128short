<?php
require 'tables.php';

class code128 {
    private $code = NULL;
    private $text = '';
    private $mode = 'Auto';
    private $len = 1;
    private $leafB = NULL, $leafC = NULL, $parent = NULL, $minCode = NULL;

    public function __construct($text, $mode = 'Auto', $parent = NULL)
    {
	global $symCode;
	$this->parent = $parent;
	$this->text = $text;
	$this->mode = $mode;

	if($parent != NULL) {
	    $this->len = $this->parent->len + 1;
	    if($this->parent->mode != $mode) $this->len++;
	}

	if($mode == 'B') list($this->code, $text) = sscanf($text, '%c%s');
	if($mode == 'C') list($this->code, $text) = sscanf($text, '%2d%s');

	if(strlen($text)>0)
	    if(array_key_exists(substr($text, 0, 1), $symCode)) $this->leafB = new code128($text, 'B', $this);
	if(strlen($text)>1)
	    if(array_key_exists(substr($text, 0, 2), $symCode)) $this->leafC = new code128($text, 'C', $this);

	if($this->leafB == NULL && $this->leafC == NULL) $this->minCode = $this;
	else {
	    $this->minCode = ($this->leafB != NULL) ? $this->leafB->minCode : $this->leafC->minCode;
	    if($this->leafC != NULL)
		if($this->minCode->len > $this->leafC->minCode->len) $this->minCode = $this->leafC->minCode;
	}

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

    public function printSVG($resolution=1, $height=50)
    {
	global $symCode;
	global $barPattern;

	$s = $this->getCode();

	$pos = 1;
	$offset = $resolution*11;
	$width = ((count($s) + 4)*11 + 2)*$resolution;

	echo "<svg xmlns='http://www.w3.org/2000/svg' width='$width' height='$height'>\n";

	$start = $symCode[array_pop($s)];
	$checksum = $start;

	$offset = $this->printPattern($barPattern[$start], $offset, $resolution, $height);

	while(!empty($s)) {
	    $code = $symCode[array_pop($s)];
	    $offset = $this->printPattern($barPattern[$code], $offset, $resolution, $height);
	    $checksum += $code*$pos;
	    $pos++;
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
