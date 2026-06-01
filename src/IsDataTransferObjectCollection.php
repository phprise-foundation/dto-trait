<?php

declare(strict_types=1);

namespace Phprise\Dto\Trait;

use Phprise\Dto\Contract\DataTransferObjectCollectionInterface;
use Phprise\Dto\Contract\DataTransferObjectInterface;
use RuntimeException;

trait IsDataTransferObjectCollection
{
    use IsDataTransferObject;

    private array $elements = [];

    #[\Override]
    public function toArray(): array
    {
        return array_map(
            fn(DataTransferObjectInterface $element) => $element->toArray(),
            $this->elements
        );
    }

    #[\Override]
    public static function fromArray(array $array, int $flags = self::USE_DEFAULTS, array $options = []): static
    {
        if (empty($array)) {
            return new static([]);
        }

        $itemClass = static::getItemClass();

        if (!class_exists($itemClass)) {
            throw new RuntimeException("The item class '{$itemClass}' does not exist.");
        }

        if (!is_subclass_of($itemClass, DataTransferObjectInterface::class, true)) {
            throw new RuntimeException("The item class '{$itemClass}' must implement " . DataTransferObjectInterface::class);
        }

        $items = [];
        foreach ($array as $item) {
            $items[] = self::hydrateItem($item, $itemClass, $flags, $options);
        }

        return new static($items);
    }

    private static function hydrateItem(mixed $item, string $itemClass, int $flags, array $options): DataTransferObjectInterface
    {
        if ($item instanceof $itemClass) {
            return $item;
        }

        if (is_array($item)) {
            return $itemClass::fromArray($item, $flags, $options);
        }

        throw new RuntimeException("Cannot hydrate item of type " . get_debug_type($item));
    }

    #[\Override]
    public function first(): DataTransferObjectInterface|false
    {
        return reset($this->elements);
    }

    private function createFrom(array $elements): static
    {
        return new static($elements);
    }

    #[\Override]
    public function last(): DataTransferObjectInterface|false
    {
        return end($this->elements);
    }

    #[\Override]
    public function key(): string|int|null
    {
        return key($this->elements);
    }

    #[\Override]
    public function next(): DataTransferObjectInterface|false
    {
        return next($this->elements);
    }

    #[\Override]
    public function current(): DataTransferObjectInterface|false
    {
        return current($this->elements);
    }

    #[\Override]
    public function remove(string|int $key): ?DataTransferObjectInterface
    {
        if (!isset($this->elements[$key]) && !array_key_exists($key, $this->elements)) {
            return null;
        }

        $removed = $this->elements[$key];
        unset($this->elements[$key]);

        return $removed;
    }

    #[\Override]
    public function removeElement(mixed $element): bool
    {
        $key = array_search($element, $this->elements, true);

        if ($key === false) {
            return false;
        }

        unset($this->elements[$key]);

        return true;
    }

    #[\Override]
    #[\ReturnTypeWillChange]
    public function offsetExists(mixed $offset): bool
    {
        return $this->containsKey($offset);
    }

    #[\Override]
    #[\ReturnTypeWillChange]
    public function offsetGet(mixed $offset)
    {
        return $this->get($offset);
    }

    #[\Override]
    #[\ReturnTypeWillChange]
    public function offsetSet(mixed $offset, mixed $value): void
    {
        if ($offset === null) {
            $this->add($value);
            return;
        }

        $this->set($offset, $value);
    }

    #[\Override]
    #[\ReturnTypeWillChange]
    public function offsetUnset(mixed $offset): void
    {
        $this->remove($offset);
    }

    #[\Override]
    public function containsKey(string|int $key): bool
    {
        return isset($this->elements[$key]) || array_key_exists($key, $this->elements);
    }

    #[\Override]
    public function contains(mixed $element): bool
    {
        return in_array($element, $this->elements, true);
    }

    #[\Override]
    public function exists(\Closure $p): bool
    {
        foreach ($this->elements as $key => $element) {
            if ($p($key, $element)) {
                return true;
            }
        }

        return false;
    }

    #[\Override]
    public function indexOf($element): string|int|false
    {
        return array_search($element, $this->elements, true);
    }

    #[\Override]
    public function get(string|int $key): ?DataTransferObjectInterface
    {
        return $this->elements[$key] ?? null;
    }

    #[\Override]
    public function getKeys(): array
    {
        return array_keys($this->elements);
    }

    #[\Override]
    public function getValues(): array
    {
        return array_values($this->elements);
    }

    #[\Override]
    #[\ReturnTypeWillChange]
    public function count(): int
    {
        return count($this->elements);
    }

    #[\Override]
    public function set(string|int $key, mixed $value): void
    {
        $this->elements[$key] = $value;
    }

    #[\Override]
    public function add(mixed $element): void
    {
        $this->elements[] = $element;
    }

    #[\Override]
    public function isEmpty(): bool
    {
        return empty($this->elements);
    }

    #[\Override]
    #[\ReturnTypeWillChange]
    public function getIterator(): \Traversable
    {
        return new \ArrayIterator($this->elements);
    }

    #[\Override]
    public function map(\Closure $func): \Doctrine\Common\Collections\Collection
    {
        return new \Doctrine\Common\Collections\ArrayCollection(array_map($func, $this->elements));
    }

    #[\Override]
    public function reduce(\Closure $func, $initial = null)
    {
        return array_reduce($this->elements, $func, $initial);
    }

    #[\Override]
    public function filter(\Closure $p): static
    {
        return $this->createFrom(array_filter($this->elements, $p, ARRAY_FILTER_USE_BOTH));
    }

    #[\Override]
    public function findFirst(\Closure $p): ?DataTransferObjectInterface
    {
        foreach ($this->elements as $key => $element) {
            if ($p($key, $element)) {
                return $element;
            }
        }

        return null;
    }

    #[\Override]
    public function forAll(\Closure $p): bool
    {
        foreach ($this->elements as $key => $element) {
            if (!$p($key, $element)) {
                return false;
            }
        }

        return true;
    }

    #[\Override]
    public function partition(\Closure $p): array
    {
        $matches = [];
        $noMatches = [];

        foreach ($this->elements as $key => $element) {
            if ($p($key, $element)) {
                $matches[$key] = $element;
                continue;
            }

            $noMatches[$key] = $element;
        }

        return [$this->createFrom($matches), $this->createFrom($noMatches)];
    }

    #[\Override]
    #[\ReturnTypeWillChange]
    public function __toString(): string
    {
        return self::class . '@' . spl_object_hash($this);
    }

    #[\Override]
    public function clear(): void
    {
        $this->elements = [];
    }

    #[\Override]
    public function slice(int $offset, int|null $length = null)
    {
        return array_slice($this->elements, $offset, $length, true);
    }

    public function matching(\Doctrine\Common\Collections\Criteria $criteria): static
    {
        $expr = $criteria->getWhereExpression();
        $filtered = $this->elements;

        if ($expr) {
            $visitor = new \Doctrine\Common\Collections\Expr\ClosureExpressionVisitor();
            $filter = $visitor->dispatch($expr);
            $filtered = array_filter($filtered, $filter);
        }

        $orderings = $criteria->orderings();

        if ($orderings) {
            $next = null;
            foreach (array_reverse($orderings) as $field => $ordering) {
                $next = \Doctrine\Common\Collections\Expr\ClosureExpressionVisitor::sortByField(
                    $field,
                    $ordering === \Doctrine\Common\Collections\Order::Descending ? -1 : 1,
                    $next
                );
            }

            uasort($filtered, $next);
        }

        $offset = $criteria->getFirstResult();
        $length = $criteria->getMaxResults();

        if ($offset !== null && $offset > 0 || $length !== null && $length > 0) {
            $filtered = array_slice($filtered, (int) $offset, $length, true);
        }

        return $this->createFrom($filtered);
    }

    public function getElements(): array
    {
        return $this->elements;
    }
}
