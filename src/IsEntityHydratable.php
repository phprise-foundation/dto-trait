<?php

declare(strict_types=1);

namespace Phprise\Dto\Trait;

use Phprise\Dto\Contract\EntityHydratable;
use Phprise\Common\Cast\TypeCoercerInterface;
use Phprise\Common\ValueObject\StringObject;
use RuntimeException;

trait IsEntityHydratable
{
    use \Phprise\Common\Trait\IsArrayable;

    #[\Override]
    public static function fromEntity(object $entity, int $flags = self::USE_DEFAULTS, array $options = []): static
    {
        $reflectionEntity = new \ReflectionClass($entity);
        $reflectionDto = new \ReflectionClass(static::class);
        $instance = $reflectionDto->newInstanceWithoutConstructor();
        
        self::transferAllProperties($reflectionEntity, $reflectionDto, $instance, $entity, $flags, $options);
        self::applyCallbacksIfRequested($entity, $instance, $flags, $options);

        return $instance;
    }

    private static function transferAllProperties(
        \ReflectionClass $reflectionEntity,
        \ReflectionClass $reflectionDto,
        object $instance,
        object $entity,
        int $flags,
        array $options
    ): void {
        $properties = $reflectionEntity->getProperties();
        foreach ($properties as $property) {
            self::transferPropertyFromEntity($property, $reflectionDto, $instance, $entity, $flags, $options);
        }
    }

    private static function applyCallbacksIfRequested(object $entity, object $instance, int $flags, array $options): void
    {
        if ($flags & self::USE_CALLBACKS) {
            self::executeCallbacks($entity, $instance, $options);
        }
    }

    private static function transferPropertyFromEntity(
        \ReflectionProperty $propertyEntity,
        \ReflectionClass $reflectionDto,
        object $instance,
        object $entity,
        int $flags,
        array $options
    ): void {
        $propertyName = $propertyEntity->getName();
        $propertyValue = $propertyEntity->getValue($entity);

        if ($propertyValue === null) {
            return;
        }

        $targetPropertyName = self::resolvePropertyName($propertyName, $flags);
        $propertyDto = self::findDtoProperty($reflectionDto, $targetPropertyName, $flags);
        
        if ($propertyDto === null) {
            return;
        }

        $coercedValue = self::preparePropertyValue($propertyValue, $propertyDto, $flags, $options);
        self::setDtoValue($instance, $propertyDto, $coercedValue, $flags);
    }

    private static function findDtoProperty(
        \ReflectionClass $reflectionDto,
        string $targetPropertyName,
        int $flags
    ): ?\ReflectionProperty {
        try {
            return $reflectionDto->getProperty($targetPropertyName);
        } catch (\Throwable $e) {
            self::handlePropertyNotFound($targetPropertyName, $reflectionDto, $flags, $e);
            return null;
        }
    }

    private static function handlePropertyNotFound(
        string $propertyName,
        \ReflectionClass $reflectionDto,
        int $flags,
        \Throwable $e
    ): void {
        if ($flags & self::THROW_ON_FAIL) {
            throw new RuntimeException(
                sprintf('Property %s not found in DTO %s', $propertyName, $reflectionDto->getName()),
                0,
                $e
            );
        }
    }

    private static function preparePropertyValue(
        mixed $propertyValue,
        \ReflectionProperty $propertyDto,
        int $flags,
        array $options
    ): mixed {
        $typeReflection = $propertyDto->getType();
        
        if (!$typeReflection instanceof \ReflectionNamedType) {
            return $propertyValue;
        }

        return self::coerceValue($propertyValue, $typeReflection, $flags, $options);
    }

    private static function resolvePropertyName(string $propertyName, int $flags): string
    {
        if ($flags & self::CAMEL_TO_SNAKE) {
            return self::convertToSnakeCase($propertyName);
        }

        if ($flags & self::SNAKE_TO_CAMEL) {
            return self::convertToCamelCase($propertyName);
        }

        return $propertyName;
    }

    private static function convertToSnakeCase(string $propertyName): string
    {
        return (new StringObject($propertyName))->toSnake();
    }

    private static function convertToCamelCase(string $propertyName): string
    {
        return (new StringObject($propertyName))->toCamel();
    }

    private static function setDtoValue(object $dto, \ReflectionProperty $propertyDto, mixed $value, int $flags): void
    {
        $propertyName = $propertyDto->getName();
        $setter = self::buildSetterName($propertyName);

        if (method_exists($dto, $setter)) {
            $dto->$setter($value);
            return;
        }

        self::setPropertyValueDirectly($dto, $propertyDto, $value, $flags);
    }

    private static function buildSetterName(string $propertyName): string
    {
        return 'set' . ucfirst($propertyName);
    }

    private static function setPropertyValueDirectly(
        object $dto,
        \ReflectionProperty $propertyDto,
        mixed $value,
        int $flags
    ): void {
        try {
            $propertyDto->setValue($dto, $value);
        } catch (\Throwable $e) {
            self::handleSetValueFailure($dto, $propertyDto, $flags, $e);
        }
    }

    private static function handleSetValueFailure(
        object $dto,
        \ReflectionProperty $propertyDto,
        int $flags,
        \Throwable $e
    ): void {
        if ($flags & self::THROW_ON_FAIL) {
            throw new RuntimeException(
                sprintf('Failed to set property %s on DTO %s', $propertyDto->getName(), get_class($dto)),
                0,
                $e
            );
        }
    }

    private static function coerceValue(mixed $value, \ReflectionNamedType $type, int $flags, array $options): mixed
    {
        if ($value === null) {
            return null;
        }

        $typeName = $type->getName();

        if (self::isTypeMatching($value, $typeName)) {
            return $value;
        }

        $typeCoercers = self::collectTypeCoercers($flags, $options);
        $applicableCoercers = self::filterApplicableCoercers($typeCoercers, $typeName);

        return self::applyCoercers($applicableCoercers, $value);
    }

    private static function isTypeMatching(mixed $value, string $typeName): bool
    {
        return gettype($value) === $typeName || $value instanceof $typeName;
    }

    private static function collectTypeCoercers(int $flags, array $options): array
    {
        $typeCoercers = $options['typeCoercers'] ?? [];

        if ($flags & self::USE_DEFAULTS) {
            return array_merge($typeCoercers, self::getDefaultCoercers());
        }

        return $typeCoercers;
    }

    private static function filterApplicableCoercers(array $typeCoercers, string $typeName): array
    {
        return array_filter(
            $typeCoercers,
            fn(TypeCoercerInterface $coercer) => $coercer->supports($typeName)
        );
    }

    private static function applyCoercers(array $applicableCoercers, mixed $value): mixed
    {
        return array_reduce(
            $applicableCoercers,
            fn(mixed $currentValue, TypeCoercerInterface $coercer): mixed => $coercer->coerce($currentValue),
            $value
        );
    }

    private static function getDefaultCoercers(): array
    {
        return [
            new \Phprise\Common\Cast\IntCoercer(),
            new \Phprise\Common\Cast\FloatCoercer(),
            new \Phprise\Common\Cast\BoolCoercer(),
            new \Phprise\Common\Cast\StringCoercer(),
            new \Phprise\Common\Cast\DateTimeImmutableCoercer(),
            new \Phprise\Common\Cast\DateTimeMutableCoercer(),
        ];
    }

    private static function executeCallbacks(object $entity, object $dto, array $options): void
    {
        $callbacks = $options['callbacks'] ?? [];

        foreach ($callbacks as $dtoPropertyName => $callback) {
            self::executeCallback($entity, $dto, $dtoPropertyName, $callback);
        }
    }

    private static function executeCallback(object $entity, object $dto, string $dtoPropertyName, mixed $callback): void
    {
        if (!is_callable($callback)) {
            return;
        }

        $value = $callback($entity);
        self::setDtoValueByName($dto, $dtoPropertyName, $value);
    }

    private static function setDtoValueByName(object $dto, string $propertyName, mixed $value): void
    {
        $setter = self::buildSetterName($propertyName);

        if (method_exists($dto, $setter)) {
            $dto->$setter($value);
            return;
        }

        self::setDtoPropertyByReflection($dto, $propertyName, $value);
    }

    private static function setDtoPropertyByReflection(object $dto, string $propertyName, mixed $value): void
    {
        $reflectionDto = new \ReflectionClass($dto);
        $property = $reflectionDto->getProperty($propertyName);
        $property->setValue($dto, $value);
    }
}
