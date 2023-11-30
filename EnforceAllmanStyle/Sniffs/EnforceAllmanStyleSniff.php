<?php

/**
 * This sniff enforces the use of Allman-style brackets.
 *
 * PHP version 5
 *
 * @category PHP
 * @package EnforceAllmanStyle
 * @author Ethan Silver <ejrsilver@gmail.com>
 */

namespace Ejrsilver\Sniffs\EnforceAllmanStyle;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

final class EnforceAllmanStyleSniff implements Sniff
{
    /**
     * Should this sniff check functions?
     */
    public bool $checkFunctions = true;

    /**
     * Should this sniff check conditional statements?
     */
    public bool $checkConditional = true;

    /**
     * Should this sniff check interable statements?
     */
    public bool $checkLoops = true;

    /**
     * Should this sniff check try/catch?
     */
    public bool $checkTryCatch = true;

    /**
     * Registers the tokens this sniff will listen for.
     *
     * @return int[]
     */
    public function register()
    {
        return [
            T_FUNCTION,
            T_FOR,
            T_FOREACH,
            T_WHILE,
            T_DO,
            T_IF,
            T_ELSEIF,
            T_ELSE,
            T_TRY,
            T_CATCH,
            T_CLASS,
        ];
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        // Make sure the symbol we're looking at is affiliated with a scope.
        if (isset($tokens[$stackPtr]['scope_opener']) === false) {
            return;
        }

        $token = $tokens[$stackPtr]['code'];
        if (
            ($token === T_FUNCTION && $this->checkFunctions === false) ||
            (($token === T_FOREACH || $token === T_FOR || $token === T_WHILE || $token === T_DO) && $this->checkLoops === false) ||
            (($token === T_IF || $token === T_ELSEIF || $token === T_ELSE || $token === T_SWITCH) && $this->checkConditional === false) ||
            (($token === T_TRY || $token === T_CATCH) && $this->checkTryCatch === false)
        ) {
            return;
        }

        if ($token === T_CLASS || $token === T_TRY || $token = T_CATCH) {
            $stmtClose = $tokens[$stackPtr]['scope_opener'];
        } else {
            // Get end of statement.
            $stmtClose = $tokens[$stackPtr]['parenthesis_closer'];
        }

        $scopeBegin = $tokens[$stackPtr]['scope_opener'];

        $dcLine = $tokens[$stmtClose]['line'];
        $braceLine = $tokens[$scopeBegin]['line'];
        $lineDiff = ($braceLine - $dcLine);
        $tokenType = token_name($tokens[$stackPtr]['code']);
        $phpcsFile->recordMetric($stackPtr, $tokenType . " stmtCloseing brace placement", 'same line');
        $code = 'BraceOnSameLine';

        if ($lineDiff === 0) {
            $error = 'Opening brace should be on a new line.';

            $recorded = $phpcsFile->addError($error, $stackPtr, $code, [], 2, true);
            echo $recorded;
            $fix = $phpcsFile->addFixableError($error, $stackPtr, $code);
            if ($fix === true) {
                $phpcsFile->fixer->beginChangeset();

                echo "made it here.";
                $indent = $phpcsFile->findFirstOnLine([], $scopeBegin);

                if ($tokens[$indent]['code'] === T_WHITESPACE) {
                    $phpcsFile->fixer->addContentBefore($scopeBegin, $tokens[$indent]['content']);
                }

                if ($tokens[($scopeBegin - 1)]['code'] === T_WHITESPACE) {
                    $phpcsFile->fixer->replaceToken(($scopeBegin - 1), '');
                }

                $phpcsFile->fixer->addNewlineBefore($scopeBegin);
                $phpcsFile->fixer->endChangeset();
            }
        }
    }
}
