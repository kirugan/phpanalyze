<?php
namespace PHPFixer\Globals;

use InvalidArgumentException;
use PhpParser\Node\Expr\Variable;
use PhpParser\Node\Stmt\ClassMethod;
use PhpParser\Node\Stmt\Function_;
use PhpParser\Node\Stmt\Global_;
use PhpParser\NodeFinder;

class RemoveSuperglobals
{
    const SUPERGLOBALS = [
        'GLOBALS',
        '_SERVER',
        '_GET',
        '_POST',
        '_FILES',
        '_COOKIE',
        '_SESSION',
        '_REQUEST',
        '_ENV',
    ];

    private $nodeFinder;
    private $varsToFix = [];

    public function __construct()
    {
        $this->nodeFinder = new NodeFinder();
    }

    public function prepare($node)
    {
        if (!($node instanceof Function_) && !($node instanceof ClassMethod)) {
            throw new InvalidArgumentException("unexpected class: " . get_class($node));
        }

        $globals = $this->nodeFinder->findInstanceOf($node->stmts, Global_::class);
        /** @var Global_ $global */
        foreach ($globals as $global) {
            /** @var Variable $var */
            foreach ($global->vars as $var) {
                if (!is_string($var->name)) {
                    continue;
                }

                if (in_array($var->name, self::SUPERGLOBALS)) {
                    $this->varsToFix[] = $var;
                }
            }
        }
    }

    public function fix(string $file): string
    {
        $lines = explode("\n", $file);
        $lineAdjustment = 0;
        foreach ($this->varsToFix as $var) {
            $lineToFix = $var->getAttribute('startLine') + $lineAdjustment;
            $idxToFix = $lineToFix - 1;

            $line = $lines[$idxToFix];
            $fixedLine = $this->removeVarFromGlobalsLine($line, $var->name);

            if (preg_match('/^\s*$/', $fixedLine)) {
                array_splice($lines, $idxToFix, 1);
                $lineAdjustment--;
            } else {
                $lines[$idxToFix] = $fixedLine;
            }
        }

        return join("\n", $lines);
    }

    private function removeVarFromGlobalsLine(string $line, string $varName): string
    {
        $txt = str_replace("\$$varName", '', $line);
        $txt = preg_replace('/global\s+,/', 'global', $txt);
        $txt = preg_replace('/global\s*;/', '', $txt);
        $txt = preg_replace('/,\s*;/', ';', $txt);
        $txt = preg_replace('/,\s*,/', ',', $txt);

        return $txt;
    }
}