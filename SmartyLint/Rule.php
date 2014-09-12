<?php
/**
 * Represents a SmartyLint rule for checking coding standards.
 *
 * @package   SmartyLint
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2013-14
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */

interface SmartyLint_Rule {

    /**
     * Registers the tokens that this rule wants to listen for.
     *
     * An example return value for a rule that wants to listen for new line
     * and any comments would be:
     *
     * <code>
     *    return array(
     *            'NEW_LINE',
     *            'DOC_COMMENT',
     *            'COMMENT',
     *           );
     * </code>
     *
     * @return array(int)
     * @see    Tokens.php
     */
    public function register();

    /**
     * Called when one of the token types that this rule is listening for
     * is found.
     *
     * The stackPtr variable indicates where in the stack the token was found.
     * A rule can acquire information this token, along with all the other
     * tokens within the stack by first acquiring the token stack:
     *
     * <code>
     *    $tokens = $smartylFile->getTokens();
     *    echo 'Encountered a '.$tokens[$stackPtr]['type'].' token';
     *    echo 'token information: ';
     *    print_r($tokens[$stackPtr]);
     * </code>
     *
     * If the rule discovers an anomilty in the code, they can raise an error
     * by calling addError() on the SmartyLint_File object, specifying an error
     * message and the position of the offending token:
     *
     * <code>
     *    $smartylFile->addError('Encountered an error', $stackPtr);
     * </code>
     *
     * @param SmartyLint_File $smartylFile The SmartyLint file where the
     *                                        token was found.
     * @param int                  $stackPtr  The position in the SmartyLint
     *                                        file's token stack where the token
     *                                        was found.
     *
     * @return void
     */
    public function process(SmartyLint_File $smartylFile, $stackPtr);
}
