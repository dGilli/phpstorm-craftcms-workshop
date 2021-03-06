<?php

namespace workshop\lang\parser;

use workshop\lang\lexer\Token;
use workshop\lang\lexer\TokenTypes;
use workshop\lang\parser\nodes\AssignmentNode;
use workshop\lang\parser\nodes\ASTNode;
use workshop\lang\parser\nodes\BinaryStatementNode;
use workshop\lang\parser\nodes\EchoNode;
use workshop\lang\parser\nodes\FileNode;
use workshop\lang\parser\nodes\NumberNode;
use workshop\lang\parser\nodes\VariableNode;

class Parser {
    public static function parse(array $tokens): FileNode {
        return self::parseFile(new SkippingWhitespacesIterator($tokens));
    }

    private static function parseFile(SkippingWhitespacesIterator $tokens): FileNode {
        $statements = [];
        while ($tokens->hasNext()) {
            $statements[] = self::parseStatement($tokens);
        }
        return new FileNode($statements);
    }

    private static function parseStatement(SkippingWhitespacesIterator $tokens): ASTNode {
        $currentType = $tokens->current()->getType();
        if ($currentType == TokenTypes::IDENTIFIER) {
            $assignment = self::parseAssignment($tokens);
            if ($assignment != null) {
                return $assignment;
            }
        }
        return self::parseScalar($tokens);
    }

    private static function parseScalar(SkippingWhitespacesIterator $tokens): ASTNode
    {
        $currentType = $tokens->current()->getType();
        if ($currentType == TokenTypes::IDENTIFIER) {
            $statement = self::parseBinaryStatement($tokens);
            if ($statement !== null) {
                return $statement;
            }
            return self::parseVariable($tokens);
        }
        if ($currentType == TokenTypes::NUMBER || $currentType == TokenTypes::MINUS) {
            $statement = self::parseBinaryStatement($tokens);
            if ($statement !== null) {
                return $statement;
            }
            return self::parseNumber($tokens);
        }
        throw new ParserException("Parse error: expected digit or identifier, got: " . $tokens->current()->getValue());
    }

    private static function parseVariable(SkippingWhitespacesIterator $tokens): ?VariableNode {
        if ($tokens->current()->getType() != TokenTypes::IDENTIFIER) return null;
        $name = $tokens->current()->getValue();
        $tokens->advance();
        return new VariableNode($name);
    }

    private static function parseNumber(SkippingWhitespacesIterator $tokens): ?NumberNode {
        $negative = false;
        if ($tokens->current()->getType() == TokenTypes::MINUS) {
            $tokens->advance();
        }
        $value = $tokens->current()->getValue();
        $tokens->advance();
        if ($negative) {
            $value = $value * -1;
        }
        return new NumberNode($value);
    }

    private static function parseBinaryStatement(SkippingWhitespacesIterator $tokens): ?BinaryStatementNode {
        $mark = $tokens->mark();
        $left = self::parseVariable($tokens);
        if ($left == null) {
            $tokens->rollbackTo($mark);
            $left = self::parseNumber($tokens);
        }
        if ($left == null) throw new ParserException("Parse error");
        if ($tokens->hasNext()) {
            $operationType = $tokens->current()->getType();
            if ($operationType == TokenTypes::MINUS ||
                $operationType == TokenTypes::PLUS ||
                $operationType == TokenTypes::MULTIPLY ||
                $operationType == TokenTypes::DIVIDE) {
                $tokens->advance();
                $right = self::parseScalar($tokens);
                if ($right == null) {
                    throw new ParserException("Parse error: expected expression");
                }
                return new BinaryStatementNode($left, $right, $operationType);
            }
        }
        $tokens->rollbackTo($mark);
        return null;
    }

    private static function parseAssignment(SkippingWhitespacesIterator $tokens): ?AssignmentNode {
        $mark = $tokens->mark();
        $variableNode = self::parseVariable($tokens);
        if ($tokens->current()->getType() == TokenTypes::EQUALS) {
            $tokens->advance();
            $expr = self::parseScalar($tokens);
            if ($expr == null) {
                throw new ParserException("Parse error: expected expression");
            }
            return new AssignmentNode($variableNode, $expr);
        }
        $tokens->rollbackTo($mark);
        return null;
    }
}

class SkippingWhitespacesIterator {
    /**
     * @var Token[]
     */
    private $tokens;
    private $index = 0;

    public function __construct(array $tokens) {
        $this->tokens = $tokens;
        $this->skipWhitespaces();
    }

    public function current(): Token {
        return $this->tokens[$this->index];
    }

    public function advance() {
        $this->index++;
        $this->skipWhitespaces();
    }

    public function stepBack() {
        $this->index++;
        $this->skipWhitespacesBackwards();
    }

    public function hasNext(): bool {
        $index = $this->index;
        $this->advance();
        $result = $this->index < count($this->tokens);
        $this->index = $index;
        return $result;
    }

    private function skipWhitespaces() {
        while ($this->index < count($this->tokens) && $this->current()->getType() == TokenTypes::WHITESPACE) {
            $this->index++;
        }
    }

    private function skipWhitespacesBackwards() {
        while ($this->index > 0 && $this->current()->getType() == TokenTypes::WHITESPACE) {
            $this->index--;
        }
    }

    public function mark(): int {
        return $this->index;
    }

    public function rollbackTo(int $mark) {
        $this->index = $mark;
    }
}