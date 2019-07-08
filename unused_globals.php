<?php
require_once __DIR__ . '/vendor/autoload.php';

use PhpParser\Node;
use PhpParser\Node\Stmt\Function_;
use PhpParser\NodeDumper;
use PhpParser\PrettyPrinter;
use PhpParser\NodeTraverser;
use PhpParser\NodeVisitorAbstract;
use PhpParser\ParserFactory;

error_reporting(E_ALL);
ini_set('display_errors', true);
ini_set('memory_limit', -1);

/**
 * Как грепнуть по global + SUPERGLOBAL:
 * global .*\$(GLOBALS|_GET|_POST|_REQUEST) <- сюда надо понакидать остальных суперглобалов
 */

$finder = new \Symfony\Component\Finder\Finder();
$finder->files()->in("/Users/k.paltsev/data")->name("apps.lib.php");

$parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);
$globalsFinder = new \PhpParser\NodeFinder();

$errors = [];
$stats = [];
$globalsInUse = 0;
$globalsNotUsed = 0;
$uniqueGlobals = [];
/** @var SplFileInfo $file */
foreach ($finder as $file) {
  if (strpos($file->getRealPath(), "Skin.php") !== false) continue;
  $contents = $file->getContents();
  if (!hasFunctions($contents) || !hasGlobal($contents)) {
    continue;
  }

  try {
    $ast = $parser->parse($contents);
    $traverser = new NodeTraverser();
    $traverser->addVisitor(new class extends NodeVisitorAbstract {
      public function enterNode(Node $node) {
        global $globalsFinder;
        global $file;
        global $globalsInUse, $globalsNotUsed, $uniqueGlobals;

        if ($node instanceof Function_ || $node instanceof Node\Stmt\ClassMethod) {
          $body = (new PhpParser\PrettyPrinter\Standard())->prettyPrint($node->stmts);
          $globals = $globalsFinder->findInstanceOf($node->stmts, Node\Stmt\Global_::class);
          $usedGlobals = [];
          /** @var PhpParser\Node\Stmt\Global_ $global */
          foreach ($globals as $global) {
            /** @var PhpParser\Node\Expr\Variable $var */
            foreach ($global->vars as $var) {
              if (!is_string($var->name)) {
                printf("Expression in global at %s:%d\n", $file->getRealPath(), $global->getLine());
                continue;
              }

              $uniqueGlobals[$var->name] = 1;
              // todo нужна более умная проверка :) типа $varName реально используется не внутри коммента
              $count = substr_count($body, "\${$var->name}");
              // пока тупая проверка на то используется ли где-то дальше переменная
              if ($count == 1) {
                printf("Var \$%s is not used in %s:%d\n", $var->name, $file->getRealPath(), $global->getLine());
                $globalsNotUsed++;
              } else {
                $usedGlobals[] = $var;
                $globalsInUse++;
              }
            }

            $global->vars = $usedGlobals;
          }
        }
      }
    });
    $traverser->traverse($ast);

    saveIntoFile($file->getRealPath(), $ast);
    exit;
  } catch (\Throwable $error) {
    $errors[$file->getFilename()] = $error->getMessage();
  }
}

printf("Globals in use %d\n", $globalsInUse);
printf("Globals not used %d\n", $globalsNotUsed);
printf("Unique globals %d\n", count($uniqueGlobals));
print_r($stats);
if ($errors) {
  printf("Errors:\n");
  print_r($errors);
  exit;
}

function incrStats(string $key) {
  global $stats;
  @$stats[$key]++;
}

function hasFunctions(string $contents): bool {
  return strpos($contents, "function ");
}

function hasGlobal(string $contents): bool {
  return strpos($contents, "global ");
}

function saveIntoFile(string $file, $ast) {
  $prettyPrinter = new PrettyPrinter\Standard;
  file_put_contents($file, $prettyPrinter->prettyPrintFile($ast));
}