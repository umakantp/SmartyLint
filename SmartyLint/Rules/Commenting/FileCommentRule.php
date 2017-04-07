<?php
/**
 * Parses and verifies the doc comments for smarty file.
 *
 * Verifies that :
 * <ul>
 *  <li>A doc comment exists</li>
 *  <li>There is a blank newline after the short description.</li>
 *  <li>There is a blank newline between the long and short description.</li>
 *  <li>There is a blank newline between the long description and tags.</li>
 *  <li>Long and short description should start with capital letters.</li>
 *  <li>A space is present before the first and after the last parameter.</li>
 *  <li>Short description must be on single line.</li>
 *  <li>Short description must end with a full stop.</li>
 *  <li>No space is present between any two parameters.</li>
 *  <li>There must be one blank line between body and headline comments.</li>
 * </ul>
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-15 Umakant Patil
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
class Rules_Commenting_FileCommentRule implements SmartyLint_Rule
{

    /**
     * The file doc comment parser for the current method.
     *
     * @var SmartyLint_CommentParser_FileCommentParser
     */
    protected $commentParser = null;

    /**
     * The current SmartyLint_File object we are processing.
     *
     * @var SmartyLint_File
     */
    protected $currentFile = null;

    /**
     * Returns an array of tokens this test wants to listen for.
     *
     * @return array
     */
    public function register()
    {
        return array('SMARTY_DOC_COMMENT');
    }

    /**
     * Processes this rule, when one of its tokens is encountered.
     *
     * @param SmartyLint_File $smartylFile The file being scanned.
     * @param int             $stackPtr    The position of the current token in
     *                                     the stack passed in $tokens.
     *
     * @return void
     */
    public function process(SmartyLint_File $smartylFile, $stackPtr)
    {
        $this->currentFile = $smartylFile;
        $tokens = $smartylFile->getTokens();
        if ($stackPtr !== 0) {
            $error = 'Doc comment should be first thing in the file.';
            if (isset($tokens[$stackPtr]['line'])) {
                $smartylFile->addError($error, array(0, $tokens[$stackPtr]['line']), 'WrongPosition');
            } else {
                $smartylFile->addError($error, $stackPtr, 'WrongPosition');
            }
        }

        $startLen = (strlen($smartylFile->lDelimiter) + 1);
        if ($tokens[$stackPtr]['content'][$startLen] !== '*') {
            $error = 'You must use "'.$smartylFile->lDelimiter.'**" style comments for a file doc comment';
            if (isset($tokens[$stackPtr]['line'])) {
                $smartylFile->addError($error, array(0, $tokens[$stackPtr]['line']), 'WrongStyle');
            } else {
                $smartylFile->addError($error, $stackPtr, 'WrongStyle');
            }
            return;
        }

        if ($tokens[($stackPtr+1)]['type'] == 'NEW_LINE' && $tokens[($stackPtr+2)]['type'] == 'NEW_LINE') {
            $error = 'There should not be any blank line after file comment and before content';
            $smartylFile->addError($error, ($stackPtr+2), 'SpacingAfterDocComments');
        }

        try {
            $this->commentParser = new SmartyLint_CommentParser_FileCommentParser($tokens[$stackPtr]['content'], $smartylFile);
            $this->commentParser->parse();
        } catch (SmartyLint_CommentParser_ParserException $e) {
            $line = ($e->getLineWithinComment() + $tokens[$stackPtr]['line']);
            $smartylFile->addError($e->getMessage(), array(0, $line), 'FailedParse');
            return;
        }

        $comment = $this->commentParser->getComment();
        if (is_null($comment) === true) {
            $error = 'File doc comment is empty';
            $smartylFile->addError($error, $stackPtr, 'Empty');
            return;
        }

        $this->processParams($tokens[$stackPtr]['line']);

        // No extra newline before short description.
        $short = $comment->getShortComment();
        $newlineCount = 0;
        $newlineSpan = strspn($short, $smartylFile->eolChar);
        if ($short !== '' && $newlineSpan > 0) {
            $error = 'Extra newline(s) found before file comment short description';
            $smartylFile->addError($error, array(0, ($tokens[$stackPtr]['line'] + 1)), 'SpacingBeforeShort');
        }

        $newlineCount = (substr_count($short, $smartylFile->eolChar) + 1);

        // Exactly one blank line between short and long description.
        $long = $comment->getLongComment();
        if (empty($long) === false) {
            $between = $comment->getWhiteSpaceBetween();
            $newlineBetween = substr_count($between, $smartylFile->eolChar);
            if ($newlineBetween !== 2) {
                $error = 'There must be exactly one blank line between descriptions in file comment';
                $smartylFile->addError($error, array(0, ($tokens[$stackPtr]['line'] + $newlineCount + 1)), 'SpacingAfterShort');
            }

            $newlineCount += $newlineBetween;

            $testLong = trim($long);
            if (preg_match('|[A-Z]|', $testLong[0]) === 0) {
                $error = 'File comment long description must start with a capital letter';
                $smartylFile->addError($error, array(0, ($tokens[$stackPtr]['line'] + $newlineCount + 1)), 'LongNotCaptial');
            }
        }

        // Exactly one blank line before tags.
        $params = $this->commentParser->getTagOrders();
        if (count($params) > 1) {
            $newlineSpan = $comment->getNewlineAfter();
            if ($newlineSpan !== 2) {
                $error = 'There must be exactly one blank line before the tags in file comment';
                if ($long !== '') {
                    $newlineCount += (substr_count($long, $smartylFile->eolChar) - $newlineSpan + 1);
                }

                $smartylFile->addError($error, array(0, ($tokens[$stackPtr]['line'] + $newlineCount)), 'SpacingBeforeTags');
                $short = rtrim($short, $smartylFile->eolChar.' ');
            }
        }

        // Short description must be single line and end with a full stop.
        $testShort = trim($short);
        if (strlen($testShort)) {
            $lastChar  = $testShort[(strlen($testShort) - 1)];
            if (substr_count($testShort, $smartylFile->eolChar) !== 0) {
                $error = 'File comment short description must be on a single line';
                $smartylFile->addError($error, array(0, ($tokens[$stackPtr]['line'] + 1)), 'ShortSingleLine');
            }

            if (preg_match('|[A-Z]|', $testShort[0]) === 0) {
                $error = 'File comment short description must start with a capital letter';
                $smartylFile->addError($error, array(0, ($tokens[$stackPtr]['line'] + 1)), 'ShortNotCapital');
            }

            if ($lastChar !== '.') {
                $error = 'File comment short description must end with a full stop';
                $smartylFile->addError($error, array(0, ($tokens[$stackPtr]['line'] + 1)), 'ShortFullStop');
            }
        }
    }

    /**
     * Process the function parameter comments.
     *
     * @param int $commentStart The position in the stack where
     *                          the comment started.
     *
     * @return void
     */
    protected function processParams($commentStart)
    {
        $params = $this->commentParser->getParams();

        if (empty($params) === false) {
            $lastParm = (count($params) - 1);
            if (substr_count($params[$lastParm]->getWhitespaceAfter(), $this->currentFile->eolChar) !== 2) {
                $error  = 'Last parameter comment has a blank newline after it';
                $errorPos = ($params[$lastParm]->getLine() + $commentStart);
                $this->currentFile->addError($error, array(0, $errorPos), 'SpacingAfterParams');
            }

            // Parameters must appear immediately after the comment.
            if ($params[0]->getOrder() !== 2) {
                $error  = 'Parameters must appear immediately after the comment';
                $errorPos = ($params[0]->getLine() + $commentStart);
                $this->currentFile->addError($error, array(0, $errorPos), 'SpacingBeforeParams');
            }

            $previousParam = null;
            $spaceBeforeVar = 10000;
            $spaceBeforeComment = 10000;
            $longestType = 0;
            $longestVar = 0;

            foreach ($params as $param) {
                $paramComment = trim($param->getComment());
                $errorPos     = ($param->getLine() + $commentStart);

                // Make sure that there is only one space before the var type.
                if ($param->getWhitespaceBeforeType() !== ' ') {
                    $error = 'Expected 1 space before variable type';
                    $this->currentFile->addError($error, array(0, $errorPos), 'SpacingBeforeParamType');
                }

                $spaceCount = substr_count($param->getWhitespaceBeforeVarName(), ' ');
                if ($spaceCount < $spaceBeforeVar) {
                    $spaceBeforeVar = $spaceCount;
                    $longestType = $errorPos;
                }

                $spaceCount = substr_count($param->getWhitespaceBeforeComment(), ' ');

                if ($spaceCount < $spaceBeforeComment && $paramComment !== '') {
                    $spaceBeforeComment = $spaceCount;
                    $longestVar = $errorPos;
                }

                // Make sure they are in the correct order,
                // and have the correct name.
                $pos = $param->getPosition();

                $paramName = ($param->getVarName() !== '') ? $param->getVarName() : '[ UNKNOWN ]';

                if ($previousParam !== null) {
                    $previousName = ($previousParam->getVarName() !== '') ? $previousParam->getVarName() : 'UNKNOWN';

                    // Check to see if the parameters align properly.
                    if ($param->alignsVariableWith($previousParam) === false) {
                        $error = 'The variable names for parameters %s (%s) and %s (%s) do not align';
                        $data  = array(
                                  $previousName,
                                  ($pos - 1),
                                  $paramName,
                                  $pos,
                                 );
                        $this->currentFile->addError($error, array(0, $errorPos), 'ParameterNamesNotAligned', $data);
                    }

                    if ($param->alignsCommentWith($previousParam) === false) {
                        $error = 'The comments for parameters %s (%s) and %s (%s) do not align';
                        $data  = array(
                                  $previousName,
                                  ($pos - 1),
                                  $paramName,
                                  $pos,
                                 );
                        $this->currentFile->addError($error, array(0, $errorPos), 'ParameterCommentsNotAligned', $data);
                    }
                }

                if ($param->getVarName() === '') {
                    $error = 'Missing parameter name at position '.$pos;
                    $this->currentFile->addError($error, array(0, $errorPos), 'MissingParamName');
                }

                if ($param->getType() === '') {
                    $error = 'Missing type at position '.$pos;
                    $this->currentFile->addError($error, array(0, $errorPos), 'MissingParamType');
                }

                if ($paramComment === '') {
                    $error = 'Missing comment for param "%s" at position %s';
                    $data  = array(
                              $paramName,
                              $pos,
                             );
                    $this->currentFile->addError($error, array(0, $errorPos), 'MissingParamComment', $data);
                }

                $previousParam = $param;
            }

            if ($spaceBeforeVar !== 1 && $spaceBeforeVar !== 10000 && $spaceBeforeComment !== 10000) {
                $error = 'Expected 1 space after the longest type';
                $this->currentFile->addError($error, array(0, $longestType), 'SpacingAfterLongType');
            }

            if ($spaceBeforeComment !== 1 && $spaceBeforeComment !== 10000) {
                $error = 'Expected 1 space after the longest variable name';
                $this->currentFile->addError($error, array(0, $longestVar), 'SpacingAfterLongName');
            }
        }
    }
}
