<?php

namespace ApiPlatform\OpenAPI\Enum;

enum OpenApiEndpointParameterTypeEnum: string {
    case TYPE_STRING = 'string';

    case TYPE_NUMBER = 'number';

    case TYPE_BOOLEAN = 'boolean';

    case TYPE_INTEGER = 'integer';

    case TYPE_ARRAY = 'array';

    case TYPE_OBJECT = 'object';
}
