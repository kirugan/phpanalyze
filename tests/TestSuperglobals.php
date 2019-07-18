<?php

use PHPFixer\Globals\RemoveSuperglobals;
use PhpParser\Node\Stmt\Function_;
use PhpParser\ParserFactory;
use PHPUnit\Framework\TestCase;

class TestSuperglobals extends TestCase
{
    /**
     * @test
     * @dataProvider multipleLinesProvider
     * @param string $code
     * @param string $expect
     */
    public function multipleLines(string $code, string $expect)
    {
        $node = $this->getFunctionNode($code);
        $actual = $this->fix($node, $code);

        $this->assertEquals($expect, $actual);
    }

    public function multipleLinesProvider(): array
    {
        $top =<<<'EOL'
<?php
function test() {
    global $GLOBALS;
    global $test1, $test2;
}
EOL;
        $topExpect =<<<'EOL'
<?php
function test() {
    global $test1, $test2;
}
EOL;
        $bottom =<<<'EOL'
<?php
function test() {
    global $test1, $test2;
    global $GLOBALS;
}
EOL;
        $bottomExpect =<<<'EOL'
<?php
function test() {
    global $test1, $test2;
}
EOL;
        $middle =<<<'EOL'
<?php
function test() {
    global $test1, $test2;
    global $GLOBALS;
    global $test3;
}
EOL;
        $middleExpect =<<<'EOL'
<?php
function test() {
    global $test1, $test2;
    global $test3;
}
EOL;
        $complex =<<<'EOL'
<?php
function test() {
    global $test1, $_GET, $test2;
    global $GLOBALS;
    global $test3;
    global $_POST, $test4;
}
EOL;
        $complexExpect =<<<'EOL'
<?php
function test() {
    global $test1, $test2;
    global $test3;
    global $test4;
}
EOL;

        return [
            [$top, $topExpect],
            [$bottom, $bottomExpect],
            [$middle, $middleExpect],
            [$complex, $complexExpect],
        ];
    }

    /**
     * @test
     * @dataProvider singleLineProvider
     * @param string $code
     * @param string $expect
     */
    public function singleLine(string $code, string $expect)
    {
        $node = $this->getFunctionNode($code);
        $actual = $this->fix($node, $code);

        $this->assertEquals($expect, $actual);
    }

    public function singleLineProvider(): array
    {
        $simpleCode =<<<'EOL'
<?php
function test() {
    global $GLOBALS;
}
EOL;
        $simpleExpect =<<<'EOL'
<?php
function test() {
}
EOL;
        $beginning =<<<'EOL'
<?php
function test() {
    global $GLOBALS, $test;
}
EOL;
        $beginningExpect =<<<'EOL'
<?php
function test() {
    global $test;
}
EOL;
        $end =<<<'EOL'
<?php
function test() {
    global $test, $GLOBALS;
}
EOL;
        $endExpect =<<<'EOL'
<?php
function test() {
    global $test;
}
EOL;
        $middle =<<<'EOL'
<?php
function test() {
    global $test, $GLOBALS, $test3;
}
EOL;
        $middleExpect =<<<'EOL'
<?php
function test() {
    global $test, $test3;
}
EOL;

        return [
            [$simpleCode, $simpleExpect],
            [$beginning, $beginningExpect],
            [$end, $endExpect],
            [$middle, $middleExpect],
        ];
    }

    private function fix($node, $code): string
    {
        $fixer = new RemoveSuperglobals();
        $fixer->prepare($node);
        return $fixer->fix($code);
    }

    private function getFunctionNode(string $sourceCode): Function_
    {
        $parser = (new ParserFactory)->create(ParserFactory::PREFER_PHP5);
        $ast = $parser->parse($sourceCode);

        return $ast[0];
    }
}