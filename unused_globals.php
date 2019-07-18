<?php
require_once __DIR__ . '/vendor/autoload.php';

use PHPFixer\Globals\RemoveSuperglobals;
use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;
use Symfony\Component\Finder\Finder;


$finder = new Finder();
$finder->files()->in(__DIR__ . '/playground/')->name("*.php");
$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);

/** @var SplFileInfo $file */
foreach ($finder as $file) {
    $contents = $file->getContents();
    try {
        $ast = $parser->parse($contents);
        $fixer = new RemoveSuperglobals();

        $traverser = new NodeTraverser();
        $traverser->addVisitor(new class extends NodeVisitorAbstract
        {
            public function enterNode(Node $node)
            {
                /** @var RemoveSuperglobals $fixer */
                global $fixer;
                if ($node instanceof Function_ || $node instanceof Node\Stmt\ClassMethod) {
                    $fixer->prepare($node);
                }
            }
        });
        $traverser->traverse($ast);

        if ($fixer->needToFix()) {
            $newContents = $fixer->fix($contents);
            file_put_contents($file->getRealPath(), $newContents);
        }
    } catch (\PhpParser\Error $error) {
        printf("%s in file '%s'\n", $error->getMessage(), $file->getRealPath());
    }
}