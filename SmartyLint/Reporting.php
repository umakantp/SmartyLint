<?php
/**
 * A class to manage, print reporting.
 *
 * @package   SmartyLint
 * @author    Greg Sherwood <gsherwood@squiz.net>
 * @author    Marc McIntyre <mmcintyre@squiz.net>
 * @author    Umakant Patil <me@umakantpatil.com>
 * @copyright 2006-2012 Squiz Pty Ltd (ABN 77 084 670 600)
 * @license   https://github.com/umakantp/SmartyLint/blob/master/LICENSE BSD Licence
 * @link      http://smartylint.com
 */

include_once dirname(__FILE__).'/../SmartyLint.php';

class SmartyLint_Reporting {

    /**
     * Actually generates the report.
     *
     * @param array $filesViolations Collected violations.
     *
     * @return integer
     */
    public function printReport($filesViolations) {
        $reportData  = $this->prepare($filesViolations);
        $numErrors = $this->generate($reportData);
        return $numErrors;
    }

    /**
     * Prints all errors and warnings for each file processed.
     *
     * Errors and warnings are displayed together, grouped by file.
     *
     * @param array $report Prepared report.
     *
     * @return integer
     */
    public function generate($report) {
        $errorsShown = 0;
        $width = 70;

        foreach ($report['files'] as $filename => $file) {
            if (empty($file['messages']) === true) {
                continue;
            }

            echo PHP_EOL.'FILE: ';
            if (strlen($filename) <= ($width - 9)) {
                echo $filename;
            } else {
                echo '...'.substr($filename, (strlen($filename) - ($width - 9)));
            }

            echo PHP_EOL;
            echo str_repeat('-', $width).PHP_EOL;

            echo 'FOUND '.$file['errors'].' ERROR(S) ';
            if ($file['warnings'] > 0) {
                echo 'AND '.$file['warnings'].' WARNING(S) ';
            }

            echo 'AFFECTING '.count($file['messages']).' LINE(S)'.PHP_EOL;
            echo str_repeat('-', $width).PHP_EOL;

            // Work out the max line number for formatting.
            $maxLine = 0;
            foreach ($file['messages'] as $line => $lineErrors) {
                if ($line > $maxLine) {
                    $maxLine = $line;
                }
            }

            $maxLineLength = strlen($maxLine);

            // The length of the word ERROR or WARNING; used for padding.
            if ($file['warnings'] > 0) {
                $typeLength = 7;
            } else {
                $typeLength = 5;
            }

            // The padding that all lines will require that are
            // printing an error message overflow.
            $paddingLine2  = str_repeat(' ', ($maxLineLength + 1));
            $paddingLine2 .= ' | ';
            $paddingLine2 .= str_repeat(' ', $typeLength);
            $paddingLine2 .= ' | ';

            // The maxium amount of space an error message can use.
            $maxErrorSpace = ($width - strlen($paddingLine2) - 1);

            foreach ($file['messages'] as $line => $lineErrors) {
                foreach ($lineErrors as $column => $colErrors) {
                    foreach ($colErrors as $error) {
                        $message = $error['message'];
                        $message .= ' ('.$error['source'].')';

                        // The padding that goes on the front of the line.
                        $padding  = ($maxLineLength - strlen($line));
                        $errorMsg = wordwrap(
                            $message,
                            $maxErrorSpace,
                            PHP_EOL.$paddingLine2
                        );

                        echo ' '.str_repeat(' ', $padding).$line.' | '.$error['type'];
                        if ($error['type'] === 'ERROR') {
                            if ($file['warnings'] > 0) {
                                echo '  ';
                            }
                        }

                        echo ' | '.$errorMsg.PHP_EOL;
                        $errorsShown++;
                    }
                }
            }
        }

        echo "\n\n";

        return ($file['errors'] + $file['warnings']);
    }

    /**
     * Pre-process and package violations for all files.
     *
     * Used by error reports to get a packaged list of all errors in each file.
     *
     * @param array $filesViolations List of found violations.
     *
     * @return array
     */
    public function prepare(array $filesViolations) {
        $report = array(
                'totals' => array(
                        'warnings' => 0,
                        'errors'   => 0
                    ),
                'files'  => array()
            );
        foreach ($filesViolations as $filename => $fileViolations) {
            $warnings = $fileViolations['warnings'];
            $errors = $fileViolations['errors'];
            $numWarnings = $fileViolations['numWarnings'];
            $numErrors = $fileViolations['numErrors'];
            $report['files'][$filename] = array(
                    'warnings' => 0,
                    'errors'   => 0,
                    'messages' => array(),
                );
            if ($numErrors === 0 && $numWarnings === 0) {
                // Perfect score!
                continue;
            }
            $report['files'][$filename]['errors'] = $numErrors;
            $report['files'][$filename]['warnings'] = $numWarnings;

            $report['totals']['errors'] += $numErrors;
            $report['totals']['warnings'] += $numWarnings;

            // Merge errors and warnings.
            foreach ($errors as $line => $lineErrors) {
                foreach ($lineErrors as $column => $colErrors) {
                    $newErrors = array();
                    foreach ($colErrors as $data) {
                        $newErrors[] = array(
                                'message'  => $data['message'],
                                'source'   => $data['source'],
                                'type'     => 'ERROR',
                            );
                    }
                    $errors[$line][$column] = $newErrors;
                }
                ksort($errors[$line]);
            }

            foreach ($warnings as $line => $lineWarnings) {
                foreach ($lineWarnings as $column => $colWarnings) {
                    $newWarnings = array();
                    foreach ($colWarnings as $data) {
                        $newWarnings[] = array(
                                'message'  => $data['message'],
                                'source'   => $data['source'],
                                'type'     => 'WARNING'
                            );
                    }

                    if (isset($errors[$line]) === false) {
                        $errors[$line] = array();
                    }

                    if (isset($errors[$line][$column]) === true) {
                        $errors[$line][$column] = array_merge(
                            $newWarnings,
                            $errors[$line][$column]
                        );
                    } else {
                        $errors[$line][$column] = $newWarnings;
                    }
                }
                ksort($errors[$line]);
            }

            ksort($errors);
            $report['files'][$filename]['messages'] = $errors;
        }
        ksort($report['files']);

        return $report;
    }
}
