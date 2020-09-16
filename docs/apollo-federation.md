# Apollo Federation support

Alpha version

Example

```php
<?php
use GraphQL\Utils\BuildSchema;

$contents = file_get_contents('schema.graphql');
$result = GraphQL::executeQuery(
    BuildFederationSchema::build($sdl),
    $input['query'],
    FederationRootValue::extend($resolvers)
);
```

`BuildFederationSchema::build()` method extends schema with federation fields and directives and builds it.
`FederationRootValue::extend()` method extends user's field resolvers with resolvers for federated fields.