<?hh //strict
namespace GraphQL\Type;

use GraphQL\Language\AST\SchemaDefinitionNode;
use GraphQL\Type\Definition\Directive;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\GraphQlType;
use GraphQL\Utils\Utils;

/**
 * Schema configuration class.
 * Could be passed directly to schema constructor. List of options accepted by **create** method is
 * [described in docs](type-system/schema.md#configuration-options).
 *
 * Usage example:
 *
 *     $config = SchemaConfig::create()
 *         ->setQuery($myQueryType)
 *         ->setTypeLoader($myTypeLoader);
 *
 *     $schema = new Schema($config);
 *
 */
type TypeLoaderFn = (function(string):GraphQlType);

class SchemaConfig
{
    private function __construct(
        public ObjectType $query
    ){}

    /**
     * @var ObjectType
     */
    public ?ObjectType $mutation;

    /**
     * @var ObjectType
     */
    public ?ObjectType $subscription;

    /**
     * @var Type[]|callable
     */
    /* HH_FIXME[2001]*/
    public $types;

    /**
     * @var Directive[]
     */
    public ?array<Directive> $directives;

    /**
     * @var callable
     */
    public ?TypeLoaderFn $typeLoader;

    /**
     * @var SchemaDefinitionNode
     */
    public ?SchemaDefinitionNode $astNode;

    /**
     * Converts an array of options to instance of SchemaConfig
     * (or just returns empty config when array is not passed).
     *
     * @api
     * @param array $options
     * @return SchemaConfig
     */
    /* HH_FIXME[4045]*/
    public static function create(array $options = []):SchemaConfig
    {
        $query = null;
        if (\array_key_exists('query', $options))
        {
            Utils::invariant(
                $options['query'] instanceof ObjectType,
                'Schema query must be Object Type if provided but got: %s',
                Utils::printSafe($options['query'])
            );
            $query = $options['query'];
        }

        invariant(
            $query instanceof ObjectType,
            "Schema query must be Object Type but got: %s",
            Utils::getVariableType($query)
        );

        $config = new SchemaConfig($query);

        if (\array_key_exists('mutation', $options) && $options['mutation'] !== null)
        {
            Utils::invariant(
                $options['mutation'] instanceof ObjectType,
                'Schema mutation must be Object Type if provided but got: %s',
                Utils::printSafe($options['mutation'])
            );
            $config->setMutation($options['mutation']);
        }

        if (\array_key_exists('subscription', $options) && $options['subscription'] !== null)
                    {
            Utils::invariant(
                $options['subscription'] instanceof ObjectType,
                'Schema subscription must be Object Type if provided but got: %s',
                Utils::printSafe($options['subscription'])
            );
            $config->setSubscription($options['subscription']);
        }

        if (\array_key_exists('types', $options) && $options['types'] !== null)
        {
            Utils::invariant(
                is_array($options['types']) || \is_callable($options['types']),
                'Schema types must be array or callable if provided but got: %s',
                Utils::printSafe($options['types'])
            );
            $config->setTypes($options['types']);
        }

        if (\array_key_exists('directives', $options) && $options['directives'] !== null)
        {
            Utils::invariant(
                is_array($options['directives']),
                'Schema directives must be array if provided but got: %s',
                Utils::printSafe($options['directives'])
            );
            $config->setDirectives($options['directives']);
        }

        if (\array_key_exists('typeResolution', $options) && $options['typeResolution'] !== null)
        {
            \trigger_error(
                'Type resolution strategies are deprecated. Just pass single option `typeLoader` '.
                'to schema constructor instead.',
                \E_USER_DEPRECATED
            );
            if ($options['typeResolution'] instanceof Resolution && !\array_key_exists('typeLoader', $options))
            {
                $strategy = $options['typeResolution'];
                $options['typeLoader'] = function($name) use ($strategy) {
                    return $strategy->resolveType($name);
                };
            }
        }

        if (\array_key_exists('typeLoader', $options) && $options['typeLoader'] !== null)
        {
            Utils::invariant(
                \is_callable($options['typeLoader']),
                'Schema type loader must be callable if provided but got: %s',
                Utils::printSafe($options['typeLoader'])
            );
            $config->setTypeLoader($options['typeLoader']);
        }

        if (\array_key_exists('astNode', $options) && $options['astNode'] !== null)
        {
            Utils::invariant(
                $options['astNode'] instanceof SchemaDefinitionNode,
                'Schema astNode must be an instance of SchemaDefinitionNode but got: %s',
                Utils::printSafe($options['typeLoader'])
            );
            $config->setAstNode($options['astNode']);
        }

        return $config;
    }

    /**
     * @return SchemaDefinitionNode
     */
    public function getAstNode():?SchemaDefinitionNode
    {
        return $this->astNode;
    }

    /**
     * @param SchemaDefinitionNode $astNode
     * @return SchemaConfig
     */
    public function setAstNode(SchemaDefinitionNode $astNode):this
    {
        $this->astNode = $astNode;
        return $this;
    }

    /**
     * @api
     * @param ObjectType $query
     * @return SchemaConfig
     */
    public function setQuery(ObjectType $query):this
    {
        $this->query = $query;
        return $this;
    }

    /**
     * @api
     * @param ObjectType $mutation
     * @return SchemaConfig
     */
    public function setMutation(ObjectType $mutation):this
    {
        $this->mutation = $mutation;
        return $this;
    }

    /**
     * @api
     * @param ObjectType $subscription
     * @return SchemaConfig
     */
    public function setSubscription(ObjectType $subscription):this
    {
        $this->subscription = $subscription;
        return $this;
    }

    /**
     * @api
     * @param Type[]|callable $types
     * @return SchemaConfig
     */
    /* HH_FIXME[4032]*/
    public function setTypes($types):this
    {
        $this->types = $types;
        return $this;
    }

    /**
     * @api
     * @param Directive[] $directives
     * @return SchemaConfig
     */
    public function setDirectives(array<Directive> $directives):this
    {
        $this->directives = $directives;
        return $this;
    }

    /**
     * @api
     * @param callable $typeLoader
     * @return SchemaConfig
     */
    public function setTypeLoader(TypeLoaderFn $typeLoader):this
    {
        $this->typeLoader = $typeLoader;
        return $this;
    }

    /**
     * @api
     * @return ObjectType
     */
    public function getQuery():?ObjectType
    {
        return $this->query;
    }

    /**
     * @api
     * @return ObjectType
     */
    public function getMutation():?ObjectType
    {
        return $this->mutation;
    }

    /**
     * @api
     * @return ObjectType
     */
    public function getSubscription():?ObjectType
    {
        return $this->subscription;
    }

    /**
     * @api
     * @return GraphQlType[]
     */
    public function getTypes():array<GraphQlType>
    {
        return $this->types ?? [];
    }

    /**
     * @api
     * @return Directive[]
     */
    public function getDirectives():array<Directive>
    {
        return $this->directives ?? [];
    }

    /**
     * @api
     * @return callable
     */
    public function getTypeLoader():?TypeLoaderFn
    {
        return $this->typeLoader;
    }
}
