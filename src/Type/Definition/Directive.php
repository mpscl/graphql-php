<?php

declare(strict_types=1);

namespace GraphQL\Type\Definition;

use GraphQL\Language\AST\DirectiveDefinitionNode;
use GraphQL\Language\DirectiveLocation;
use GraphQL\Utils\Utils;
use function array_key_exists;
use function is_array;

class Directive
{
    public const DEFAULT_DEPRECATION_REASON = 'No longer supported';

    public const INCLUDE_NAME         = 'include';
    public const IF_ARGUMENT_NAME     = 'if';
    public const SKIP_NAME            = 'skip';
    public const DEPRECATED_NAME      = 'deprecated';
    public const REASON_ARGUMENT_NAME = 'reason';

    /** @var Directive[]|null */
    public static $internalDirectives;

    // Schema Definitions

    /** @var string */
    public $name;

    /** @var string|null */
    public $description;

    /** @var string[] */
    public $locations;

    /** @var FieldArgument[] */
    public $args = [];

    /** @var DirectiveDefinitionNode|null */
    public $astNode;

    /** @var mixed[] */
    public $config;

    /**
     * @param array{
     *      args: array<string,array|FieldArgument>,
     *      astNode?: DirectiveDefinitionNode,
     *      description?: string,
     *      locations?: array<string>,
     *      name?: string
     * } $config
     */
    public function __construct(array $config)
    {
        if (isset($config['args'])) {
            $args = [];
            foreach ($config['args'] as $name => $arg) {
                if (is_array($arg)) {
                    $args[] = new FieldArgument($arg + ['name' => $name]);
                } else {
                    $args[] = $arg;
                }
            }
            $this->args = $args;
            unset($config['args']);
        }

        $this->description = $config['description'] ?? null;
        $this->astNode = $config['astNode'] ?? null;

        Utils::invariant($config['name'] ?? null, 'Directive must be named.');
        $this->name = $config['name'];

        Utils::invariant(is_array($config['locations'] ?? null), 'Must provide locations for directive.');
        $this->locations = $config['locations'];

        $this->config = $config;
    }

    /**
     * @return Directive
     */
    public static function includeDirective()
    {
        $internal = self::getInternalDirectives();

        return $internal['include'];
    }

    /**
     * @return Directive[]
     */
    public static function getInternalDirectives() : array
    {
        if (self::$internalDirectives === null) {
            self::$internalDirectives = [
                'include'    => new self([
                    'name'        => self::INCLUDE_NAME,
                    'description' => 'Directs the executor to include this field or fragment only when the `if` argument is true.',
                    'locations'   => [
                        DirectiveLocation::FIELD,
                        DirectiveLocation::FRAGMENT_SPREAD,
                        DirectiveLocation::INLINE_FRAGMENT,
                    ],
                    'args'        => [new FieldArgument([
                        'name'        => self::IF_ARGUMENT_NAME,
                        'type'        => Type::nonNull(Type::boolean()),
                        'description' => 'Included when true.',
                    ]),
                    ],
                ]),
                'skip'       => new self([
                    'name'        => self::SKIP_NAME,
                    'description' => 'Directs the executor to skip this field or fragment when the `if` argument is true.',
                    'locations'   => [
                        DirectiveLocation::FIELD,
                        DirectiveLocation::FRAGMENT_SPREAD,
                        DirectiveLocation::INLINE_FRAGMENT,
                    ],
                    'args'        => [new FieldArgument([
                        'name'        => self::IF_ARGUMENT_NAME,
                        'type'        => Type::nonNull(Type::boolean()),
                        'description' => 'Skipped when true.',
                    ]),
                    ],
                ]),
                'deprecated' => new self([
                    'name'        => self::DEPRECATED_NAME,
                    'description' => 'Marks an element of a GraphQL schema as no longer supported.',
                    'locations'   => [
                        DirectiveLocation::FIELD_DEFINITION,
                        DirectiveLocation::ENUM_VALUE,
                    ],
                    'args'        => [new FieldArgument([
                        'name'         => self::REASON_ARGUMENT_NAME,
                        'type'         => Type::string(),
                        'description'  =>
                            'Explains why this element was deprecated, usually also including a ' .
                            'suggestion for how to access supported similar data. Formatted ' .
                            'in [Markdown](https://daringfireball.net/projects/markdown/).',
                        'defaultValue' => self::DEFAULT_DEPRECATION_REASON,
                    ]),
                    ],
                ]),
            ];
        }

        return self::$internalDirectives;
    }

    /**
     * @return Directive
     */
    public static function skipDirective()
    {
        $internal = self::getInternalDirectives();

        return $internal['skip'];
    }

    /**
     * @return Directive
     */
    public static function deprecatedDirective()
    {
        $internal = self::getInternalDirectives();

        return $internal['deprecated'];
    }

    /**
     * @return bool
     */
    public static function isSpecifiedDirective(Directive $directive)
    {
        return array_key_exists($directive->name, self::getInternalDirectives());
    }
}
