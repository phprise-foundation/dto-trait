# phprise/dto-trait

The Atomic Repository is the smallest possible unit of granularity.
It enforces a strict architectural boundary through the following rules:

1. **No Subdirectories**: Only one level of directory inside src/.
2. **Maximum 10 Files**: Only 10 files per package.
3. **Strict Typing**: Mandatory `declare(strict_types=1)` and full type hinting for all properties and methods.
4. **Object Calisthenics**: Maximum of 50 lines per class and 2 instance variables to ensure extreme cohesion.
5. **Value Objects**: All primitives must be wrapped. Raw strings or integers are not permitted in domain logic.
6. **First Class Collections**: Arrays are forbidden for data transport; use dedicated Collection objects.
7. **Tell, Don't Ask**: Getters and setters are prohibited. Objects must expose behavior, not state.
8. **Infrastructure Ignorance**: The domain core is decoupled from persistence, frameworks, and external tools.
9. **Logic Flow**: The `else` keyword is banned. Use guard clauses and early returns to minimize indentation.


## Installation

Install the package via composer:

```bash
composer require phprise/dto-trait
```

## Usage

[Describe here the usage]


## Philosophy
We follow **The OTAKU Manifesto: Fluid Structure Design**.

1. **O** - Own your Discipline (Be strict with yourself)
2. **T** - Tools for Composition (Compose like Unix)
3. **A** - Armor the Core (Protect the heart of the business)
4. **K** - Keep Infrastructure Silent (Infrastructure is just a detail)
5. **U** - Universal Language & Contracts (Speak the user's language via clear contracts)

Please read more about it in [PHILOSOPHY.md](PHILOSOPHY.md).


## License
MIT License

Free to use, modify, and distribute.