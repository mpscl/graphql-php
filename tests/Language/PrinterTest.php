<?php
namespace GraphQL\Tests\Language;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\EnumValueNode;
use GraphQL\Language\AST\FieldNode;
use GraphQL\Language\AST\NameNode;
use GraphQL\Language\AST\OperationDefinitionNode;
use GraphQL\Language\AST\SelectionSetNode;
use GraphQL\Language\AST\StringValueNode;
use GraphQL\Language\AST\VariableNode;
use GraphQL\Language\AST\VariableDefinitionNode;
use GraphQL\Language\Parser;
use GraphQL\Language\Printer;
use PHPUnit\Framework\TestCase;

class PrinterTest extends TestCase
{
    /**
     * @see it('does not alter ast')
     */
    public function testDoesntAlterAST() : void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $astCopy = $ast->cloneDeep();
        $this->assertEquals($astCopy, $ast);

        Printer::doPrint($ast);
        $this->assertEquals($astCopy, $ast);
    }

    /**
     * @see it('prints minimal ast')
     */
    public function testPrintsMinimalAst() : void
    {
        $ast = new FieldNode(['name' => new NameNode(['value' => 'foo'])]);
        $this->assertEquals('foo', Printer::doPrint($ast));
    }

    /**
     * @see it('produces helpful error messages')
     */
    public function testProducesHelpfulErrorMessages() : void
    {
        $badAst1 = new \ArrayObject(['random' => 'Data']);
        $this->expectException(\Throwable::class);
        $this->expectExceptionMessage('Invalid AST Node: {"random":"Data"}');
        Printer::doPrint($badAst1);
    }

    /**
     * @see it('correctly prints non-query operations without name')
     */
    public function testCorrectlyPrintsOpsWithoutName() : void
    {
        $queryAstShorthanded = Parser::parse('query { id, name }');

        $expected = '{
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($queryAstShorthanded));

        $mutationAst = Parser::parse('mutation { id, name }');
        $expected = 'mutation {
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($mutationAst));

        $queryAstWithArtifacts = Parser::parse(
            'query ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'query ($foo: TestType) @testDirective {
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($queryAstWithArtifacts));

        $mutationAstWithArtifacts = Parser::parse(
            'mutation ($foo: TestType) @testDirective { id, name }'
        );
        $expected = 'mutation ($foo: TestType) @testDirective {
  id
  name
}
';
        $this->assertEquals($expected, Printer::doPrint($mutationAstWithArtifacts));
    }

    /**
     * @see it('correctly prints single-line with leading space')
     */
    public function testCorrectlyPrintsSingleLineBlockStringsWithLeadingSpace() : void
    {
        $mutationAstWithArtifacts = Parser::parse(
          '{ field(arg: """    space-led value""") }'
        );
        $expected = '{
  field(arg: """    space-led value""")
}
';
    $this->assertEquals($expected, Printer::doPrint($mutationAstWithArtifacts));
    }

    /**
     * @see it('correctly prints string with a first line indentation')
     */
    public function testCorrectlyPrintsBlockStringsWithAFirstLineIndentation() : void
    {
        $mutationAstWithArtifacts = Parser::parse(
            '{
  field(arg: """
        first
      line
    indentation
  """)
}'
          );
          $expected = '{
  field(arg: """
        first
      line
    indentation
  """)
}
';
      $this->assertEquals($expected, Printer::doPrint($mutationAstWithArtifacts));
    }

    /**
     * @see it('correctly prints single-line with leading space and quotation')
     */
    public function testCorrectlyPrintsSingleLineWithLeadingSpaceAndQuotation() : void
    {
        $mutationAstWithArtifacts = Parser::parse('
            {
              field(arg: """    space-led value "quoted string"
              """)
            }
        ');
        $expected = <<<END
{
  field(arg: """    space-led value "quoted string"
  """)
}

END;
        $this->assertEquals($expected, Printer::doPrint($mutationAstWithArtifacts));
    }

    /**
     * @see it('Experimental: correctly prints fragment defined variables')
     */
    public function testExperimentalCorrectlyPrintsFragmentDefinedVariables() : void
    {
        $fragmentWithVariable = Parser::parse('
          fragment Foo($a: ComplexType, $b: Boolean = false) on TestType {
            id
          }
          ',
            ['experimentalFragmentVariables' => true]
        );

        $this->assertEquals(
            Printer::doPrint($fragmentWithVariable),
            'fragment Foo($a: ComplexType, $b: Boolean = false) on TestType {
  id
}
'
        );
    }

    /**
     * @see it('correctly prints single-line with leading space and quotation')
     */
    public function testCorrectlyPrintsSingleLineStringsWithLeadingSpaceAndQuotation() : void
    {
        $mutationAstWithArtifacts = Parser::parse(
            '{
  field(arg: """    space-led value "quoted string"
  """)
}'
          );
          $expected = '{
  field(arg: """    space-led value "quoted string"
  """)
}
';
      $this->assertEquals($expected, Printer::doPrint($mutationAstWithArtifacts));
    }

    /**
     * @see it('prints kitchen sink')
     */
    public function testPrintsKitchenSink() : void
    {
        $kitchenSink = file_get_contents(__DIR__ . '/kitchen-sink.graphql');
        $ast = Parser::parse($kitchenSink);

        $printed = Printer::doPrint($ast);

        $expected = <<<'EOT'
query queryName($foo: ComplexType, $site: Site = MOBILE) {
  whoever123is: node(id: [123, 456]) {
    id
    ... on User @defer {
      field2 {
        id
        alias: field1(first: 10, after: $foo) @include(if: $foo) {
          id
          ...frag
        }
      }
    }
    ... @skip(unless: $foo) {
      id
    }
    ... {
      id
    }
  }
}

mutation likeStory {
  like(story: 123) @defer {
    story {
      id
    }
  }
}

subscription StoryLikeSubscription($input: StoryLikeSubscribeInput) {
  storyLikeSubscribe(input: $input) {
    story {
      likers {
        count
      }
      likeSentence {
        text
      }
    }
  }
}

fragment frag on Friend {
  foo(size: $size, bar: $b, obj: {key: "value", block: """
    block string uses \"""
  """})
}

{
  unnamed(truthy: true, falsey: false, nullish: null)
  query
}

EOT;
        $this->assertEquals($expected, $printed);
    }
}
