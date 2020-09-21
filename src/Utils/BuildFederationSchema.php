<?php
namespace GraphQL\Utils;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ObjectTypeExtensionFederationNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Schema;

class BuildFederationSchema extends BuildSchema
{
    const FEDERATION_ADDITIONS = <<<SDL

directive @key(fields: _FieldSet!) on OBJECT | INTERFACE
directive @external on FIELD_DEFINITION
directive @requires(fields: _FieldSet!) on FIELD_DEFINITION
directive @provides(fields: _FieldSet!) on FIELD_DEFINITION
SDL;

    private $_schema;

    public function buildFromSchema(Schema $schema)
    {
        $this->_schema = SchemaExtender::extend($schema, Parser::parse(self::FEDERATION_ADDITIONS));

        return $this->_schema;
    }

    public function buildFromSdl(string $source, ?callable $typeConfigDecorator = null, array $options = [])
    {
        $source .= self::FEDERATION_ADDITIONS;
        $doc = $source instanceof DocumentNode ? $source : Parser::parse($source);
        $this->_schema = BuildSchema::buildAST($doc, $typeConfigDecorator, $options);

        return $this->extendSchema();
    }

    public function extendSchema()
    {
        $types = $this->_schema->getTypeMap();
        $entityTypeNames = [];
        foreach ($types as $type) {
            if (
                $type instanceof ObjectType &&
                ((
                    $type->astNode !== null &&
                    !$type->astNode instanceof ObjectTypeExtensionFederationNode &&
                    (isset($type->astNode, $type->astNode->directives) || isset($type->astNode['directives'])
                    )
                    ||
                    (
                    isset($type->directives)
                    ))
                )
            ) {
                if (isset($type->directives)) {
                    foreach ($type->directives as $directive){
                        if ($directive->name === 'key') {
                            $entityTypeNames[] = $type->name;
                        }
                    }
                } else if (isset($type->astNode, $type->astNode->directives) || isset($type->astNode['directives'])) {
                    $directiveNodes = $type->astNode->directives;
                    $nodes = iterator_to_array($directiveNodes->getIterator());

                    foreach ($nodes as $node) {
                        if ($node->name->kind === 'Name' && $node->name->value === 'key') {
                            $entityTypeNames[] = $type->name;
                        }
                    }
                }
            }
        }

        if ($entityTypeNames) {
            $entityTypeNames = implode(' | ', $entityTypeNames);
            $sdl = <<<SDL
            
scalar _FieldSet
scalar _Any
type _Service {
  sdl: String
}

extend type Query {
  _service: _Service!
}
            
union _Entity = $entityTypeNames
extend type Query {
  _entities(representations: [_Any!]!): [_Entity]!
}
SDL;
            $schema = SchemaExtender::extend($schema, Parser::parse($sdl));
        }

        return $schema;
    }
}