<?php
namespace GraphQL\Utils;

use GraphQL\Type\Definition\ObjectType;
use GraphQL\Type\Definition\ResolveInfo;

class FederationRootValue
{
    public static function extend($rootValue)
    {
        $federationFieldsResolvers = [
            '_service' => function($rootValue, $args, $context, ResolveInfo $info) {
                return [
                    'sdl' => SchemaPrinter::doPrint($info->schema),
                ];
            },
            '_entities' => function($rootValue, $args, $context, ResolveInfo $info) {
                $representations = $args['representations'];

                return array_map(
                    function ($representation) use ($info) {
                        $typeName = $representation['__typename'];

                        /** @var ObjectType $type */
                        $type = $info->schema->getType($typeName);

                        if (!$type || $type instanceof ObjectType === false) {
                            throw new \Exception(
                                `The _entities resolver tried to load an entity for type "${$typeName}", but no object type of that name was found in the schema`
                            );
                        }

                        $resolver = $type->resolveFieldFn ?: function () use ($representation) {
                            return $representation;
                        };

                        return $resolver();
                    },
                    $representations
                );
            },
        ];

        return $rootValue + $federationFieldsResolvers;
    }
}