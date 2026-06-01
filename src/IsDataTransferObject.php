<?php

declare(strict_types=1);

namespace Phprise\Dto\Trait;

use Phprise\Dto\Contract\DataTransferObjectInterface;
use Phprise\Common\Trait\IsArrayable;
use Phprise\Common\Trait\IsArrayHydratable;
use Phprise\Common\Trait\IsCamelCaseArrayable;
use Phprise\Common\Trait\IsCamelCaseJsonable;
use Phprise\Common\Trait\IsJsonable;
use Phprise\Common\Trait\IsJsonHydratable;
use Phprise\Common\Trait\IsSnakeCaseArrayable;
use Phprise\Common\Trait\IsSnakeCaseJsonable;
use Phprise\Common\Trait\IsTemplateFormattable;
use Stringable;

trait IsDataTransferObject
{
    use IsArrayable,
        IsArrayHydratable,
        IsJsonHydratable,
        IsJsonable,
        IsSnakeCaseArrayable,
        IsSnakeCaseJsonable,
        IsCamelCaseArrayable,
        IsCamelCaseJsonable,
        IsTemplateFormattable,
        IsEntityHydrator;

    public const int USE_DEFAULTS = 0;
    public const int USE_STRICT = 1;

    #[\Override]
    public function jsonSerialize(): mixed
    {
        return $this->toArray();
    }

    #[\Override]
    public function __toString(): string
    {
        return $this->toJson();
    }

    public function toString(): string
    {
        return $this->__toString();
    }
}
