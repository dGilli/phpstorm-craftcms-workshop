<?php
/**
 * Created by PhpStorm.
 * User: wbars
 * Date: 18/08/2018
 * Time: 11:07
 */

namespace lang\generator;

use PHPUnit\Framework\TestCase;
use workshop\lang\Compiler;
use workshop\lang\generator\CodeGenerator;
use workshop\lang\lexer\Scanner;
use workshop\lang\parser\Parser;

class CodeGeneratorTest extends TestCase {
    public function testGeneration() {
        $tokens = Scanner::parseTokens("echo 1 a = 1 - 2 b = 1 * 3 / 4 c = a - 4");
        $fileNode = Parser::parse($tokens);
        $generateCode = (new CodeGenerator())->generateCode($fileNode);
        $this->assertEquals("<?php echo 1;\$a=1-2;\$b=1*3/4;\$c=\$a-4;", $generateCode);
    }
}
