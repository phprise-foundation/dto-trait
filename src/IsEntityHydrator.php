<?php

declare(strict_types=1);

namespace Phprise\Dto\Trait;

use Phprise\Dto\Contract\EntityHydrator;
use Phprise\Dto\Contract\DataTransferObjectInterface;
use Phprise\Common\Cast\TypeCoercerInterface;
use Phprise\Common\ValueObject\StringObject;
use ReflectionClass;
use ReflectionProperty;
use RuntimeException;

trait IsEntityHydrator
{
    use \Phprise\Common\Trait\IsArrayable;

    #[\Override]
    public function toEntity(string|object $entity, int $flags = self::USE_DEFAULTS, array $options = []): object
    {
        $className = $this->resolveClassName($entity);
        $reflectionDto = self::getReflectionClass(static::class);
        $reflectionEntity = self::getReflectionClass($className);
        $instance = $this->resolveEntityInstance($entity);
        
        $this->transferAllProperties($reflectionDto, $reflectionEntity, $instance, $flags, $options);
        $this->applyCallbacksIfRequested($instance, $flags, $options);

        return $instance;
    }

    private function transferAllProperties(
        ReflectionClass $reflectionDto,
        ReflectionClass $reflectionEntity,
        object $instance,
        int $flags,
        array $options
    ): void {
        $properties = $reflectionDto->getProperties();
        foreach ($properties as $property) {
            $this->transferPropertyToEntity($property, $reflectionEntity, $instance, $flags, $options);
        }
    }

    private function applyCallbacksIfRequested(object $instance, int $flags, array $options): void
    {
        if ($flags & self::USE_CALLBACKS) {
            $this->executeCallbacks($instance, $this, $options);
        }
    }

    private function createClassFromString(string $className): object
    {
        $this->validateClassExists($className);
        $reflectionClass = self::getReflectionClass($className);
        
        return $this->instantiateClass($reflectionClass);
    }

    private function validateClassExists(string $className): void
    {
        if (!class_exists($className)) {
            throw new RuntimeException(sprintf('Entity %s not found', $className));
        }
    }

    private function instantiateClass(ReflectionClass $reflectionClass): object
    {
        $constructor = $reflectionClass->getConstructor();
        
        if ($this->hasNoRequiredParameters($constructor)) {
            return $reflectionClass->getName()();
        }

        return $reflectionClass->newInstanceWithoutConstructor();
    }

    private function hasNoRequiredParameters(\ReflectionMethod|null $constructor): bool
    {
        return !$constructor || $constructor->getNumberOfRequiredParameters() === 0;
    }

    private function resolveClassName(string|object $entity): string
    {
        if (is_string($entity)) {
            return $entity;
        }

        return $entity::class;
    }

    private function transferPropertyToEntity(
        ReflectionProperty $propertyDto,
        ReflectionClass $reflectionEntity,
        object $instance,
        int $flags,
        array $options
    ): void {
        $propertyName = $propertyDto->getName();
        $propertyValue = $propertyDto->getValue($this);

        if ($propertyValue === null) {
            return;
        }

        $targetPropertyName = $this->resolvePropertyName($propertyName, $flags);
        $propertyEntity = $this->findEntityProperty($reflectionEntity, $targetPropertyName, $flags);
        
        if ($propertyEntity === null) {
            return;
        }

        $coercedValue = $this->preparePropertyValue($propertyValue, $propertyEntity, $flags, $options);
        $this->setEntityValue($instance, $propertyEntity, $coercedValue, $flags);
    }

    private function findEntityProperty(
        ReflectionClass $reflectionEntity,
        string $targetPropertyName,
        int $flags
    ): ?ReflectionProperty {
        try {
            return $reflectionEntity->getProperty($targetPropertyName);
        } catch (RuntimeException $e) {
            $this->handlePropertyNotFound($targetPropertyName, $reflectionEntity, $flags, $e);
            return null;
        }
    }

    private function handlePropertyNotFound(
        string $propertyName,
        ReflectionClass $reflectionEntity,
        int $flags,
        RuntimeException $e
    ): void {
        if ($flags & self::THROW_ON_FAIL) {
            throw new RuntimeException(
                sprintf('Property %s not found in entity %s', $propertyName, $reflectionEntity->getName()),
                0,
                $e
            );
        }
    }

    private function preparePropertyValue(
        mixed $propertyValue,
        ReflectionProperty $propertyEntity,
        int $flags,
        array $options
    ): mixed {
        $typeReflection = $propertyEntity->getType();
        
        if (!$typeReflection instanceof \ReflectionNamedType) {
            return $propertyValue;
        }

        $typeEntity = $typeReflection->getName();

        if ($propertyValue instanceof DataTransferObjectInterface) {
            return $propertyValue->toEntity($typeEntity, $flags, $options);
        }

        return $this->coerceValue($propertyValue, $typeReflection, $flags, $options);
    }

    private function resolvePropertyName(string $propertyName, int $flags): string
    {
        if ($flags & self::CAMEL_TO_SNAKE) {
            return $this->convertToSnakeCase($propertyName);
        }

        if ($flags & self::SNAKE_TO_CAMEL) {
            return $this->convertToCamelCase($propertyName);
        }

        return $propertyName;
    }

    private function convertToSnakeCase(string $propertyName): string
    {
        return (new StringObject($propertyName))->toSnake();
    }

    private function convertToCamelCase(string $propertyName): string
    {
        return (new StringObject($propertyName))->toCamel();
    }

    private function setEntityValue(object $instance, ReflectionProperty $propertyEntity, mixed $value, int $flags): void
    {
        $propertyName = $propertyEntity->getName();
        $setter = $this->buildSetterName($propertyName);

        if (method_exists($instance, $setter)) {
            $instance->$setter($value);
            return;
        }

        $this->setPropertyValueDirectly($instance, $propertyEntity, $value, $flags);
    }

    private function buildSetterName(string $propertyName): string
    {
        return 'set' . ucfirst($propertyName);
    }

    private function setPropertyValueDirectly(
        object $instance,
        ReflectionProperty $propertyEntity,
        mixed $value,
        int $flags
    ): void {
        try {
            $propertyEntity->setValue($instance, $value);
        } catch (\Throwable $e) {
            $this->handleSetValueFailure($instance, $propertyEntity, $flags, $e);
        }
    }

    private function handleSetValueFailure(
        object $instance,
        ReflectionProperty $propertyEntity,
        int $flags,
        \Throwable $e
    ): void {
        if ($flags & self::THROW_ON_FAIL) {
            throw new RuntimeException(
                sprintf('Failed to set property %s on entity %s', $propertyEntity->getName(), get_class($instance)),
                0,
                $e
            );
        }
    }

    private function coerceValue(mixed $value, \ReflectionNamedType $type, int $flags, array $options): mixed
    {
        if ($value === null) {
            return null;
        }

        $typeName = $type->getName();

        if ($this->isTypeMatching($value, $typeName)) {
            return $value;
        }

        $typeCoercers = $this->collectTypeCoercers($flags, $options);
        $applicableCoercers = $this->filterApplicableCoercers($typeCoercers, $typeName);

        return $this->applyCoercers($applicableCoercers, $value);
    }

    private function isTypeMatching(mixed $value, string $typeName): bool
    {
        return gettype($value) === $typeName || $value instanceof $typeName;
    }

    private function collectTypeCoercers(int $flags, array $options): array
    {
        $typeCoercers = $options['typeCoercers'] ?? [];

        if ($flags & self::USE_DEFAULTS) {
            return array_merge($typeCoercers, $this->getDefaultCoercers());
        }

        return $typeCoercers;
    }

    private function filterApplicableCoercers(array $typeCoercers, string $typeName): array
    {
        return array_filter(
            $typeCoercers,
            fn(TypeCoercerInterface $coercer) => $coercer->supports($typeName)
        );
    }

    private function applyCoercers(array $applicableCoercers, mixed $value): mixed
    {
        return array_reduce(
            $applicableCoercers,
            fn(mixed $currentValue, TypeCoercerInterface $coercer): mixed => $coercer->coerce($currentValue),
            $value
        );
    }

    private function getDefaultCoercers(): array
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

    private function executeCallbacks(object $entity, object $dto, array $options): void
    {
        $callbacks = $options['callbacks'] ?? [];

        foreach ($callbacks as $entityPropertyName => $callback) {
            $this->executeCallback($entity, $dto, $entityPropertyName, $callback);
        }
    }

    private function executeCallback(object $entity, object $dto, string $entityPropertyName, mixed $callback): void
    {
        if (!is_callable($callback)) {
            return;
        }

        $value = $callback($dto);
        $this->setEntityValueByName($entity, $entityPropertyName, $value);
    }

    private function setEntityValueByName(object $entity, string $propertyName, mixed $value): void
    {
        $setter = $this->buildSetterName($propertyName);

        if (method_exists($entity, $setter)) {
            $entity->$setter($value);
            return;
        }

        $this->setEntityPropertyByReflection($entity, $propertyName, $value);
    }

    private function setEntityPropertyByReflection(object $entity, string $propertyName, mixed $value): void
    {
        $reflectionEntity = new \ReflectionClass($entity);
        $property = $reflectionEntity->getProperty($propertyName);
        $property->setValue($entity, $value);
    }

    private function resolveEntityInstance(string|object $entity): object
    {
        if (is_string($entity)) {
            return $this->createClassFromString($entity);
        }

        return $entity;
    }
}
