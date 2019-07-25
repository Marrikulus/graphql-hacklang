<?hh //partial
namespace GraphQL\Executor;

use GraphQL\Error\Error;
use GraphQL\Language\AST\FragmentDefinitionNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Type\Schema;
use GraphQL\Executor\Promise\PromiseAdapter;

/**
 * Data that must be available at all points during query execution.
 *
 * Namely, schema of the type system that is currently executing,
 * and the fragments defined in the query document
 *
 * @internal
 */
class ExecutionContext
{
    /**
     * @var Schema
     */
    public Schema $schema;

    /**
     * @var FragmentDefinitionNode[]
     */
    public array<string, FragmentDefinitionNode> $fragments;

    /**
     * @var mixed
     */
    public mixed $rootValue;

    /**
     * @var mixed
     */
    public mixed $contextValue;

    /**
     * @var OperationDefinitionNode
     */
    public OperationDefinitionNode $operation;

    /**
     * @var array
     */
    public array $variableValues;

    /**
     * @var callable
     */
    public $fieldResolver;

    /**
     * @var array
     */
    public array<Error> $errors;

    public PromiseAdapter $promises;

    public function __construct(
        Schema $schema,
        array<string, FragmentDefinitionNode> $fragments,
        mixed $root,
        mixed $contextValue,
        OperationDefinitionNode $operation,
        $variables,
        ?array<Error> $errors,
        $fieldResolver,
        PromiseAdapter $promiseAdapter
    )
    {
        $this->schema = $schema;
        $this->fragments = $fragments;
        $this->rootValue = $root;
        $this->contextValue = $contextValue;
        $this->operation = $operation;
        $this->variableValues = $variables;
        $this->errors = $errors ?? [];
        $this->fieldResolver = $fieldResolver;
        $this->promises = $promiseAdapter;
    }

    public function addError(Error $error):ExecutionContext
    {
        $this->errors[] = $error;
        return $this;
    }
}
