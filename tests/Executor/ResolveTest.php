<?php
namespace GraphQL\Tests\Executor;

use GraphQL\GraphQL;
use GraphQL\Type\Schema;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\Type;

require_once __DIR__ . '/TestClasses.php';
use PHPUnit\Framework\TestCase;

class ResolveTest extends TestCase
{
    // Execute: resolve function

    private function buildSchema($testField)
    {
        return new Schema([
            'query' => new ObjectType([
                'name' => 'Query',
                'fields' => [
                    'test' => $testField
                ]
            ])
        ]);
    }

    /**
     * @see it('default function accesses properties')
     */
    public function testDefaultFunctionAccessesProperties() : void
    {
        $schema = $this->buildSchema(['type' => Type::string()]);

        $source = [
            'test' => 'testValue'
        ];

        $this->assertEquals(
            ['data' => ['test' => 'testValue']],
            GraphQL::executeQuery($schema, '{ test }', $source)->toArray()
        );
    }

    /**
     * @see it('default function calls methods')
     */
    public function testDefaultFunctionCallsClosures() : void
    {
        $schema = $this->buildSchema(['type' => Type::string()]);
        $_secret = 'secretValue' . uniqid();

        $source = [
            'test' => function() use ($_secret) {
                return $_secret;
            }
        ];
        $this->assertEquals(
            ['data' => ['test' => $_secret]],
            GraphQL::executeQuery($schema, '{ test }', $source)->toArray()
        );
    }

    /**
     * @see it('default function passes args and context')
     */
    public function testDefaultFunctionPassesArgsAndContext() : void
    {
        $schema = $this->buildSchema([
            'type' => Type::int(),
            'args' => [
                'addend1' => [ 'type' => Type::int() ],
            ],
        ]);

        $source = new Adder(700);

        $result = GraphQL::executeQuery($schema, '{ test(addend1: 80) }', $source, ['addend2' => 9])->toArray();
        $this->assertEquals(['data' => ['test' => 789]], $result);
    }

    /**
     * @see it('uses provided resolve function')
     */
    public function testUsesProvidedResolveFunction() : void
    {
        $schema = $this->buildSchema([
            'type' => Type::string(),
            'args' => [
                'aStr' => ['type' => Type::string()],
                'aInt' => ['type' => Type::int()],
            ],
            'resolve' => function ($source, $args) {
                return json_encode([$source, $args]);
            }
        ]);

        $this->assertEquals(
            ['data' => ['test' => '[null,[]]']],
            GraphQL::executeQuery($schema, '{ test }')->toArray()
        );

        $this->assertEquals(
            ['data' => ['test' => '["Source!",[]]']],
            GraphQL::executeQuery($schema, '{ test }', 'Source!')->toArray()
        );

        $this->assertEquals(
            ['data' => ['test' => '["Source!",{"aStr":"String!"}]']],
            GraphQL::executeQuery($schema, '{ test(aStr: "String!") }', 'Source!')->toArray()
        );

        $this->assertEquals(
            ['data' => ['test' => '["Source!",{"aStr":"String!","aInt":-123}]']],
            GraphQL::executeQuery($schema, '{ test(aInt: -123, aStr: "String!") }', 'Source!')->toArray()
        );
    }
}
