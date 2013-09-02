<?php
/**
 * Tokenizes Smarty code.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      http://smartylint.com
 */
class SmartyLint_Tokenizers_Smarty {

    /**
     * Creates an array of tokens when given some Smarty code.
     *
     * @param string $string  The string to tokenize.
     * @param string $eolChar The EOL character to use for splitting strings.
     * @param string $sD      Start delimiter used to tokenize Smarty strings.
     * @param string $eD      End delimiter used to tokenize Smarty strings.
     *
     * @return array
     */
    public function tokenizeString($string, $eolChar = '\n', $sD, $eD) {
        $token = array();
        $chars = str_split($string);
        $c = 0;
        $inComment = false;
        $isOtherThanSpaceTabLineEmpty = false;
        $currentLine = 1;

        for ($i=0; $i<count($chars); $i++) {
            $char = $chars[$i];
            $b = $c - 1;
            switch ($char) {

                case '':
                    $token[$c] = array('type' => 'EMPTY', 'content' => '', 'line' => $currentLine);
                    $c++;
                    break;

                case "\n":
                    $token[$c] = array('type' => 'NEW_LINE', 'content' => "\n", 'line' => $currentLine);
                    $currentLine++;
                    $c++;
                    break;

                case ' ':
                    if (isset($token[$b]) && $token[$b] == $char) {
                        $token[$b]['content'] = $token[$b]['content'] . $char;
                    } else {
                        $token[$c] = array('type' => 'SPACE', 'content' => ' ', 'line' => $currentLine);
                        $c++;
                    }
                    break;

                case "\t":
                    $token[$c] = array('type' => 'TAB', 'content' => "\t", 'line' => $currentLine);
                    $c++;
                    break;

                case "<":
                    if ((isset($chars[($i+1)]) && $chars[($i+1)] == "!") &&
                        (isset($chars[($i+2)]) && $chars[($i+2)] == "-") &&
                        (isset($chars[($i+3)]) && $chars[($i+3)] == "-")) {
                        // This is HTML comment.
                        $token[$c] = array();
                        $token[$c]['type'] = "HTML_COMMENT";

                        $j = 1;
                        $t = '';
                        $n = '';
                        $m = '';
                        $str = $char;
                        $multiline = false;
                        $start = $currentLine;
                        while (1) {
                            $t = $chars[($i+$j)];
                            isset($chars[($i+$j)]) ? $t = $chars[($i+$j)] : $t = '';
                            isset($chars[($i+($j-1))]) ? $n = $chars[($i+($j-1))] : $n = '';
                            isset($chars[($i+($j-2))]) ? $m = $chars[($i+($j-2))] : $m = '';
                            $str .= $t;
                            $j++;
                            if ($t == "\n") {
                                $currentLine++;
                                $multiline = true;
                            }
                            if ($t == '>' && $n == '-' && $m == '-') {
                                break;
                            }
                        }
                        $token[$c]['content'] = $str;
                        $token[$c]['line'] = $currentLine;
                        if ($multiline) {
                            $token[$c]['multi'] = true;
                            $token[$c]['start'] = $start;
                        }
                        $i = $i+($j-1);
                        $c++;
                    } else {
                        if (isset($token[$b]) && $token[$b]['type'] == 'UNKNOWN') {
                            $token[$b]['content'] = $token[$b]['content'] . $char;
                        } else {
                            $token[$c] = array('type' => 'UNKNOWN', 'content' => $char, 'line' => $currentLine);
                            $c++;
                        }
                    }

                    $isOtherThanSpaceTabLineEmpty = true;
                    break;
                case $sD:
                    // If letter after delimiter is *, then its comment.
                    $j = 0;
                    if ($chars[($i+1)] == "*") {
                        $token[$c] = array();
                        $token[$c]['type'] = "COMMENT";

                        // There was no letter other than space, tab, new line, and empty went before
                        // this comment then its doc comments.
                        if (!$isOtherThanSpaceTabLineEmpty) {
                            $token[$c]['type'] = "DOC_COMMENT";
                        }

                        $j = 1;
                        $t = '';
                        $n = '';
                        $str = $char;
                        $multiline = false;
                        $start = $currentLine;
                        while (1) {
                            $t = $chars[($i+$j)];
                            $n = $chars[($i+($j-1))];
                            $str .= $t;
                            $j++;
                            if ($t == "\n") {
                                $currentLine++;
                                $multiline = true;
                            }
                            if ($t == $eD && $n == '*') {
                                break;
                            }
                        }
                        $token[$c]['content'] = $str;
                        $token[$c]['line'] = $currentLine;
                        if ($multiline) {
                            $token[$c]['multi'] = true;
                            $token[$c]['start'] = $start;
                        }
                        $c++;
                        $i = $i+($j-1);
                    } else {
                        if (isset($token[$b]) && $token[$b]['type'] == 'UNKNOWN') {
                            $token[$b]['content'] = $token[$b]['content'] . $char;
                        } else {
                            $token[$c] = array('type' => 'UNKNOWN', 'content' => $char, 'line' => $currentLine);
                            $c++;
                        }
                    }
                    $isOtherThanSpaceTabLineEmpty = true;
                    break;

                default:
                    if (isset($token[$b]) && $token[$b]['type'] == 'UNKNOWN') {
                        $token[$b]['content'] = $token[$b]['content'] . $char;
                    } else {
                        $token[$c] = array('type' => 'UNKNOWN', 'content' => $char, 'line' => $currentLine);
                        $c++;
                    }
                    $isOtherThanSpaceTabLineEmpty = true;
                    break;

            }
        }
        return $token;
    }
}
