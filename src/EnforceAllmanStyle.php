<?php

namespace Ejrsilver\EnforceAllmanStyle;

use PHP_CodeSniffer\Files\File;
use PHP_CodeSniffer\Sniffs\Sniff;
use PHP_CodeSniffer\Util\Tokens;

final class EnforceAllmanStyle implements Sniff
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

    private function fix_same_line(File $phpcsFile, array $tokens, $open)
    {
        $error = 'Opening brace should be on a new line.';
        $fix = $phpcsFile->addFixableError($error, $open, 'BraceOnSameLine');

        if ($fix === true)
        {
            $hasTrailingAnnotation = false;
            for ($nextLine = ($open + 1); $nextLine < $phpcsFile->numTokens; $nextLine++)
            {
                if ($tokens[$open]['line'] !== $tokens[$nextLine]['line'])
                {
                    break;
                }
                if (isset(Tokens::$phpcsCommentTokens[$tokens[$nextLine]['code']]) === true)
                {
                    $hasTrailingAnnotation = true;
                }
            }
            $phpcsFile->fixer->beginChangeset();
            $indent = $phpcsFile->findFirstOnLine([], $open);

            if ($hasTrailingAnnotation === false || $nextLine === false)
            {
                if ($tokens[$indent]['code'] === T_WHITESPACE)
                {
                    $phpcsFile->fixer->addContentBefore($open, $tokens[$indent]['content']);
                }

                if ($tokens[($open - 1)]['code'] === T_WHITESPACE)
                {
                    $phpcsFile->fixer->replaceToken(($open - 1), '');
                }

                $phpcsFile->fixer->addNewlineBefore($nextLine);
            }
            else
            {
                $phpcsFile->fixer->replaceToken($open, '');
                $phpcsFile->fixer->addNewlineBefore($nextLine);
                $phpcsFile->fixer->addContentBefore($nextLine, '{'); // }

                if ($tokens[$indent]['code'] === T_WHITESPACE)
                {
                    $phpcsFile->fixer->addContentBefore($nextLine, $tokens[$indent]['content']);
                }
            }

            $phpcsFile->fixer->endChangeset();
        }
    }

    private function fix_blank_lines(File $phpcsFile, $stackPtr, $tokens, $open, $closer, $lineDiff, $prev)
    {
        $error = 'Opening brace should be on the line after the declaration; found %s blank line(s)';
        $data = [($lineDiff - 1)];

        $prevNonWs = $phpcsFile->findPrevious(T_WHITESPACE, ($open - 1), $closer, true);
        if ($prevNonWs !== $prev)
        {
            $phpcsFile->addError($error, $open, 'BraceSpacing', $data);
        }
        else
        {
            $fix = $phpcsFile->addFixableError($error, $open, 'BraceSpacing', $data);
            if ($fix === true)
            {
                $phpcsFile->fixer->beginChangeset();
                for ($i = $open; $i > $prev; $i--)
                {
                    if ($tokens[$i]['line'] === $tokens[$open]['line'])
                    {
                        if ($tokens[$i]['line'] === $tokens[$open]['line'])
                        {
                            if ($tokens[$i]['column'] === 1)
                            {
                                $phpcsFile->fixer->addNewlineBefore($i);
                            }

                            continue;
                        }
                        $phpcsFile->fixer->replaceToken($i, '');
                    }
                    $phpcsFile->fixer->endChangeset();
                }
            }
        }
        $ignore = Tokens::$phpcsCommentTokens;
        $ignore[] = T_WHITESPACE;
        $next = $phpcsFile->findNext($ignore, ($open + 1), null, true);
        if ($tokens[$next]['line'] === $tokens[$open]['line'])
        {
            if ($next === $tokens[$stackPtr]['scope_closer'])
            {
                return;
            }

            $error = 'Opening brace must be the last content on the line.';
            $fix = $phpcsFile->addFixableError($error, $open, 'ContentAfterBrace');
            if ($fix === true)
            {
                $phpcsFile->fixer->addNewline($open);
            }
        }

        if ($lineDiff !== 1)
        {
            return;
        }

        $lineStart = $phpcsFile->findFirstOnLine(T_WHITESPACE, $stackPtr, true);
        $startColumn = $tokens[$lineStart]['column'];
        $braceIndent = $tokens[$open]['column'];

        if ($braceIndent !== $startColumn)
        {
            $expected = ($startColumn - 1);
            $found = ($braceIndent - 1);
            $error = 'Opening brace indented incorrectly; expected %s spaces, found %s';
            $data = [
                $expected,
                $found
            ];

            $fix = $phpcsFile->addFixableError($error, $open, 'BraceIndent', $data);
            if ($fix === true)
            {
                $indent = str_repeat(' ', $expected);
                if ($found === 0)
                {
                    $phpcsFile->fixer->replaceToken($open, $indent);
                }
                else
                {
                    $phpcsFile->fixer->replaceToken(($open - 1), $indent);
                }
            }
        }
    }

    public function process(File $phpcsFile, $stackPtr)
    {
        $tokens = $phpcsFile->getTokens();

        if (isset($tokens[$stackPtr]['scope_opener']) === false)
        {
            return;
        }

        $token = $tokens[$stackPtr]['code'];
        if (
            ($token === T_FUNCTION && $this->checkFunctions === false) ||
            (($token === T_FOREACH || $token === T_FOR || $token === T_WHILE || $token === T_DO) && $this->checkLoops === false) ||
            (($token === T_IF || $token === T_ELSEIF || $token === T_ELSE || $token === T_SWITCH) && $this->checkConditional === false) ||
            (($token === T_TRY || $token === T_CATCH) && $this->checkTryCatch === false)
        )
        {
            return;
        }

        $open = $tokens[$stackPtr]['scope_opener'];
        $closer = $tokens[$stackPtr]['parenthesis_closer'];

        // Find end of declaration.
        $prev = $phpcsFile->findPrevious(Tokens::$emptyTokens, ($open - 1), $closer, true);

        $dcLine = $tokens[$prev]['line'];
        $braceLine = $tokens[$open]['line'];

        $lineDiff = ($braceLine - $dcLine);

        $tokenType = token_name($tokens[$stackPtr]['code']);

        if ($lineDiff === 0)
        {
            $this->fix_same_line($phpcsFile, $tokens, $open);
            $phpcsFile->recordMetric($stackPtr, $tokenType . " opening brace placement", 'same line');
        }
        else if ($lineDiff > 1)
        {
            $this->fix_blank_lines($phpcsFile, $stackPtr, $tokens, $open, $closer, $lineDiff, $prev);
            $phpcsFile->recordMetric($stackPtr, $tokenType . ' opening brace placement', 'new line');
        }
    }
}
