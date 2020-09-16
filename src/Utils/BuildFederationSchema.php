<?php
namespace GraphQL\Utils;

use GraphQL\Language\AST\DocumentNode;
use GraphQL\Language\AST\ObjectTypeExtensionFederationNode;
use GraphQL\Language\Parser;
use GraphQL\Type\Definition\ObjectType;

class BuildFederationSchema extends BuildSchema
{
    const FEDERATION_ADDITIONS = <<<SDL

scalar _FieldSet
scalar _Any
type _Service {
  sdl: String
}

directive @key(fields: _FieldSet!) on OBJECT | INTERFACE
directive @external on FIELD_DEFINITION
directive @requires(fields: _FieldSet!) on FIELD_DEFINITION
directive @provides(fields: _FieldSet!) on FIELD_DEFINITION
SDL;

    public static function build($source, ?callable $typeConfigDecorator = null, array $options = [])
    {
        if (!$source instanceof DocumentNode) {
            $source .= self::FEDERATION_ADDITIONS;
        }

        $doc = $source instanceof DocumentNode ? $source : Parser::parse($source);

        $schema = self::buildAST($doc, $typeConfigDecorator, $options);

        $types = $schema->getTypeMap();
        $entityTypeNames = [];
        foreach ($types as $type) {
            if (
                $type instanceof ObjectType &&
                $type->astNode !== null &&
                !$type->astNode instanceof ObjectTypeExtensionFederationNode &&
                (isset($type->astNode->directives) || isset($type->astNode['directives']))
            ) {
                $directiveNodes = $type->astNode->directives;
                $nodes = iterator_to_array($directiveNodes->getIterator());

                foreach ($nodes as $node) {
                    if ($node->name->kind === 'Name' && $node->name->value === 'key') {
                        $entityTypeNames[] = $type->name;
                    }
                }
            }
        }

        if ($entityTypeNames) {
            $entityTypeNames = implode(' | ', $entityTypeNames);
            $sdl = <<<SDL
            
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