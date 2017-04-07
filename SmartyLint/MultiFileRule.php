<?php
/**
 * Represents a SmartyLint multi-file rule for checking coding standards.
 *
 * A multi-file rule is called after all files have been checked using the
 * regular rules. The process() method is passed an array of SmartyLint_File
 * objects, one for each file checked during the script run.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      https://github.com/umakantp/SmartyLint
 */
interface SmartyLint_MultiFileRule
{
    /**
     * Called once per script run to allow for processing of this rule.
     *
     * @param array(SmartyLint_File) $files The SmartyLint files processed
     *                                      during the script run.
     *
     * @return void
     */
    public function process(array $files);
}
