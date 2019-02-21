<?hh //strict
namespace GraphQL\Language;

use GraphQL\Language\AST\ArgumentNode;
use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\AST\EnumTypeDefinitionNode;
use GraphQL\Language\AST\EnumValueDefinitionNode;
use GraphQL\Language\AST\FieldDefinitionNode;
use GraphQL\Language\AST\InputObjectTypeDefinitionNode;
use GraphQL\Language\AST\InputValueDefinitionNode;
use GraphQL\Language\AST\InterfaceTypeDefinitionNode;
use GraphQL\Language\AST\ListValueNode;
use GraphQL\Language\AST\BooleanValueNode;
use GraphQL\Language\AST\DirectiveNode;
use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\FloatValueNode;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\FragmentSpreadNode;
use GraphQL\Language\AST\InlineFragmentNode;
use GraphQL\Language\AST\IntValueNode;
use GraphQL\Language\AST\ListTypeNode;
use GraphQL\Language\AST\Location;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\NamedTypeNode;
use GraphQL\Language\AST\NodeList;
use GraphQL\Language\AST\NonNullTypeNode;
use GraphQL\Language\AST\NullValueNode;
use GraphQL\Language\AST\ObjectFieldNode;
use GraphQL\Language\AST\ObjectTypeDefinitionNode;
use GraphQL\Language\AST\ObjectValueNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\OperationTypeDefinitionNode;
use GraphQL\Language\AST\ScalarTypeDefinitionNode;
use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\TypeExtensionDefinitionNode;
use GraphQL\Language\AST\TypeSystemDefinitionNode;
use GraphQL\Language\AST\UnionTypeDefinitionNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\AST\Node;
use GraphQL\Error\SyntaxError;

/**
 * Parses string containing GraphQL query or [type definition](type-system/type-language.md) to Abstract Syntax Tree.
 */
class Parser
{
    /**
     * Given a GraphQL source, parses it into a `GraphQL\Language\AST\DocumentNode`.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * Available options:
     *
     * noLocation: boolean,
     * (By default, the parser creates AST nodes that know the location
     * in the source that they correspond to. This configuration flag
     * disables that behavior for performance or testing.)
     *
     * @api
     * @param Source|string $source
     * @param array $options
     * @return DocumentNode
     */
    public static function parse(Source $source, array<string,mixed> $options = []):DocumentNode
    {
        $parser = new Parser($source, $options);
        return $parser->parseDocument();
    }

    /**
     * Given a string containing a GraphQL value (ex. `[42]`), parse the AST for
     * that value.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Values directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: `GraphQL\Utils\AST::valueFromAST()`.
     *
     * @api
     * @param Source|string $source
     * @param array $options
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
     */
    public static function parseValue(Source $source, array<string,mixed> $options = []):Node
    {
        $parser = new Parser($source, $options);
        $parser->expect(Token::SOF);
        $value = $parser->parseValueLiteral(false);
        $parser->expect(Token::EOF);
        return $value;
    }

    /**
     * Given a string containing a GraphQL Type (ex. `[Int!]`), parse the AST for
     * that type.
     * Throws `GraphQL\Error\SyntaxError` if a syntax error is encountered.
     *
     * This is useful within tools that operate upon GraphQL Types directly and
     * in isolation of complete GraphQL documents.
     *
     * Consider providing the results to the utility function: `GraphQL\Utils\AST::typeFromAST()`.
     *
     * @api
     * @param Source|string $source
     * @param array $options
     * @return ListTypeNode|NameNode|NonNullTypeNode
     */
    public static function parseType(Source $source, array<string,mixed> $options = []):Node
    {
        $parser = new Parser($source, $options);
        $parser->expect(Token::SOF);
        $type = $parser->parseTypeReference();
        $parser->expect(Token::EOF);
        return $type;
    }

    /**
     * @var Lexer
     */
    private Lexer $lexer;

    /**
     * Parser constructor.
     * @param Source $source
     * @param array $options
     */
    public function __construct(Source $source, array<string,mixed> $options = [])
    {
        $this->lexer = new Lexer($source, $options);
    }

    /**
     * Returns a location object, used to identify the place in
     * the source that created a given parsed object.
     *
     * @param Token $startToken
     * @return Location|null
     */
    public function loc(Token $startToken):?Location
    {
        if ( idx($this->lexer->options, 'noLocation', false))
        {
            return new Location($startToken, $this->lexer->lastToken, $this->lexer->source);
        }
        return null;
    }

    /**
     * Determines if the next token is of a given kind
     *
     * @param $kind
     * @return bool
     */
    public function peek(string $kind):bool
    {
        return $this->lexer->token->kind === $kind;
    }

    /**
     * If the next token is of the given kind, return true after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     *
     * @param $kind
     * @return bool
     */
    public function skip(string $kind):bool
    {
        $match = $this->lexer->token->kind === $kind;

        if ($match) {
            $this->lexer->advance();
        }
        return $match;
    }

    /**
     * If the next token is of the given kind, return that token after advancing
     * the parser. Otherwise, do not change the parser state and return false.
     * @param string $kind
     * @return Token
     * @throws SyntaxError
     */
    public function expect(string $kind):Token
    {
        $token = $this->lexer->token;

        if ($token->kind === $kind) {
            $this->lexer->advance();
            return $token;
        }

        throw new SyntaxError(
            $this->lexer->source,
            $token->start,
            "Expected $kind, found " . $token->getDescription()
        );
    }

    /**
     * If the next token is a keyword with the given value, return that token after
     * advancing the parser. Otherwise, do not change the parser state and return
     * false.
     *
     * @param string $value
     * @return Token
     * @throws SyntaxError
     */
    public function expectKeyword(string $value):Token
    {
        $token = $this->lexer->token;

        if ($token->kind === Token::NAME && $token->value === $value) {
            $this->lexer->advance();
            return $token;
        }
        throw new SyntaxError(
            $this->lexer->source,
            $token->start,
            'Expected "' . $value . '", found ' . $token->getDescription()
        );
    }

    /**
     * @param Token|null $atToken
     * @return SyntaxError
     */
    public function unexpected(?Token $atToken = null):SyntaxError
    {
        $token = $atToken ?: $this->lexer->token;
        return new SyntaxError($this->lexer->source, $token->start, "Unexpected " . $token->getDescription());
    }

    /**
     * Returns a possibly empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @param int $openKind
     * @param callable $parseFn
     * @param int $closeKind
     * @return NodeList
     * @throws SyntaxError
     */
    public function any(string $openKind, (function():Node) $parseFn, string $closeKind):NodeList
    {
        $this->expect($openKind);

        $nodes = [];
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn();
        }
        return new NodeList($nodes);
    }

    /**
     * Returns a non-empty list of parse nodes, determined by
     * the parseFn. This list begins with a lex token of openKind
     * and ends with a lex token of closeKind. Advances the parser
     * to the next lex token after the closing token.
     *
     * @param $openKind
     * @param $parseFn
     * @param $closeKind
     * @return NodeList
     * @throws SyntaxError
     */
    public function many(string $openKind, (function():Node) $parseFn, string $closeKind):NodeList
    {
        $this->expect($openKind);

        $nodes = [$parseFn()];
        while (!$this->skip($closeKind)) {
            $nodes[] = $parseFn();
        }
        return new NodeList($nodes);
    }

    /**
     * Converts a name lex token into a name parse node.
     *
     * @return NameNode
     * @throws SyntaxError
     */
    public function parseName():NameNode
    {
        $token = $this->expect(Token::NAME);

        return new NameNode(
            $token->value,
            $this->loc($token)
        );
    }

    /**
     * Implements the parsing rules in the Document section.
     *
     * @return DocumentNode
     * @throws SyntaxError
     */
    public function parseDocument():DocumentNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::SOF);

        $definitions = [];
        do {
            $definitions[] = $this->parseDefinition();
        } while (!$this->skip(Token::EOF));

        return new DocumentNode(
            new NodeList($definitions),
            $this->loc($start)
        );
    }

    /**
     * @return OperationDefinitionNode|FragmentDefinitionNode|TypeSystemDefinitionNode
     * @throws SyntaxError
     */
    public function parseDefinition():Node
    {
        if ($this->peek(Token::BRACE_L)) {
            return $this->parseOperationDefinition();
        }

        $value = $this->lexer->token->value;
        if ($this->peek(Token::NAME) && $value !== null) {
            switch ($value) {
                case 'query':
                case 'mutation':
                case 'subscription':
                    return $this->parseOperationDefinition();

                case 'fragment':
                    return $this->parseFragmentDefinition();

                // Note: the Type System IDL is an experimental non-spec addition.
                case 'schema':
                case 'scalar':
                case 'type':
                case 'interface':
                case 'union':
                case 'enum':
                case 'input':
                case 'extend':
                case 'directive':
                    return $this->parseTypeSystemDefinition();
            }
        }

        throw $this->unexpected();
    }

    // Implements the parsing rules in the Operations section.

    /**
     * @return OperationDefinitionNode
     * @throws SyntaxError
     */
    public function parseOperationDefinition():OperationDefinitionNode
    {
        $start = $this->lexer->token;
        if ($this->peek(Token::BRACE_L)) {
            return new OperationDefinitionNode(
                null,
                'query',
                null,
                new NodeList([]),
                $this->parseSelectionSet(),
                $this->loc($start)
            );
        }

        $operation = $this->parseOperationType();

        $name = null;
        if ($this->peek(Token::NAME)) {
            $name = $this->parseName();
        }

        return new OperationDefinitionNode(
            $name,
            $operation,
            $this->parseVariableDefinitions(),
            $this->parseDirectives(),
            $this->parseSelectionSet(),
            $this->loc($start)
        );
    }

    /**
     * @return string
     * @throws SyntaxError
     */
    public function parseOperationType():string
    {
        $operationToken = $this->expect(Token::NAME);
        $value = $operationToken->value;
        if ($value !== null)
        {
            switch ($value)
            {
                case 'query': return 'query';
                case 'mutation': return 'mutation';
                // Note: subscription is an experimental non-spec addition.
                case 'subscription': return 'subscription';
            }
        }

        throw $this->unexpected($operationToken);
    }

    /**
     * @return VariableDefinitionNode[]|NodeList
     */
    public function parseVariableDefinitions():NodeList
    {
        return $this->peek(Token::PAREN_L) ?
            $this->many(
                Token::PAREN_L,
                inst_meth($this, 'parseVariableDefinition'),
                Token::PAREN_R
            ) :
            new NodeList([]);
    }

    /**
     * @return VariableDefinitionNode
     * @throws SyntaxError
     */
    public function parseVariableDefinition():VariableDefinitionNode
    {
        $start = $this->lexer->token;
        $var = $this->parseVariable();

        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();
        $defaultValue = ($this->skip(Token::EQUALS) ? $this->parseValueLiteral(true) : null);

        return new VariableDefinitionNode(
            $var,
            $type,
            $defaultValue,
            $this->loc($start)
        );
    }

    /**
     * @return VariableNode
     * @throws SyntaxError
     */
    public function parseVariable():VariableNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::DOLLAR);

        return new VariableNode(
            $this->parseName(),
            $this->loc($start)
        );
    }

    /**
     * @return SelectionSetNode
     */
    public function parseSelectionSet():SelectionSetNode
    {
        $start = $this->lexer->token;
        return new SelectionSetNode(
            $this->many(Token::BRACE_L, inst_meth($this, 'parseSelection'), Token::BRACE_R),
            $this->loc($start)
        );
    }

    /**
     *  Selection :
     *   - Field
     *   - FragmentSpread
     *   - InlineFragment
     *
     * @return mixed
     */
    public function parseSelection():Node
    {
        return $this->peek(Token::SPREAD) ?
            $this->parseFragment() :
            $this->parseField();
    }

    /**
     * @return FieldNode
     */
    public function parseField():FieldNode
    {
        $start = $this->lexer->token;
        $nameOrAlias = $this->parseName();

        if ($this->skip(Token::COLON)) {
            $alias = $nameOrAlias;
            $name = $this->parseName();
        } else {
            $alias = null;
            $name = $nameOrAlias;
        }

        return new FieldNode(
            $name,
            $alias,
            $this->parseArguments(),
            $this->parseDirectives(),
            $this->peek(Token::BRACE_L) ? $this->parseSelectionSet() : null,
            $this->loc($start)
        );
    }

    /**
     * @return ArgumentNode[]|NodeList
     */
    public function parseArguments():NodeList
    {
        return $this->peek(Token::PAREN_L) ?
            $this->many(Token::PAREN_L, inst_meth($this, 'parseArgument'), Token::PAREN_R) :
            new NodeList([]);
    }

    /**
     * @return ArgumentNode
     * @throws SyntaxError
     */
    public function parseArgument():ArgumentNode
    {
        $start = $this->lexer->token;
        $name = $this->parseName();

        $this->expect(Token::COLON);
        $value = $this->parseValueLiteral(false);

        return new ArgumentNode($name, $value, $this->loc($start));
    }

    // Implements the parsing rules in the Fragments section.

    /**
     * @return FragmentSpreadNode|InlineFragmentNode
     * @throws SyntaxError
     */
    public function parseFragment():Node
    {
        $start = $this->lexer->token;
        $this->expect(Token::SPREAD);

        if ($this->peek(Token::NAME) && $this->lexer->token->value !== 'on') {
            return new FragmentSpreadNode(
                $this->parseFragmentName(),
                $this->parseDirectives(),
                $this->loc($start)
            );
        }

        $typeCondition = null;
        if ($this->lexer->token->value === 'on') {
            $this->lexer->advance();
            $typeCondition = $this->parseNamedType();
        }

        return new InlineFragmentNode(
            $typeCondition,
            $this->parseDirectives(),
            $this->parseSelectionSet(),
            $this->loc($start)
        );
    }

    /**
     * @return FragmentDefinitionNode
     * @throws SyntaxError
     */
    public function parseFragmentDefinition():FragmentDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('fragment');

        $name = $this->parseFragmentName();
        $this->expectKeyword('on');
        $typeCondition = $this->parseNamedType();

        return new FragmentDefinitionNode(
            $name,
            $typeCondition,
            $this->parseDirectives(),
            $this->parseSelectionSet(),
            $this->loc($start)
        );
    }

    /**
     * @return NameNode
     * @throws SyntaxError
     */
    public function parseFragmentName():NameNode
    {
        if ($this->lexer->token->value === 'on') {
            throw $this->unexpected();
        }
        return $this->parseName();
    }

    // Implements the parsing rules in the Values section.

    /**
     * Value[Const] :
     *   - [~Const] Variable
     *   - IntValue
     *   - FloatValue
     *   - StringValue
     *   - BooleanValue
     *   - NullValue
     *   - EnumValue
     *   - ListValue[?Const]
     *   - ObjectValue[?Const]
     *
     * BooleanValue : one of `true` `false`
     *
     * NullValue : `null`
     *
     * EnumValue : Name but not `true`, `false` or `null`
     *
     * @param $isConst
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode|VariableNode|ListValueNode|ObjectValueNode|NullValueNode
     * @throws SyntaxError
     */
    public function parseValueLiteral(bool $isConst):Node
    {
        $token = $this->lexer->token;
        switch ($token->kind) {
            case Token::BRACKET_L:
                return $this->parseArray($isConst);
            case Token::BRACE_L:
                return $this->parseObject($isConst);
            case Token::INT:
                $this->lexer->advance();
                return new IntValueNode(
                    $token->value,
                    $this->loc($token)
                );
            case Token::FLOAT:
                $this->lexer->advance();
                return new FloatValueNode(
                    $token->value,
                    $this->loc($token)
                );
            case Token::STRING:
                $this->lexer->advance();
                return new StringValueNode($token->value, $this->loc($token));
            case Token::NAME:
                if ($token->value === 'true' || $token->value === 'false') {
                    $this->lexer->advance();
                    return new BooleanValueNode($token->value === 'true', $this->loc($token));
                } else if ($token->value === 'null') {
                    $this->lexer->advance();
                    return new NullValueNode($this->loc($token));
                } else {
                    $this->lexer->advance();
                    return new EnumValueNode(
                        $token->value,
                        $this->loc($token)
                    );
                }
                break;

            case Token::DOLLAR:
                if (!$isConst) {
                    return $this->parseVariable();
                }
                break;
        }
        throw $this->unexpected();
    }

    /**
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|StringValueNode|VariableNode
     * @throws SyntaxError
     */
    public function parseConstValue():Node
    {
        return $this->parseValueLiteral(true);
    }

    /**
     * @return BooleanValueNode|EnumValueNode|FloatValueNode|IntValueNode|ListValueNode|ObjectValueNode|StringValueNode|VariableNode
     */
    public function parseVariableValue():Node
    {
        return $this->parseValueLiteral(false);
    }

    /**
     * @param bool $isConst
     * @return ListValueNode
     */
    public function parseArray(bool $isConst):ListValueNode
    {
        $start = $this->lexer->token;
        $item = $isConst ? 'parseConstValue' : 'parseVariableValue';
        return new ListValueNode(
            /* HH_FIXME[2025] */
            $this->any(Token::BRACKET_L, inst_meth($this, $item), Token::BRACKET_R),
            $this->loc($start)
        );
    }

    /**
     * @param $isConst
     * @return ObjectValueNode
     */
    public function parseObject(bool $isConst):ObjectValueNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::BRACE_L);
        $fields = [];
        while (!$this->skip(Token::BRACE_R)) {
            $fields[] = $this->parseObjectField($isConst);
        }
        return new ObjectValueNode(
            new NodeList($fields),
            $this->loc($start)
        );
    }

    /**
     * @param $isConst
     * @return ObjectFieldNode
     */
    public function parseObjectField(bool $isConst):ObjectFieldNode
    {
        $start = $this->lexer->token;
        $name = $this->parseName();

        $this->expect(Token::COLON);

        return new ObjectFieldNode(
            $name,
            $this->parseValueLiteral($isConst),
            $this->loc($start)
        );
    }

    // Implements the parsing rules in the Directives section.

    /**
     * @return DirectiveNode[]|NodeList
     */
    public function parseDirectives():NodeList
    {
        $directives = [];
        while ($this->peek(Token::AT)) {
            $directives[] = $this->parseDirective();
        }
        return new NodeList($directives);
    }

    /**
     * @return DirectiveNode
     * @throws SyntaxError
     */
    public function parseDirective():DirectiveNode
    {
        $start = $this->lexer->token;
        $this->expect(Token::AT);
        return new DirectiveNode(
            $this->parseName(),
            $this->parseArguments(),
            $this->loc($start)
        );
    }

    // Implements the parsing rules in the Types section.

    /**
     * Handles the Type: TypeName, ListType, and NonNullType parsing rules.
     *
     * @return ListTypeNode|NameNode|NonNullTypeNode
     * @throws SyntaxError
     */
    public function parseTypeReference():Node
    {
        $start = $this->lexer->token;

        if ($this->skip(Token::BRACKET_L)) {
            $type = $this->parseTypeReference();
            $this->expect(Token::BRACKET_R);
            $type = new ListTypeNode(
                $type,
                $this->loc($start)
            );
        } else {
            $type = $this->parseNamedType();
        }
        if ($this->skip(Token::BANG)) {
            return new NonNullTypeNode(
                $type,
                $this->loc($start)
            );

        }
        return $type;
    }

    public function parseNamedType():NamedTypeNode
    {
        $start = $this->lexer->token;

        return new NamedTypeNode(
            $this->parseName(),
            $this->loc($start)
        );
    }

    // Implements the parsing rules in the Type Definition section.

    /**
     * TypeSystemDefinition :
     *   - SchemaDefinition
     *   - TypeDefinition
     *   - TypeExtensionDefinition
     *   - DirectiveDefinition
     *
     * TypeDefinition :
     *   - ScalarTypeDefinition
     *   - ObjectTypeDefinition
     *   - InterfaceTypeDefinition
     *   - UnionTypeDefinition
     *   - EnumTypeDefinition
     *   - InputObjectTypeDefinition
     *
     * @return TypeSystemDefinitionNode
     * @throws SyntaxError
     */
    public function parseTypeSystemDefinition():Node
    {
        $value = $this->lexer->token->value;
        if ($this->peek(Token::NAME) && $value !== null) {
            switch ($value) {
                case 'schema': return $this->parseSchemaDefinition();
                case 'scalar': return $this->parseScalarTypeDefinition();
                case 'type': return $this->parseObjectTypeDefinition();
                case 'interface': return $this->parseInterfaceTypeDefinition();
                case 'union': return $this->parseUnionTypeDefinition();
                case 'enum': return $this->parseEnumTypeDefinition();
                case 'input': return $this->parseInputObjectTypeDefinition();
                case 'extend': return $this->parseTypeExtensionDefinition();
                case 'directive': return $this->parseDirectiveDefinition();
            }
        }

        throw $this->unexpected();
    }

    /**
     * @return SchemaDefinitionNode
     * @throws SyntaxError
     */
    public function parseSchemaDefinition():SchemaDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('schema');
        $directives = $this->parseDirectives();

        $operationTypes = $this->many(
            Token::BRACE_L,
            inst_meth($this, 'parseOperationTypeDefinition'),
            Token::BRACE_R
        );

        return new SchemaDefinitionNode(
            $directives,
            $operationTypes,
            $this->loc($start)
        );
    }

    /**
     * @return OperationTypeDefinitionNode
     */
    public function parseOperationTypeDefinition():OperationTypeDefinitionNode
    {
        $start = $this->lexer->token;
        $operation = $this->parseOperationType();
        $this->expect(Token::COLON);
        $type = $this->parseNamedType();

        return new OperationTypeDefinitionNode(
            $operation,
            $type,
            $this->loc($start)
        );
    }

    /**
     * @return ScalarTypeDefinitionNode
     * @throws SyntaxError
     */
    public function parseScalarTypeDefinition():ScalarTypeDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('scalar');
        $name = $this->parseName();
        $directives = $this->parseDirectives();

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new ScalarTypeDefinitionNode(
            $name,
            $directives,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return ObjectTypeDefinitionNode
     * @throws SyntaxError
     */
    public function parseObjectTypeDefinition():ObjectTypeDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('type');
        $name = $this->parseName();
        $interfaces = $this->parseImplementsInterfaces();
        $directives = $this->parseDirectives();

        $fields = $this->any(
            Token::BRACE_L,
            inst_meth($this, 'parseFieldDefinition'),
            Token::BRACE_R
        );

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new ObjectTypeDefinitionNode(
            $name,
            $interfaces,
            $directives,
            $fields,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return NamedTypeNode[]
     */
    public function parseImplementsInterfaces():array<NamedTypeNode>
    {
        $types = [];
        if ($this->lexer->token->value === 'implements') {
            $this->lexer->advance();
            do {
                $types[] = $this->parseNamedType();
            } while ($this->peek(Token::NAME));
        }
        return $types;
    }

    /**
     * @return FieldDefinitionNode
     * @throws SyntaxError
     */
    public function parseFieldDefinition():FieldDefinitionNode
    {
        $start = $this->lexer->token;
        $name = $this->parseName();
        $args = $this->parseArgumentDefs();
        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();
        $directives = $this->parseDirectives();

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new FieldDefinitionNode(
            $name,
            $args,
            $type,
            $directives,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return InputValueDefinitionNode[]|NodeList
     */
    public function parseArgumentDefs():NodeList
    {
        if (!$this->peek(Token::PAREN_L)) {
            return new NodeList([]);
        }
        return $this->many(Token::PAREN_L, inst_meth($this, 'parseInputValueDef'), Token::PAREN_R);
    }

    /**
     * @return InputValueDefinitionNode
     * @throws SyntaxError
     */
    public function parseInputValueDef():InputValueDefinitionNode
    {
        $start = $this->lexer->token;
        $name = $this->parseName();
        $this->expect(Token::COLON);
        $type = $this->parseTypeReference();
        $defaultValue = null;
        if ($this->skip(Token::EQUALS)) {
            $defaultValue = $this->parseConstValue();
        }
        $directives = $this->parseDirectives();
        $description = $this->getDescriptionFromAdjacentCommentTokens($start);
        return new InputValueDefinitionNode(
            $name,
            $type,
            $defaultValue,
            $directives,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return InterfaceTypeDefinitionNode
     * @throws SyntaxError
     */
    public function parseInterfaceTypeDefinition():InterfaceTypeDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('interface');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $fields = $this->any(
            Token::BRACE_L,
            inst_meth($this, 'parseFieldDefinition'),
            Token::BRACE_R
        );

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new InterfaceTypeDefinitionNode(
            $name,
            $directives,
            $fields,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return UnionTypeDefinitionNode
     * @throws SyntaxError
     */
    public function parseUnionTypeDefinition():UnionTypeDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('union');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $this->expect(Token::EQUALS);
        $types = $this->parseUnionMembers();

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new UnionTypeDefinitionNode(
            $name,
            $directives,
            $types,
            $description,
            $this->loc($start),
        );
    }

    /**
     * UnionMembers :
     *   - `|`? NamedType
     *   - UnionMembers | NamedType
     *
     * @return NamedTypeNode[]
     */
    public function parseUnionMembers():array<NamedTypeNode>
    {
        // Optional leading pipe
        $this->skip(Token::PIPE);
        $members = [];

        do {
            $members[] = $this->parseNamedType();
        } while ($this->skip(Token::PIPE));
        return $members;
    }

    /**
     * @return EnumTypeDefinitionNode
     * @throws SyntaxError
     */
    public function parseEnumTypeDefinition():EnumTypeDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('enum');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $values = $this->many(
            Token::BRACE_L,
            inst_meth($this, 'parseEnumValueDefinition'),
            Token::BRACE_R
        );

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new EnumTypeDefinitionNode(
            $name,
            $directives,
            $values,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return EnumValueDefinitionNode
     */
    public function parseEnumValueDefinition():EnumValueDefinitionNode
    {
        $start = $this->lexer->token;
        $name = $this->parseName();
        $directives = $this->parseDirectives();

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new EnumValueDefinitionNode(
            $name,
            $directives,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return InputObjectTypeDefinitionNode
     * @throws SyntaxError
     */
    public function parseInputObjectTypeDefinition():InputObjectTypeDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('input');
        $name = $this->parseName();
        $directives = $this->parseDirectives();
        $fields = $this->any(
            Token::BRACE_L,
            inst_meth($this, 'parseInputValueDef'),
            Token::BRACE_R
        );

        $description = $this->getDescriptionFromAdjacentCommentTokens($start);

        return new InputObjectTypeDefinitionNode(
            $name,
            $directives,
            $fields,
            $description,
            $this->loc($start),
        );
    }

    /**
     * @return TypeExtensionDefinitionNode
     * @throws SyntaxError
     */
    public function parseTypeExtensionDefinition():TypeExtensionDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('extend');
        $definition = $this->parseObjectTypeDefinition();

        return new TypeExtensionDefinitionNode(
            $definition,
            $this->loc($start)
        );
    }

    /**
     * DirectiveDefinition :
     *   - directive @ Name ArgumentsDefinition? on DirectiveLocations
     *
     * @return DirectiveDefinitionNode
     * @throws SyntaxError
     */
    public function parseDirectiveDefinition():DirectiveDefinitionNode
    {
        $start = $this->lexer->token;
        $this->expectKeyword('directive');
        $this->expect(Token::AT);
        $name = $this->parseName();
        $args = $this->parseArgumentDefs();
        $this->expectKeyword('on');
        $locations = $this->parseDirectiveLocations();

        return new DirectiveDefinitionNode(
            $name,
            $args,
            $locations,
            $this->loc($start)
        );
    }

    /**
     * @return NameNode[]
     */
    public function parseDirectiveLocations():array<NameNode>
    {
        // Optional leading pipe
        $this->skip(Token::PIPE);
        $locations = [];
        do {
            $locations[] = $this->parseName();
        } while ($this->skip(Token::PIPE));
        return $locations;
    }

    /**
     * @param Token $nameToken
     * @return null|string
     */
    private function getDescriptionFromAdjacentCommentTokens(Token $nameToken):?string
    {
        $description = null;

        $currentToken = $nameToken;
        $previousToken = $currentToken->prev;

        while ($previousToken !== null
            && $previousToken->kind == Token::COMMENT
            && ($previousToken->line + 1) == $currentToken->line
        ) {
            $description = $previousToken->value . $description;

            // walk the tokens backwards until no longer adjacent comments
            $currentToken = $previousToken;
            $previousToken = $currentToken->prev;
        }

        return $description;
    }
}
