<?php

declare(strict_types=1);

namespace League\OpenAPIValidation\Schema\Keywords;

use cebe\openapi\spec\Type as CebeType;
use League\OpenAPIValidation\Foundation\ArrayHelper;
use League\OpenAPIValidation\Schema\Exception\FormatMismatch;
use League\OpenAPIValidation\Schema\Exception\InvalidSchema;
use League\OpenAPIValidation\Schema\Exception\TypeMismatch;
use League\OpenAPIValidation\Schema\TypeFormats\FormatsContainer;
use RuntimeException;
use Psr\Http\Message\ServerRequestInterface;

use function class_exists;
use function is_array;
use function is_bool;
use function is_int;
use function is_numeric;
use function is_object;
use function is_string;
use function sprintf;

class Type extends BaseKeyword
{
    /**
     * The value of this keyword MUST be either a string ONLY.
     *
     * String values MUST be one of the seven primitive types defined by the
     * core specification.
     *
     * An instance matches successfully if its primitive type is one of the
     * types defined by keyword.  Recall: "number" includes "integer".
     *
     * @param mixed $data
     *
     * @throws TypeMismatch
     */
    public function validate($data, string $type, ?string $format = null): void
    {
        //Temporary workaround to log non-compliant endpoints
        $container = \Lib\Container::getInstance();
        $request = $container->get(ServerRequestInterface::class);
        $logger = $container->get('Logger');

        switch ($type) {
            case CebeType::OBJECT:
                if (! is_object($data) && ! (is_array($data) && ArrayHelper::isAssoc($data)) && $data !== []) {
                    throw TypeMismatch::becauseTypeDoesNotMatch(CebeType::OBJECT, $data);
                }

                break;
            case CebeType::ARRAY:
                if (! is_array($data) || ArrayHelper::isAssoc($data)) {
                    throw TypeMismatch::becauseTypeDoesNotMatch('array', $data);
                }

                break;
            case CebeType::BOOLEAN:
                //Temporary workaround to log non-compliant endpoints
                if(is_string($data)){
                    $logger->info('Incompatible data type provided on GraphQL body',
                        [
                            'request_path' => $request->getUri()->getPath(),
                            'data_type' => CebeType::BOOLEAN,
                            'value' => $data
                        ]
                    );
                }

                $stringifiedBool = is_scalar($data) && preg_match('#^(true|false)$#i', (string) $data);
                if (! is_bool($data) && ! $stringifiedBool) {
                    throw TypeMismatch::becauseTypeDoesNotMatch(CebeType::BOOLEAN, $data);
                }

                break;
            case CebeType::NUMBER:
                //Temporary workaround to log non-compliant endpoints
                if (is_string($data)) {
                    $logger->info('Incompatible data type provided on GraphQL body',
                        [
                            'request_path' => $request->getUri()->getPath(),
                            'data_type' => CebeType::NUMBER,
                            'value' => $data
                        ]
                    );
                }
                if (! is_numeric($data)) {
                    throw TypeMismatch::becauseTypeDoesNotMatch(CebeType::NUMBER, $data);
                }

                break;
            case CebeType::INTEGER:
                //Temporary workaround to log non-compliant endpoints
                if(is_string($data)){
                    $logger->info('Incompatible data type provided on GraphQL body',
                        [
                            'request_path' => $request->getUri()->getPath(),
                            'data_type' => CebeType::INTEGER,
                            'value' => $data
                        ]
                    );
                }

                $stringifiedInt = is_scalar($data) && preg_match('#^[-+]?\d+$#', (string) $data) && ! is_float($data);
                if (! is_int($data) && ! $stringifiedInt) {
                    throw TypeMismatch::becauseTypeDoesNotMatch(CebeType::INTEGER, $data);
                }

                break;
            case CebeType::STRING:
                if (! is_string($data)) {
                    throw TypeMismatch::becauseTypeDoesNotMatch(CebeType::STRING, $data);
                }

                break;
            default:
                throw InvalidSchema::becauseTypeIsNotKnown($type);
        }

        // 2. Validate format now

        if (! $format) {
            return;
        }

        $formatValidator = FormatsContainer::getFormat($type, $format); // callable or FQCN
        if ($formatValidator === null) {
            return;
        }

        if (is_string($formatValidator) && ! class_exists($formatValidator)) {
            throw new RuntimeException(sprintf("'%s' does not loaded", $formatValidator));
        }

        if (is_string($formatValidator)) {
            $formatValidator = new $formatValidator();
        }

        if (! $formatValidator($data)) {
            throw FormatMismatch::fromFormat($format, $data, $type);
        }
    }
}
