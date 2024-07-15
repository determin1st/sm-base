# state machine base

- :suspect: [conio](conio.md)
- :feelsgood: [hurl](hurl.md)
- :suspect: [mustache](mustache.md)
- :finnadie: [promise](promise.md)

## autoload<sup>[◥][autoload]</sup>
```php
require 'sm-base/autoload.php';
```

## autoload with composer<sup>[◥][composer]</sup>
```sh
...
```

## naming
- classes: `PascalCase` or `UpperCamelCase`
- constants: `self::ALL_CAPS_SNAKE_CASE`
- static property: `self::$ALL_CAPS_SNAKE_CASE`
- static variable: `$ALL_CAPS_SNAKE_CASE`
- static method: `self::lower_caps_snake_case()`
- dynamic property/method: `$this->isCamelCase`
- array parameter: `$options['lower-caps-hyphen-case']`
- files: `lower-caps-hyphen-case.ext`


[autoload]: https://www.php.net/manual/en/language.oop5.autoload.php
[composer]: https://en.wikipedia.org/wiki/Composer_(software)

