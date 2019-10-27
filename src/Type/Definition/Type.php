<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use Exception;
use GraphQL\Error\InvariantViolation;
use GraphQL\Language\AST\TypeDefinitionNode;
use GraphQL\Language\AST\TypeExtensionNode;
use GraphQL\Type\Introspection;
use GraphQL\Utils\Utils;
use JsonSerializable;
use phpDocumentor\Reflection\Types\Nullable;
use ReflectionClass;
use Throwable;
use function array_keys;
use function array_merge;
use function implode;
use function in_array;
use function preg_replace;
use function trigger_error;
use const E_USER_DEPRECATED;

/**
 * Registry of standard GraphQL types
 * and a base class for all other types.
 */
abstract class Type implements JsonSerializable
{
    public const STRING  = 'String';
    public const INT     = 'Int';
    public const BOOLEAN = 'Boolean';
    public const FLOAT   = 'Float';
    public const ID      = 'ID';

    /** @var array<string,ScalarType|CustomScalarType>> */
    private static $standardTypes;

    /** @var Type[] */
    private static $builtInTypes;

    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    /** @var TypeDefinitionNode|null */
    public $astNode;

    public $config;

    /** @var TypeExtensionNode[] */
    public $extensionASTNodes;

    /**
     * @api
     */
    public static function id(): ScalarType
    {
        if(is_null(static::$standardTypes[static::ID] ?? null)) {
            static::$standardTypes[static::ID] = new IDType();
        }
        return static::$standardTypes[static::ID];
    }

    /**
     * @api
     */
    public static function string(): ScalarType
    {
        if(is_null(static::$standardTypes[static::STRING] ?? null)) {
            static::$standardTypes[static::STRING] = new StringType();
        }
        return static::$standardTypes[static::STRING];
    }

    /**
     * @api
     */
    public static function boolean() : ScalarType
    {
        if(is_null(static::$standardTypes[static::BOOLEAN] ?? null)) {
            static::$standardTypes[static::BOOLEAN] = new BooleanType();
        }
        return static::$standardTypes[static::BOOLEAN];
    }

    /**
     * @api
     */
    public static function int() : ScalarType
    {
        if(is_null(static::$standardTypes[static::INT] ?? null)) {
            static::$standardTypes[static::INT] = new IntType();
        }
        return static::$standardTypes[static::INT];
    }

    /**
     * @api
     */
    public static function float() : ScalarType
    {
        if(is_null(static::$standardTypes[static::FLOAT] ?? null)) {
            static::$standardTypes[static::FLOAT] = new FloatType();
        }
        return static::$standardTypes[static::FLOAT];
    }

    /**
     * @param Type $wrappedType
     *
     * @api
     */
    public static function listOf(Type $wrappedType) : ListOfType
    {
        return new ListOfType($wrappedType);
    }

    /**
     * @api
     */
    public static function nonNull(NullableType $wrappedType) : NonNull
    {
        return new NonNull($wrappedType);
    }

    /**
     * Checks if the type is a builtin type
     *
     * @return bool
     */
    public static function isBuiltInType(Type $type) : bool
    {
        return in_array($type->name, array_keys(self::getAllBuiltInTypes()), true);
    }

    /**
     * Returns all builtin in types including base scalar and
     * introspection types
     *
     * @return Type[]
     */
    public static function getAllBuiltInTypes()
    {
        if (self::$builtInTypes === null) {
            self::$builtInTypes = array_merge(
                Introspection::getTypes(),
                self::getStandardTypes()
            );
        }

        return self::$builtInTypes;
    }

    /**
     * Returns all builtin scalar types
     *
     * @return ScalarType[]
     */
    public static function getStandardTypes()
    {
        return [
            self::ID => static::id(),
            self::STRING => static::string(),
            self::FLOAT => static::float(),
            self::INT => static::int(),
            self::BOOLEAN => static::boolean(),
        ];
    }

    /**
     * @deprecated Use method getStandardTypes() instead
     *
     * @return Type[]
     */
    public static function getInternalTypes()
    {
        trigger_error(__METHOD__ . ' is deprecated. Use Type::getStandardTypes() instead', E_USER_DEPRECATED);

        return self::getStandardTypes();
    }

    /**
     * @param array<string,ScalarType|mixed> $types
     */
    public static function overrideStandardTypes(array $types)
    {
        $standardTypes = self::getStandardTypes();
        foreach ($types as $type) {
            Utils::invariant(
                $type instanceof Type,
                'Expecting instance of %s, got %s',
                self::class,
                Utils::printSafe($type)
            );
            Utils::invariant(
                isset($type->name, $standardTypes[$type->name]),
                'Expecting one of the following names for a standard type: %s, got %s',
                implode(', ', array_keys($standardTypes)),
                Utils::printSafe($type->name ?? null)
            );
            static::$standardTypes[$type->name] = $type;
        }
    }

    /**
     * @param Type $type
     *
     * @api
     */
    public static function isInputType($type) : bool
    {
        return self::getNamedType($type) instanceof InputType;
    }

    /**
     * @param ?Type $type
     * @api
     */
    public static function getNamedType($type): ?Type
    {
        if ($type === null) {
            return null;
        }
        while ($type instanceof WrappingType) {
            $type = $type->getWrappedType();
        }

        return $type;
    }

    /**
     * @param Type $type
     *
     * @api
     */
    public static function isOutputType($type) : bool
    {
        return self::getNamedType($type) instanceof OutputType;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isLeafType($type)
    {
        return $type instanceof LeafType;
    }

    /**
     * @param Type $type
     *
     * @return bool
     *
     * @api
     */
    public static function isCompositeType($type)
    {
        return $type instanceof CompositeType;
    }

    /**
     * @param Type $type
     *
     * @api
     */
    public static function isAbstractType($type) : bool
    {
        return $type instanceof AbstractType;
    }

    /**
     * @param Type $type
     *
     * @api
     */
    public static function getNullableType(Type $type): Type
    {
        return $type instanceof NonNull
            ? $type->getWrappedType()
            : $type;
    }

    /**
     * @throws InvariantViolation
     */
    public function assertValid()
    {
        Utils::assertValidName($this->name);
    }

    /**
     * @return string
     */
    public function jsonSerialize()
    {
        return $this->toString();
    }

    /**
     * @return string
     */
    public function toString()
    {
        return $this->name;
    }

    /**
     * @return string
     */
    public function __toString()
    {
        try {
            return $this->toString();
        } catch (Throwable $e) {
            echo $e;
        }
    }

    /**
     * @return string|null
     */
    protected function tryInferName()
    {
        if ($this->name) {
            return $this->name;
        }

        // If class is extended - infer name from className
        // QueryType -> Type
        // SomeOtherType -> SomeOther
        $tmp  = new ReflectionClass($this);
        $name = $tmp->getShortName();

        if ($tmp->getNamespaceName() !== __NAMESPACE__) {
            return preg_replace('~Type$~', '', $name);
        }

        return null;
    }
}
