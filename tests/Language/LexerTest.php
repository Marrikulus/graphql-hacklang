<?hh //strict
//decl
namespace GraphQL\Tests\Language;

use GraphQL\Language\Lexer;
use function Facebook\FBExpect\expect;
use GraphQL\Language\Source;
use GraphQL\Language\SourceLocation;
use GraphQL\Language\Token;
use GraphQL\Error\SyntaxError;
use GraphQL\Utils\Utils;

class LexerTest extends \Facebook\HackTest\HackTest
{
    /**
     * @it disallows uncommon control characters
     */
    public function testDissallowsUncommonControlCharacters():void
    {
        $char = Utils::chr(0x0007);

        try
        {
            $this->lexOne($char);
        }
        catch(SyntaxError $e)
        {
            expect($e->getMessage())->toMatchRegExp('/' . \preg_quote('Syntax Error GraphQL (1:1) Cannot contain the invalid character "\u0007"', '/') . '/');
            return;
        }

        self::fail("Should have thrown an exception");
    }

    /**
     * @it accepts BOM header
     */
    public function testAcceptsBomHeader():void
    {
        $bom = Utils::chr(0xFEFF);
        $expected = [
            'kind' => Token::NAME,
            'start' => 2,
            'end' => 5,
            'value' => 'foo'
        ];

        expect((array)$this->lexOne($bom . ' foo'))->toInclude($expected);
    }

    /**
     * @it records line and column
     */
    public function testRecordsLineAndColumn():void
    {
        $expected = [
            'kind' => Token::NAME,
            'start' => 8,
            'end' => 11,
            'line' => 4,
            'column' => 3,
            'value' => 'foo'
        ];
        $actual = (array)$this->lexOne("\n \r\n \r  foo\n");
        unset($actual['prev']);
        unset($actual['next']);
        expect($actual)->toBeSame($expected);
    }

    /**
     * @it skips whitespace and comments
     */
    public function testSkipsWhitespacesAndComments():void
    {
        $example1 = '

    foo


';
        $expected = [
            'kind' => Token::NAME,
            'start' => 6,
            'end' => 9,
            'value' => 'foo'
        ];
        expect((array)$this->lexOne($example1))->toInclude($expected);

        $example2 = '
    #comment
    foo#comment
';

        $expected = [
            'kind' => Token::NAME,
            'start' => 18,
            'end' => 21,
            'value' => 'foo'
        ];
        expect((array)$this->lexOne($example2))->toInclude($expected);

        $expected = [
            'kind' => Token::NAME,
            'start' => 3,
            'end' => 6,
            'value' => 'foo'
        ];

        $example3 = ',,,foo,,,';
        expect((array)$this->lexOne($example3))->toInclude($expected);
    }

    /**
     * @it errors respect whitespace
     */
    public function testErrorsRespectWhitespace():void
    {
        $str = '' .
            "\n" .
            "\n" .
            "    ?\n" .
            "\n";

        $this->setExpectedException(SyntaxError::class,
            'Syntax Error GraphQL (3:5) Cannot parse the unexpected character "?".' . "\n" .
            "\n" .
            "2: \n" .
            "3:     ?\n" .
            "       ^\n" .
            "4: \n");
        $this->lexOne($str);
    }

    /**
     * @it updates line numbers in error for file context
     */
    public function testUpdatesLineNumbersInErrorForFileContext():void
    {
        $str = '' .
            "\n" .
            "\n" .
            "     ?\n" .
            "\n";
        $source = new Source($str, 'foo.js', new SourceLocation(11, 12));

        $this->setExpectedException(
            SyntaxError::class,
            'Syntax Error foo.js (13:6) ' .
            'Cannot parse the unexpected character "?".' . "\n" .
            "\n" .
            '12: ' . "\n" .
            '13:      ?' . "\n" .
            '         ^' . "\n" .
            '14: ' . "\n"
        );
        $lexer = new Lexer($source);
        $lexer->advance();
    }

    public function testUpdatesColumnNumbersInErrorForFileContext():void
    {
        $source = new Source('?', 'foo.js', new SourceLocation(1, 5));

        $this->setExpectedException(
            SyntaxError::class,
            'Syntax Error foo.js (1:5) ' .
            'Cannot parse the unexpected character "?".' . "\n" .
            "\n" .
            '1:     ?' . "\n" .
            '       ^' . "\n"
        );
        $lexer = new Lexer($source);
        $lexer->advance();
    }

    /**
     * @it lexes strings
     */
    public function testLexesStrings():void
    {
        expect((array) $this->lexOne('"simple"'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 8,
            'value' => 'simple'
        ]);


        expect((array) $this->lexOne('" white space "'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 15,
            'value' => ' white space '
        ]);

        expect((array) $this->lexOne('"quote \\""'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 10,
            'value' => 'quote "'
        ]);

        expect((array) $this->lexOne('"escaped \\\\n\\\\r\\\\b\\\\t\\\\f"'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 25,
            'value' => 'escaped \n\r\b\t\f'
        ]);

        expect((array) $this->lexOne('"slashes \\\\ \\\\/"'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 16,
            'value' => 'slashes \\ \/'
        ]);

        expect((array) $this->lexOne('"unicode яуц"'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 13,
            'value' => 'unicode яуц'
        ]);

        $unicode = \json_decode('"\u1234\u5678\u90AB\uCDEF"');
        expect((array) $this->lexOne('"unicode \u1234\u5678\u90AB\uCDEF"'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 34,
            'value' => 'unicode ' . $unicode
        ]);

        expect((array) $this->lexOne('"\u1234\u5678\u90AB\uCDEF"'))
        ->toInclude([
            'kind' => Token::STRING,
            'start' => 0,
            'end' => 26,
            'value' => $unicode
        ]);
    }

    public function reportsUsefulErrors():array<array<string>>
    {
        return [
            ['"', "Syntax Error GraphQL (1:2) Unterminated string.\n\n1: \"\n    ^\n"],
            ['"no end quote', "Syntax Error GraphQL (1:14) Unterminated string.\n\n1: \"no end quote\n                ^\n"],
            ["'single quotes'", "Syntax Error GraphQL (1:1) Unexpected single quote character ('), did you mean to use a double quote (\")?\n\n1: 'single quotes'\n   ^\n"],
            ['"contains unescaped \u0007 control char"', "Syntax Error GraphQL (1:21) Invalid character within String: \"\\u0007\"\n\n1: \"contains unescaped \\u0007 control char\"\n                       ^\n"],
            ['"null-byte is not \u0000 end of file"', 'Syntax Error GraphQL (1:19) Invalid character within String: "\\u0000"' . "\n\n1: \"null-byte is not \\u0000 end of file\"\n                     ^\n"],
            ['"multi' . "\n" . 'line"', "Syntax Error GraphQL (1:7) Unterminated string.\n\n1: \"multi\n         ^\n2: line\"\n"],
            ['"multi' . "\r" . 'line"', "Syntax Error GraphQL (1:7) Unterminated string.\n\n1: \"multi\n         ^\n2: line\"\n"],
            ['"bad \\z esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\z\n\n1: \"bad \\z esc\"\n         ^\n"],
            ['"bad \\x esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\x\n\n1: \"bad \\x esc\"\n         ^\n"],
            ['"bad \\u1 esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\u1 es\n\n1: \"bad \\u1 esc\"\n         ^\n"],
            ['"bad \\u0XX1 esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\u0XX1\n\n1: \"bad \\u0XX1 esc\"\n         ^\n"],
            ['"bad \\uXXXX esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uXXXX\n\n1: \"bad \\uXXXX esc\"\n         ^\n"],
            ['"bad \\uFXXX esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uFXXX\n\n1: \"bad \\uFXXX esc\"\n         ^\n"],
            ['"bad \\uXXXF esc"', "Syntax Error GraphQL (1:7) Invalid character escape sequence: \\uXXXF\n\n1: \"bad \\uXXXF esc\"\n         ^\n"],
        ];
    }

    /**
     * @it lex reports useful string errors
     */
    <<DataProvider('reportsUsefulErrors')>>
    public function testReportsUsefulErrors($str, $expectedMessage)
    {
        $this->setExpectedException(SyntaxError::class, $expectedMessage);
        $this->lexOne($str);
    }

    /**
     * @it lexes numbers
     */
    public function testLexesNumbers():void
    {
        expect((array) $this->lexOne('4'))
            ->toInclude(['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '4']);

        expect((array) $this->lexOne('4.123'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '4.123']);

        expect((array) $this->lexOne('-4'))
            ->toInclude(['kind' => Token::INT, 'start' => 0, 'end' => 2, 'value' => '-4']);

        expect((array) $this->lexOne('9'))
            ->toInclude(['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '9']);

        expect((array) $this->lexOne('0'))
            ->toInclude(['kind' => Token::INT, 'start' => 0, 'end' => 1, 'value' => '0']);

        expect((array) $this->lexOne('-4.123'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '-4.123']);

        expect((array) $this->lexOne('0.123'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '0.123']);

        expect((array) $this->lexOne('123e4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123e4']);

        expect((array) $this->lexOne('123E4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 5, 'value' => '123E4']);

        expect((array) $this->lexOne('123e-4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e-4']);

        expect((array) $this->lexOne('123e+4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 6, 'value' => '123e+4']);

        expect((array) $this->lexOne('-1.123e4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123e4']);

        expect((array) $this->lexOne('-1.123E4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 8, 'value' => '-1.123E4']);

        expect((array) $this->lexOne('-1.123e-4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e-4']);

        expect((array) $this->lexOne('-1.123e+4'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 9, 'value' => '-1.123e+4']);

        expect((array) $this->lexOne('-1.123e4567'))
            ->toInclude(['kind' => Token::FLOAT, 'start' => 0, 'end' => 11, 'value' => '-1.123e4567']);

    }

    public function reportsUsefulNumberErrors()
    {
        return [
            [ '00', "Syntax Error GraphQL (1:2) Invalid number, unexpected digit after 0: \"0\"\n\n1: 00\n    ^\n"],
            [ '+1', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"+\".\n\n1: +1\n   ^\n"],
            [ '1.', "Syntax Error GraphQL (1:3) Invalid number, expected digit but got: <EOF>\n\n1: 1.\n     ^\n"],
            [ '.123', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \".\".\n\n1: .123\n   ^\n"],
            [ '1.A', "Syntax Error GraphQL (1:3) Invalid number, expected digit but got: \"A\"\n\n1: 1.A\n     ^\n"],
            [ '-A', "Syntax Error GraphQL (1:2) Invalid number, expected digit but got: \"A\"\n\n1: -A\n    ^\n"],
            [ '1.0e', "Syntax Error GraphQL (1:5) Invalid number, expected digit but got: <EOF>\n\n1: 1.0e\n       ^\n"],
            [ '1.0eA', "Syntax Error GraphQL (1:5) Invalid number, expected digit but got: \"A\"\n\n1: 1.0eA\n       ^\n"],
        ];
    }

    /**
     * @it lex reports useful number errors
     */
    <<DataProvider('reportsUsefulNumberErrors')>>
    public function testReportsUsefulNumberErrors($str, $expectedMessage)
    {
        $this->setExpectedException(SyntaxError::class, $expectedMessage);
        $this->lexOne($str);
    }

    /**
     * @it lexes punctuation
     */
    public function testLexesPunctuation():void
    {
        expect((array) $this->lexOne('!'))
        ->toInclude(['kind' => Token::BANG, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('$'))
        ->toInclude(['kind' => Token::DOLLAR, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('('))
        ->toInclude(['kind' => Token::PAREN_L, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne(')'))
        ->toInclude(['kind' => Token::PAREN_R, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('...'))
        ->toInclude(['kind' => Token::SPREAD, 'start' => 0, 'end' => 3, 'value' => null]);

        expect((array) $this->lexOne(':'))
        ->toInclude(['kind' => Token::COLON, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('='))
        ->toInclude(['kind' => Token::EQUALS, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('@'))
        ->toInclude(['kind' => Token::AT, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('['))
        ->toInclude(['kind' => Token::BRACKET_L, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne(']'))
        ->toInclude(['kind' => Token::BRACKET_R, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('{'))
        ->toInclude(['kind' => Token::BRACE_L, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('|'))
        ->toInclude(['kind' => Token::PIPE, 'start' => 0, 'end' => 1, 'value' => null]);

        expect((array) $this->lexOne('}'))
        ->toInclude(['kind' => Token::BRACE_R, 'start' => 0, 'end' => 1, 'value' => null]);

    }

    public function reportsUsefulUnknownCharErrors()
    {
        $unicode1 = \json_decode('"\u203B"');
        $unicode2 = \json_decode('"\u200b"');

        return [
            ['..', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \".\".\n\n1: ..\n   ^\n"],
            ['?', "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"?\".\n\n1: ?\n   ^\n"],
            [$unicode1, "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"\\u203b\".\n\n1: $unicode1\n   ^\n"],
            [$unicode2, "Syntax Error GraphQL (1:1) Cannot parse the unexpected character \"\\u200b\".\n\n1: $unicode2\n   ^\n"],
        ];
    }

    /**
     * @it lex reports useful unknown character error
     */
    <<DataProvider('reportsUsefulUnknownCharErrors')>>
    public function testReportsUsefulUnknownCharErrors($str, $expectedMessage)
    {
        $this->setExpectedException(SyntaxError::class, $expectedMessage);
        $this->lexOne($str);
    }

    /**
     * @it lex reports useful information for dashes in names
     */
    public function testReportsUsefulDashesInfo():void
    {
        $q = 'a-b';
        $lexer = new Lexer(new Source($q));
        expect((array) $lexer->advance())
            ->toInclude(['kind' => Token::NAME, 'start' => 0, 'end' => 1, 'value' => 'a']);

        $this->setExpectedException(SyntaxError::class, 'Syntax Error GraphQL (1:3) Invalid number, expected digit but got: "b"' . "\n\n1: a-b\n     ^\n");
        $lexer->advance();
    }

    /**
     * @it produces double linked list of tokens, including comments
     */
    public function testDoubleLinkedList():void
    {
        $lexer = new Lexer(new Source('{
      #comment
      field
    }'));

        $startToken = $lexer->token;
        do {
            $endToken = $lexer->advance();
            // Lexer advances over ignored comment tokens to make writing parsers
            // easier, but will include them in the linked list result.
            expect($endToken->kind)->toNotBePHPEqual('Comment');
        } while ($endToken->kind !== '<EOF>');

        expect($startToken->prev)->toBePHPEqual(null);
        expect($endToken->next)->toBePHPEqual(null);

        $tokens = [];
        for ($tok = $startToken; $tok; $tok = $tok->next) {
            if (!empty($tokens)) {
                // Tokens are double-linked, prev should point to last seen token.
                expect($tok->prev)->toBeSame($tokens[\count($tokens) - 1]);
            }
            $tokens[] = $tok;
        }

        expect(Utils::map($tokens, function ($tok) {
            return $tok->kind;
        }))->toBePHPEqual([
            '<SOF>',
            '{',
            'Comment',
            'Name',
            '}',
            '<EOF>'
        ]);
    }

    /**
     * @param string $body
     * @return Token
     */
    private function lexOne(string $body):Token
    {
        $lexer = new Lexer(new Source($body));
        return $lexer->advance();
    }
}
